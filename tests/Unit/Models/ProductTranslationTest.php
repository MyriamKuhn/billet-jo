<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

class ProductTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function testItDefinesTheCorrectFillableAttributes()
    {
        $model = new ProductTranslation();

        $this->assertEquals(
            ['product_id', 'locale', 'name', 'product_details'],
            $model->getFillable()
        );
    }

    public function testItCastsProductDetailsToAnArray()
    {
        $model = new ProductTranslation();

        $casts = $model->getCasts();

        $this->assertArrayHasKey('product_details', $casts);
        $this->assertSame('array', $casts['product_details']);
    }

    public function testItRoundTripsTheProductDetailsJsonArray()
    {
        $details = [
            'places'      => 3,
            'description' => ['Ligne 1', 'Ligne 2'],
            'date'        => '2025-07-26',
            'time'        => '20:00',
            'location'    => 'Stade exemple',
            'category'    => 'Test',
            'image'       => 'https://example.com/img.png',
        ];

        // On simule la saisie d'un array et on vérifie que le getter renvoie bien un array identique.
        $model = new ProductTranslation();
        $model->product_details = $details;

        $this->assertIsArray($model->product_details);
        $this->assertSame($details, $model->product_details);
    }

    public function testItBelongsToAProduct()
    {
        // On crée un produit en base
        $product = Product::factory()->create();

        // On crée la traduction liée
        $translation = ProductTranslation::factory()->create([
            'product_id' => $product->id,
        ]);

        // On vérifie que la relation fonctionne comme prévu
        $relation = $translation->product();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertTrue($relation->exists());
        $this->assertEquals($product->id, $translation->product->id);
    }
}
