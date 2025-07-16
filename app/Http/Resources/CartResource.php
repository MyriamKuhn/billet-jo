<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource representation of a shopping cart.
 *
 * @OA\Schema(
 *   schema="CartMinimal",
 *   title="CartMinimal",
 *   type="object",
 *   required={"id","user_id","cart_items"},
 *   @OA\Property(property="id",      type="integer", example=1),
 *   @OA\Property(property="user_id", type="integer", example=42),
 *   @OA\Property(
 *     property="cart_items",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/CartItemMinimal")
 *   )
 * )
 */
class CartResource extends JsonResource
{
    /**
     * Transform the cart resource into an array for JSON serialization.
     *
     * Includes:
     * - id: the cart's unique identifier
     * - user_id: the ID of the owning user
     * - cart_items: a collection of cart item resources
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            // Cart identifier
            'id'         => $this->id,
            // ID of the user who owns this cart
            'user_id'    => $this->user_id,
            // List of items in the cart, if the relationship was loaded
            'cart_items' => CartItemResource::collection(
                $this->whenLoaded('cartItems')
            ),
        ];
    }
}

