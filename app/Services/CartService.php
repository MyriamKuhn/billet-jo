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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Exceptions\StockUnavailableException;
use App\Models\Product;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * CartService handles operations related to user and guest carts.
 * It supports both authenticated users and guests using Redis for guest carts.
 */
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
     * Initializes the TTL for guest carts based on the session configuration.
     */
    public function __construct(protected LoggerInterface $logger)
    {
        // Convert session lifetime from minutes to seconds
        $this->guestTtl = config('session.lifetime', 120) * 60;
    }

    /**
     * Retrieve or create a cart for the specified user.
     *
     * @param  User  $user  The user for whom to retrieve or create a cart.
     * @return Cart         The user's cart model.
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
     * Attempts to fetch the hash at the session-specific key.
     * Any error (connection failure, timeout, etc.) is caught and logged,
     * and an empty array is returned.
     *
     * @return array<int,int>  Associative array mapping product IDs to quantities.
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
     * Add or increment an item in the guest cart (Redis).
     *
     * @param  int  $productId  The ID of the product to add.
     * @param  int  $qty        The quantity to add (must be at least 1).
     * @throws BadRequestHttpException  If the quantity is less than 1.
     * @return void
     */
    public function addItemForGuest(int $productId, int $qty = 1): void
    {
        if ($qty < 1) {
            throw new BadRequestHttpException('Quantity must be at least 1');
        }

        $items = $this->getGuestCart();
        $items[$productId] = ($items[$productId] ?? 0) + $qty;

        // Ensure enough stock for all requested items
        $this->assertStockAvailable($items);

        try {
            $key = $this->guestKey();
            Redis::hincrby($key, $productId, $qty);
            Redis::expire($key, $this->guestTtl);
        } catch (RedisConnectionException $e) {
            $this->logger->error(
                'Error adding item to guest cart in Redis.',
                ['exception' => $e]
            );
        }
    }

    /**
     * Remove or decrement an item from the guest cart (Redis).
     *
     * @param  int        $productId  The ID of the product to remove or decrement.
     * @param  int|null   $qty        The amount to remove; if null, the item is removed entirely.
     * @return void
     */
    public function removeItemForGuest(int $productId, ?int $qty = null): void
    {
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
            Redis::expire($key, $this->guestTtl);
        } catch (RedisConnectionException $e) {
            $this->logger->warning(
                'Unable to modify guest cart in Redis.',
                ['exception' => $e]
            );
        }
    }

    /**
     * Add or increment an item in the cart for an authenticated user.
     *
     * @param  User  $user       The authenticated user.
     * @param  int   $productId  The ID of the product to add.
     * @param  int   $qty        The quantity to add (must be at least 1).
     * @throws BadRequestHttpException    If the quantity is less than 1.
     * @throws QueryException             If a database error occurs.
     * @return void
     */
    public function addItemForUser(User $user, int $productId, int $qty = 1): void
    {
        if ($qty < 1) {
            throw new BadRequestHttpException('Quantity must be at least 1');
        }

        $cart = $this->getUserCart($user);
        $cart->load(['cartItems']);
        $items = $cart->cartItems->pluck('quantity', 'product_id')->toArray();
        $items[$productId] = ($items[$productId] ?? 0) + $qty;

        // Ensure enough stock
        $this->assertStockAvailable($items);

        try {
            $item = $cart->cartItems()->where('product_id', $productId)->first();
            if ($item) {
                $item->increment('quantity', $qty);
            } else {
                $cart->cartItems()->create([
                    'product_id' => $productId,
                    'quantity'   => $qty,
                ]);
            }
        } catch (QueryException $e) {
            $this->logger->error(
                'Error adding item to user cart.',
                ['exception' => $e]
            );
            throw $e;
        }
    }

    /**
     * Remove or decrement an item from the authenticated user's cart.
     *
     * @param  User        $user       The authenticated user.
     * @param  int         $productId  The ID of the product to remove or decrement.
     * @param  int|null    $qty        The amount to remove; if null, the item is removed entirely.
     * @throws QueryException           If a database error occurs.
     * @return void
     */
    public function removeItemForUser(User $user, int $productId, ?int $qty = null): void
    {
        $cart = $this->getUserCart($user);
        $item = $cart->cartItems()->where('product_id', $productId)->first();

        if (! $item) {
            return;
        }

        try {
            if ($qty === null || $item->quantity <= $qty) {
                $item->delete();
            } else {
                $item->decrement('quantity', $qty);
            }
        } catch (QueryException $e) {
            $this->logger->error(
                'Error removing item from user cart.',
                ['exception' => $e]
            );
            throw $e;
        }
    }

    /**
     * Merge all guest cart items into the user's cart.
     * Quantities for duplicate products are summed.
     *
     * @param  int  $userId  The ID of the user whose cart will receive guest items.
     * @throws ModelNotFoundException   If the user does not exist.
     * @throws QueryException           If a database error occurs.
     * @return void
     */
    public function mergeGuestIntoUser(int $userId): void
    {
        $user     = User::findOrFail($userId);
        $userCart = $this->getUserCart($user);
        $guestItems = $this->getGuestCart();

        foreach ($guestItems as $productId => $qty) {
            $item = $userCart->cartItems()
                            ->where('product_id', $productId)
                            ->first();

            if ($item) {
                $item->increment('quantity', $qty);
            } else {
                $userCart->cartItems()->create([
                    'product_id' => $productId,
                    'quantity'   => $qty,
                ]);
            }
        }

        // Clean up the guest cart in Redis and session
        try {
            Redis::del($this->guestKey());
            Session::forget('guest_cart_id');
        } catch (RedisConnectionException $e) {
            $this->logger->warning(
                'Failed to delete guest cart from Redis after merge.',
                ['exception' => $e]
            );
        }
    }

    /**
     * Generate or retrieve a unique Redis key for the guest cart.
     * Stores a UUID in the session if one is not already present.
     *
     * @return string  The Redis key for the current guest cart.
     */
    protected function guestKey(): string
    {
        $headerId = Request::header('X-Guest-Cart-Id');
        if ($headerId && \Ramsey\Uuid\Uuid::isValid($headerId)) {
            $guestId = $headerId;
        } else {
            $guestId = Session::get('guest_cart_id');
            if (! $guestId) {
                $guestId = (string) Str::uuid();
            }
        }

        Session::put('guest_cart_id', $guestId);

        return "cart:guest:{$guestId}";
    }

    /**
     * Clear all items from the authenticated user's cart.
     *
     * @param  User  $user
     * @throws QueryException  If a database error occurs during deletion.
     * @return void
     */
    public function clearCartForUser(User $user): void
    {
        try {
            $cart = $this->getUserCart($user);
            $cart->cartItems()->delete();
        } catch (QueryException $e) {
            $this->logger->error(
                'Error clearing user cart.',
                ['exception' => $e]
            );
            throw $e;
        }
    }

    /**
     * Ensure that there is sufficient stock for all items (guest or user).
     *
     * @param  Cart|array<int,int>  $cartOrItems  A Cart model or an array of [product_id => quantity].
     * @throws StockUnavailableException  If any item exceeds available stock.
     * @return void
     */
    public function assertStockAvailable(Cart|array $cartOrItems): void
    {
        if ($cartOrItems instanceof Cart) {
            $cartOrItems->load([
                'cartItems.product' => fn($q) => $q->select(['id','stock_quantity','name'])
            ]);
            $items = $cartOrItems->cartItems
                        ->mapWithKeys(fn($item) => [
                            $item->product_id => $item->quantity
                        ])->toArray();
        } else {
            $items = $cartOrItems;
        }

        $products = Product::whereIn('id', array_keys($items))
            ->get(['id','stock_quantity','name']);

        $errors = [];

        foreach ($products as $product) {
            $requested = $items[$product->id] ?? 0;
            if ($requested > $product->stock_quantity) {
                $errors[] = [
                    'product_id'         => $product->id,
                    'product_name'       => $product->name,
                    'requested_quantity' => $requested,
                    'available_quantity' => $product->stock_quantity,
                ];
            }
        }

        if (! empty($errors)) {
            throw new StockUnavailableException(
                'Stock unavailable for one or more items in the cart',
                $errors
            );
        }
    }
}

