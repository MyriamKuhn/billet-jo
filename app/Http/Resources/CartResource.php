<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
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
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->user_id,
            'cart_items' => CartItemResource::collection(
                $this->whenLoaded('cartItems')
            ),
        ];
    }
}

