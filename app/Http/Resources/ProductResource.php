<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a product resource, typically used in e-commerce or inventory systems.
 *
 * @OA\Schema(
 *   schema="MinimalProduct",
 *   title="MinimalProduct",
 *   type="object",
 *   required={"id","name","price","sale","stock_quantity","product_details"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Sample Product"),
 *   @OA\Property(property="price", type="number", format="float", example=49.99),
 *   @OA\Property(property="sale", type="number", format="float", example=0.10),
 *   @OA\Property(property="stock_quantity", type="integer", example=20),
 *   @OA\Property(
 *     property="product_details",
 *     ref="#/components/schemas/ProductDetails"
 *   )
 * )
 */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'price'          => (float) $this->price,
            'sale'           => (float) $this->sale,
            'stock_quantity' => $this->stock_quantity,

            'product_details'=> new ProductDetailsResource($this->product_details),
        ];
    }
}

