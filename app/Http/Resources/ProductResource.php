<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'price'          => $this->price,
            'sale'           => $this->sale,
            'stock_quantity' => $this->stock_quantity,

            // On injecte notre Resource du JSON colonné
            'product_details'=> new ProductDetailsResource(
                $this->product_details  // array issu du cast
            ),
        ];
    }
}

