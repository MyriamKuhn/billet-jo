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
     *     @OA\Response(
     *         response=200,
     *         description="Cart retrieved successfully",
     *         @OA\JsonContent(
     *             required={"data"},
     *             oneOf={
     *               @OA\Schema(ref="#/components/schemas/CartMinimal"),
     *               @OA\Schema(
     *                 title="GuestCartResponse",
     *                 type="object",
     *                 @OA\Property(
     *                   property="data",
     *                   type="object",
     *                   description="Map of product IDs to quantities",
     *                   @OA\AdditionalProperties(
     *                     type="integer",
     *                     example=2
     *                   )
     *                 )
     *               )
     *             }
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

        // Authenticated user → CartResource
        if ($cart instanceof Cart) {
            $cart->load(['cartItems.product' => function($q) {
                $q->select(['id','name','product_details']);
            }]);
            return (new CartResource($cart))
                ->response()
                ->setStatusCode(200);
        }

        // Guest → array<int,int>
        return response()->json([
            'data' => $cart,  // array productId => qty, ici []
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
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
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
