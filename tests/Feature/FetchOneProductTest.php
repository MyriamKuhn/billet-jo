<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Product;

class FetchOneProductTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsProductDetailsIfProductExists()
    {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'id' => $product->id,
                        'name' => $product->name,
                    ],
                ]);
    }

    public function testItReturns404IfProductDoesNotExist()
    {
        $response = $this->getJson("/api/products/9999");

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Resource not found',
                    'code' => 'not_found',
                ]);
    }
}
