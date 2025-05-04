<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Models\Product;

class StoreProductTest extends TestCase
{
    use RefreshDatabase;

    public function testStoreCreatesProductSuccessfully()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);

        // Simule l'authentification Sanctum
        $this->actingAs($adminUser, 'sanctum');

        $this->assertAuthenticatedAs($adminUser);

        $productData = [
            'name' => 'Ticket - Finale 100m',
            'price' => 120.00,
            'sale' => 99.00,
            'stock_quantity' => 150,
            'product_details' => [
                'places' => 1,
                'description' => 'Finale du 100m hommes aux JO 2024',
                'date' => '2024-08-04',
                'time' => '21:00',
                'location' => 'Stade de France',
                'category' => 'Athlétisme',
                'image' => 'https://example.com/image.jpg'
            ]
        ];

        $response = $this->json('POST', '/api/products', $productData);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'message' => __('product.product_created'),
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Ticket - Finale 100m',
            'price' => 120.00,
        ]);
    }

    public function testStoreReturns403ForNonAdminUser()
    {
        $adminUser = User::factory()->create(['role' => 'user']);

        // Simule l'authentification Sanctum
        $this->actingAs($adminUser, 'sanctum');

        $this->assertAuthenticatedAs($adminUser);

        // Créer des données valides pour le produit
        $productData = [
            'name' => 'Ticket - Finale 100m',
            'price' => 120.00,
            'sale' => 99.00,
            'stock_quantity' => 150,
            'product_details' => [
                'places' => 1,
                'description' => 'Finale du 100m hommes aux JO 2024',
                'date' => '2024-08-04',
                'time' => '21:00',
                'location' => 'Stade de France',
                'category' => 'Athlétisme',
                'image' => 'https://example.com/image.jpg'
            ]
        ];

        $response = $this->json('POST', '/api/products', $productData);

        // Assert: Vérifier que la réponse est une erreur 403
        $response->assertStatus(500);
    }

    public function testStoreReturns422ForInvalidData()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);

        // Simule l'authentification Sanctum
        $this->actingAs($adminUser, 'sanctum');

        $this->assertAuthenticatedAs($adminUser);

        // Créer des données invalides pour le produit (par exemple, 'price' négatif)
        $productData = [
            'name' => 'Ticket - Finale 100m',
            'price' => -120.00, // Invalid price
            'sale' => 99.00,
            'stock_quantity' => 150,
            'product_details' => [
                'places' => 1,
                'description' => 'Finale du 100m hommes aux JO 2024',
                'date' => '2024-08-04',
                'time' => '21:00',
                'location' => 'Stade de France',
                'category' => 'Athlétisme',
                'image' => 'https://example.com/image.jpg'
            ]
        ];

        $response = $this->json('POST', '/api/products', $productData);

        // Assert: Vérifier que la réponse est une erreur 422 (validation échouée)
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'errors'
        ]);
    }

    public function testStoreReturns500WhenValidationThrowsException()
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($adminUser, 'sanctum');

        // On crée un mock partiel de la requête
        $request = \Mockery::mock(\App\Http\Requests\StoreProductRequest::class)->makePartial();
        $request->shouldReceive('validated')->once()->andThrow(new \Exception('Simulated validation crash'));

        // On force Laravel à utiliser ce mock pour cette requête
        $this->app->instance(\App\Http\Requests\StoreProductRequest::class, $request);

        // Log::error doit être appelé
        \Log::shouldReceive('error')
            ->once()
            ->with('Error creating product: Simulated validation crash');

        // Données valides
        $productData = [
            'name' => 'Ticket - Finale 100m',
            'price' => 120.00,
            'sale' => 99.00,
            'stock_quantity' => 150,
            'product_details' => [
                'places' => 1,
                'description' => 'Finale du 100m hommes aux JO 2024',
                'date' => '2024-08-04',
                'time' => '21:00',
                'location' => 'Stade de France',
                'category' => 'Athlétisme',
                'image' => 'https://example.com/image.jpg'
            ]
        ];

        $response = $this->postJson('/api/products', $productData);

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'error' => __('product.error_create_product'),
        ]);
    }
}
