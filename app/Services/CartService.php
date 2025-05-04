<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\ConnectionException as RedisConnectionException;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use Throwable;

class CartService
{
    /**
     * Time to live for the guest cart in seconds (session.lifetime * 60).
     *
     * @var int
     */
    protected int $guestTtl;

    /**
     * CartService constructor.
     *
     * Initializes the TTL for guest carts based on session configuration.
     */
    public function __construct(protected LoggerInterface $logger)
    {
        // Set TTL from session lifetime (in minutes) to seconds
        $this->guestTtl = config('session.lifetime', 120) * 60;
    }

    /**
     * Get the current cart for the authenticated user or guest.
     *
     * @return Cart|array<int,int>  Returns the Cart model for authenticated users
     *                              or an associative array of product IDs to quantities for guests.
     */
    public function getCurrentCart(): Cart|array
    {
        // If user is logged in, retrieve their persistent cart; otherwise, return the guest cart
        return auth()->check()
            ? $this->getUserCart(auth()->user())
            : $this->getGuestCart();
    }

    /**
     * Retrieve or create a cart for the specified user.
     *
     * @param  User  $user  The user for whom to retrieve or create a cart.
     * @return Cart  The user's cart model.
     */
    public function getUserCart(User $user): Cart
    {
        // First or create ensures a cart record exists for the user
        return Cart::firstOrCreate([
            'user_id' => $user->id,
        ]);
    }

    /**
     * Retrieve the guest cart items stored in Redis.
     *
     * Attempts to fetch the hash at the session-specific key. Any error
     * (connection failure, timeout, etc.) is caught and logged, and an empty
     * array is returned.
     *
     * @return array<int,int>  An associative array mapping product IDs to quantities.
     */
    public function getGuestCart(): array
    {
        try {
            // Attempt to fetch the guest cart from Redis using the unique key for this session
            $key = $this->guestKey();
            return Redis::hgetall($key) ?: [];
        } catch (Throwable $e) {
            // On error, log and always return an empty array (no exception propagation)
            app(LoggerInterface::class)
                ->warning('Could not fetch guest cart from Redis', ['exception' => $e]);
            return [];
        }
    }

