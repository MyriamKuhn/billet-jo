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
        // 1) Create the master product
        $product = Product::create([
            'name'            => $data['translations']['en']['name'],
            'price'           => $data['translations']['en']['price'],
            'sale'            => $data['translations']['en']['sale']  ?? null,
            'stock_quantity'  => $data['translations']['en']['stock_quantity'],
            'product_details' => $data['translations']['en']['product_details'],
        ]);

        // 2) Loop on each locale to create the translations
        foreach (['fr','de'] as $locale) {
            $trans = $data['translations'][$locale];
            $product->translations()->create([
                'locale'           => $locale,
                'name'             => $trans['name'],
                'product_details'  => $trans['product_details'],
            ]);
        }

        return $product;
    }

    /**
     * Update an existing product with its details.
     *
     * @param Product $product
     * @param array $data Validated input from StoreProductRequest.
     * @return \App\Models\Product
     */
    public function update(Product $product, array $data): Product
    {
        // 1) Update the master product
        $en = $data['translations']['en'];
        $product->update([
            'name'            => $en['name'],
            'price'           => $en['price'],
            'sale'            => $en['sale']         ?? null,
            'stock_quantity'  => $en['stock_quantity'],
            'product_details' => $en['product_details'],
        ]);

        // 2) Loop on each locale to update the translations
        foreach (['fr','de'] as $locale) {
            $trans = $data['translations'][$locale];
            $product->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name'            => $trans['name'],
                    'product_details' => $trans['product_details'],
                ]
            );
        }

        return $product->refresh();
    }

    /**
     * Update only the quantity, the pricing and the sale of an existing product.
     *
     * @param Product $product
     * @param array $data Validated input from UpdateProductPricingRequest.
     * @return void
     */
    public function updatePricing(Product $product, array $data): void
    {
        $product->fill($data);

        $product->save();
    }
}
