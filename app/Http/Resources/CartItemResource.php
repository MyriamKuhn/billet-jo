<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="CartItemMinimal",
 *   title="CartItemMinimal",
 *   type="object",
 *   required={"id","quantity","unit_price","total_price","product"},
 *   @OA\Property(property="id",            type="integer", example=7),
 *   @OA\Property(property="product_id",    type="integer", example=42),
 *   @OA\Property(property="quantity",      type="integer", example=3),
 *   @OA\Property(property="unit_price",    type="number",  format="float", example=53.99),
 *   @OA\Property(property="total_price",   type="number",  format="float", example=161.97),
 *   @OA\Property(property="discount_rate", type="number",  format="float", example=0.10, nullable=true),
 *   @OA\Property(property="original_price",type="number",  format="float", example=59.99, nullable=true),
 *   @OA\Property(
 *     property="product",
 *     type="object",
 *     required={"name","image","date","location"},
 *     @OA\Property(property="name",     type="string", example="Billet concert"),
 *     @OA\Property(property="image",    type="string", example="name-of-image.jpg"),
 *     @OA\Property(property="date",     type="string", format="date", example="2024-07-26"),
 *     @OA\Property(property="location", type="string", example="Stade de France")
 *   )
 * )
 */
class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $originalPrice  = $this->product->price;
        $discountRate   = $this->product->sale ?? 0.0; // ex. 0.10 = 10%
        $unitPrice      = round($originalPrice * (1 - $discountRate), 2);
        $totalPrice     = round($unitPrice * $this->quantity, 2);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,

            // Price after discount
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,

            // Show only if the product is on sale
            $this->mergeWhen($discountRate > 0, [
                'original_price' => $originalPrice,
                'discount_rate' => $discountRate,
            ]),

            // On choisit seulement les champs nécessaires du produit
            'product'  => [
                'name'     => $this->product->name,
                'image'    => $this->product->product_details['image']    ?? null,
                'date'     => $this->product->product_details['date']     ?? null,
                'time'     => $this->product->product_details['time']     ?? null,
                'location' => $this->product->product_details['location'] ?? null,
            ],
        ];
    }
}