    /**
     * Add or increment an item in the cart for a user or guest.
     *
     * @param  int  $productId  The ID of the product to add.
     * @param  int  $qty        The quantity to add (must be at least 1).
     * @throws \InvalidArgumentException            If the quantity is less than 1.
     * @throws \Illuminate\Database\QueryException If a database error occurs while updating a user cart.
     * @return void
     */
    public function addItem(int $productId, int $qty = 1): void
    {
        if ($qty < 1) {
            // Validate quantity
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        if (auth()->check()) {
            // Logged in: persist cart in database
            try {
                $cart = $this->getUserCart(auth()->user());
                $item = $cart->cartItems()->where('product_id', $productId)->first();

                if ($item) {
                    // Increment existing cart item quantity
                    $item->increment('quantity', $qty);
                } else {
                    // Create a new cart item for the user
                    $cart->cartItems()->create([
                        'product_id' => $productId,
                        'quantity'   => $qty,
                    ]);
                }
            } catch (QueryException $e) {
                // Log database errors and rethrow for higher-level handling
                $this->logger->error(
                    'Error adding item to user cart.',
                    ['exception' => $e]
                );
                throw $e;
            }
        } else {
            // Guest: store cart in Redis with TTL
            try {
                $key = $this->guestKey();
                // hincrby adds the quantity atomically
                Redis::hincrby($key, $productId, $qty);
                // Reset the TTL so guest cart persists during session
                Redis::expire($key, $this->guestTtl);
            } catch (RedisConnectionException $e) {
                // Log Redis errors but do not interrupt guest flow
                $this->logger->error(
                    'Error adding item to guest cart in Redis.',
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * Merge all guest items into the user's cart by adding quantities for duplicates.
     *
     * @param  int  $userId                            The ID of the user whose cart will receive guest items.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the user does not exist.
     * @throws \Illuminate\Database\QueryException                 If a database error occurs while merging carts.
     * @return void
     */
    public function mergeGuestIntoUser(int $userId): void
    {
        // Retrieve user or fail
        $user = User::findOrFail($userId);
        $userCart = $this->getUserCart($user);
        $guestItems = $this->getGuestCart();

        foreach ($guestItems as $productId => $qty) {
            $item = $userCart->cartItems()
                            ->where('product_id', $productId)
                            ->first();

            if ($item) {
                // If item exists, add guest quantity to existing quantity
                $item->increment('quantity', $qty);
            } else {
                // Otherwise, create new cart item
                $userCart->cartItems()->create([
                    'product_id' => $productId,
                    'quantity'   => $qty,
                ]);
            }
        }

        // Cleanup guest cart data from Redis and session
        try {
            Redis::del($this->guestKey());
            Session::forget('guest_cart_id');
        } catch (RedisConnectionException $e) {
            // Log warning if cleanup fails; not critical to user flow
            $this->logger->warning(
                'Failed to delete guest cart from Redis after merge.',
                ['exception' => $e]
            );
        }
    }

    /**
     * Generate and retrieve a unique Redis key for the guest cart.
     * Stores a UUID in session if not already set.
     *
     * @return string  The Redis key for the current guest cart.
     */
    protected function guestKey(): string
    {
        // Retrieve or generate a unique ID for this guest session
        $guestId = Session::get('guest_cart_id');
        if (! $guestId) {
            $guestId = (string) Str::uuid();
            Session::put('guest_cart_id', $guestId);
        }

        // Return the Redis key namespace for guest carts
        return "cart:guest:{$guestId}";
    }

    /**
     * Remove or decrement an item from the current cart (guest or authenticated user).
     *
     * - For authenticated users: deletes the CartItem if $qty is null or exceeds current quantity,
     *   otherwise decrements by $qty.
     * - For guests: operates on the Redis hash under the session-specific key.
     *
     * @param  int        $productId  The ID of the product to remove or decrement.
     * @param  int|null   $qty        The amount to remove; if null, the item is fully removed.
     *
     * @throws \Illuminate\Database\QueryException
     *         If a database error occurs when modifying the user’s cart.
     *
     * @return void
     */
    public function removeItem(int $productId, ?int $qty = null): void
    {
        if (auth()->check()) {
            // Authenticated user: remove or decrement in database
            $cart = $this->getUserCart(auth()->user());
            $item = $cart->cartItems()
                        ->where('product_id', $productId)
                        ->first();

            if (!$item) {
                return;
            }

            try {
                if ($qty === null || $item->quantity <= $qty) {
                    $item->delete();
                } else {
                    $item->decrement('quantity', $qty);
                }
            } catch (QueryException $e) {
                // Log a detailed error before letting the global handler catch it
                $this->logger->error(
                    'Error removing item from user cart.',
                    ['exception' => $e]);
                throw $e;
            }
        } else {
            // Guest: operate on Redis hash
            $key = $this->guestKey();

            try {
                if ($qty === null) {
                    Redis::hdel($key, (string) $productId);
                } else {
                    $current = (int) (Redis::hget($key, (string) $productId) ?? 0);
                    if ($current <= $qty) {
                        Redis::hdel($key, (string) $productId);
                    } else {
                        Redis::hincrby($key, (string) $productId, -$qty);
                    }
                }
                // Refresh TTL so the guest cart persists for the session duration
                Redis::expire($key, $this->guestTtl);
            } catch (RedisConnectionException $e) {
                // Log and silently swallow— we want guests to continue without Redis
                $this->logger->warning(
                    'Unable to modify guest cart in Redis.',
                    ['exception' => $e]
                );
            }
        }
    }

    /**
     * Remove all items from the authenticated user’s cart.
     *
     * This method must only be invoked for an authenticated user (protected by
     * auth:sanctum). It deletes all cart_items records belonging to the user.
     *
     * @throws \Illuminate\Database\QueryException
     *         If a database error occurs when deleting cart items.
     * @return void
     */
    public function clearCart(): void
    {
        // Only authenticated users can reach here
        try {
            $cart = $this->getUserCart(auth()->user());
            $cart->cartItems()->delete();
        } catch (QueryException $e) {
            // Log the failure for diagnostics and rethrow so the global handler
            // can return a structured 500 JSON response
            $this->logger->error(
                'Error clearing user cart.',
                ['exception' => $e]
            );
            throw $e;
        }
    }
}
