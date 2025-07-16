<?php

namespace App\Services;

use App\Models\Product;

/**
 * ProductManagementService handles the creation and updating of products
 * and their translations in different languages.
 */
class ProductManagementService
{
    /**
     * Create a new product along with its translations.
     *
     * @param  array  $data  Validated input from StoreProductRequest.
     * @return \App\Models\Product
     */
    public function create(array $data): Product
    {
        $price = $data['price'];
        $sale  = $data['sale'] ?? null;
        $stock = $data['stock_quantity'];

        // 1) Create the base (English) product record
        $en = $data['translations']['en'];
        $product = Product::create([
            'name'            => $en['name'],
            'price'           => $price,
            'sale'            => $sale,
            'stock_quantity'  => $stock,
            'product_details' => $en['product_details'],
        ]);

        // 2) Create translations for the other locales
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
     * Update an existing product and its translations.
     *
     * @param  Product  $product
     * @param  array    $data     Validated input from StoreProductRequest.
     * @return \App\Models\Product
     */
    public function update(Product $product, array $data): Product
    {
        $price  = $data['price'];
        $sale   = $data['sale'] ?? null;
        $stock  = $data['stock_quantity'];

        // 1) Update the base (English) product record
        $en = $data['translations']['en'];
        $product->update([
			'name'            => $en['name'],
			'price'           => $price,
			'sale'            => $sale,
			'stock_quantity'  => $stock,
			'product_details' => $en['product_details'],
		]);

        // 2) Update or create translations for the other locales
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
     * Update only the price, sale, and stock quantity of a product.
     *
     * @param  Product  $product
     * @param  array    $data     Validated input from UpdateProductPricingRequest.
     * @return void
     */
    public function updatePricing(Product $product, array $data): void
    {
        // Fill in only the provided fields (price, sale, stock_quantity)
        $product->fill($data);

        // Save the changes
        $product->save();
    }
}
