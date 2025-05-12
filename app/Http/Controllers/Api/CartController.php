<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Http\Resources\CartResource;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Product;

class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get current cart (guest or authenticated user).
     *
     * @OA\Get(
     *     operationId="getCurrentCart",
     *     path="/api/cart",
     *     tags={"Carts"},
     *     summary="Retrieve current shopping cart",
 *     description="
Returns the current cart contents:

- **Authenticated users**: full cart model (cart ID, user ID, items with product details)
- **Guests**: a simple map of product IDs to quantities

Provide a Bearer token to operate on the user’s cart; omit it to operate on the guest cart.
",
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Cart retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"data"},
     *             @OA\Property(
     *               property="data",
     *               oneOf={
     *                 @OA\Schema(ref="#/components/schemas/CartMinimal"),
     *                 @OA\Schema(
     *                      schema="GuestCart",
     *                      type="object",
     *                      required={"cart_items"},
     *                      @OA\Property(
     *                          property="cart_items",
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/CartItemMinimal")
     *                      )
     *                  )
     *               }
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCurrentCart();

        if ($cart instanceof Cart) {
            // Authenticated
            $cart->load(['cartItems.product' => fn($q) => $q->select(['id','stock_quantity','name','product_details'])]);

            return response()->json([
                'data' => (new CartResource($cart))->resolve()
            ], 200);
        }

        // Guest
        $itemsMap = $cart;
        $products = Product::whereIn('id', array_keys($itemsMap))
            ->get(['id','stock_quantity','name','price','sale','product_details']);

        $guestItems = $products->map(function(Product $product) use ($itemsMap) {
            $qty          = $itemsMap[$product->id];
            $available    = $product->stock_quantity >= $qty;
            $original     = $product->price;
            $discountRate = $product->sale ?? 0.0;
            $unitPrice    = round($original * (1 - $discountRate), 2);
            $totalPrice   = round($unitPrice * $qty, 2);

            return [
                'id'                 => null,
                'product_id'         => $product->id,
                'quantity'           => $qty,
                'in_stock'           => $available,
                'available_quantity' => $product->stock_quantity,
                'unit_price'         => $unitPrice,
                'total_price'        => $totalPrice,
                'original_price'     => $discountRate > 0 ? $original : null,
                'discount_rate'      => $discountRate > 0 ? $discountRate : null,
                'product'            => [
                    'name'     => $product->name,
                    'image'    => $product->product_details['image']    ?? null,
                    'date'     => $product->product_details['date']     ?? null,
                    'time'     => $product->product_details['time']     ?? null,
                    'location' => $product->product_details['location'] ?? null,
                ],
            ];
        })->values();

        return response()->json([
            'data' => [
                'cart_items' => $guestItems,
            ]
        ], 200);
    }

    /**
     * Update the quantity of an item in the current cart.
     *
     * @OA\Patch(
     *     path="/api/cart/items/{product}",
     *     operationId="updateCartItem",
     *     tags={"Carts"},
     *     summary="Set cart item quantity or remove item",
     *     description="
Updates the quantity for a given product in the current cart:

- **quantity > 0**: set the new quantity
- **quantity = 0**: remove the item from the cart

Accessible by guests and authenticated users. Provide a Bearer token to update the user’s cart; omit it to update the guest cart.
",
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="ID of the product to update",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="integer", example=3, description="New quantity (0 to remove item)")
     *         )
     *     ),
     *     @OA\Response(response=204, ref="#/components/responses/NoContent"),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=409, ref="#/components/responses/StockUnavailable"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @param  Product  $product
     * @param  UpdateCartItemRequest  $request
     * @return JsonResponse
     */
    public function updateItem(Product $product, UpdateCartItemRequest $request): JsonResponse
    {
        $newQty = $request->input('quantity');
        $productId = $product->id;

        // Retrieve the current cart
        $currentCart = $this->cartService->getCurrentCart();
        if (is_array($currentCart)) {
            // guest
            $currentQty = (int) ($currentCart[$productId] ?? 0);
        } else {
            // user
            $item = $currentCart->cartItems()->where('product_id', $productId)->first();
            $currentQty = $item ? $item->quantity : 0;
        }

        $delta = $newQty - $currentQty;

        if ($delta > 0) {
            $this->cartService->addItem($productId, $delta);
        } elseif ($delta < 0) {
            $this->cartService->removeItem($productId, abs($delta));
        }
        // if $delta === 0, nothing to do

        return response()->json(null, 204);
    }

    /**
     * Clear the entire cart of the authenticated user.
     *
     * @OA\Delete(
     *     path="/api/cart/items",
     *     tags={"Carts"},
     *     summary="Clear authenticated user's cart",
     *     description="Deletes every item from the current authenticated user’s shopping cart. Requires a valid Bearer token.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=204, ref="#/components/responses/NoContent"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @return JsonResponse
     */
    public function clearCart(): JsonResponse
    {
        // The user must be authenticated to clear the cart
        $this->cartService->clearCart();

        return response()->json(null, 204);
    }
}
