<?php

namespace App\Services;

use App\Models\Product;

class ProductManagementService
{
    /**
     * Create a new product with its details.
     *
     * @param  array  $data  Validated input from StoreProductRequest.
     * @return \App\Models\Product
     */
    public function create(array $data): Product
    {
        return Product::create([
            'name'           => $data['name'],
            'price'          => $data['price'],
            'sale'           => $data['sale'] ?? null,
            'stock_quantity' => $data['stock_quantity'],
            'product_details'=> $data['product_details'],
        ]);
    }

    /**
     * Update a product with the given data.
     *
     * @param  Product  $product
     * @param  array    $data
     * @return Product
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->refresh();
    }
}
