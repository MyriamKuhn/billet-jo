<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="ProductSalesResource",
 *   type="object",
 *   @OA\Property(property="product_id",   type="integer", example=42),
 *   @OA\Property(property="product_name", type="string",  example="Concert VIP Ticket"),
 *   @OA\Property(property="sales_count",  type="integer", example=128)
 * )
 */
class ProductSalesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'product_id'   => $this->product_id,
            'product_name' => $this->product->name,        // on aura chargée la colonne via le JOIN
            'sales_count'  => (int) $this->sales_count,
        ];
    }
}
