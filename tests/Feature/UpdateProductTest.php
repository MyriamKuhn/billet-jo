<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Mockery;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use App\Http\Requests\StoreProductRequest;
use App\Http\Controllers\Api\ProductController;


class UpdateProductTest extends TestCase
{
    use RefreshDatabase;

    public function testUpdateProductSuccess()
    {
        // Créer un utilisateur administrateur (ou simuler l'authentification de l'admin)
        $admin = User::factory()->create(['role' => 'admin']); // Adapté à ton modèle utilisateur
        $this->actingAs($admin);

        // Créer un produit à mettre à jour
        $product = Product::factory()->create();

        // Créer les données pour la mise à jour
        $updateData = [
            'name' => 'Updated Product',
            'price' => 150.00,
            'sale' => 10.0,
            'stock_quantity' => 200,
            'product_details' => [
                'places' => 300,
                'description' => 'Updated description',
                'date' => '2025-06-01',
                'time' => '14:00',
                'location' => 'Stadium B',
                'category' => 'Sports',
                'image' => 'http://example.com/updated-product-image.jpg',
            ],
        ];

        // Appel API pour la mise à jour du produit
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // Vérifier la réponse
        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Product updated successfully',
                    'product' => [
                        'name' => 'Updated Product',
                        'price' => 150.00,
                        'sale' => 10.0,
                        'stock_quantity' => 200,
                    ]
                ]);

        // Vérifier que le produit a bien été mis à jour dans la base de données
        $product->refresh();
        $this->assertEquals('Updated Product', $product->name);
        $this->assertEquals(150.00, $product->price);
    }

    public function testUpdateProductValidationFailed()
    {
        // Créer un utilisateur administrateur
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        // Créer un produit à mettre à jour
        $product = Product::factory()->create();

        // Créer des données de mise à jour invalides (par exemple, "name" manquant)
        $invalidData = [
            'price' => 150.00,
            'sale' => 10.0,
            'stock_quantity' => 200,
            'product_details' => [
                'places' => 300,
                'description' => 'Updated description',
                'date' => '2025-06-01',
                'time' => '14:00',
                'location' => 'Stadium B',
                'category' => 'Sports',
                'image' => 'http://example.com/updated-product-image.jpg',
            ],
        ];

        // Appel API pour la mise à jour du produit avec des données invalides
        $response = $this->putJson("/api/products/{$product->id}", $invalidData);

        // Vérifier que la réponse est bien en erreur de validation
        $response->assertStatus(422); // HTTP 422 pour les erreurs de validation
        $response->assertJsonValidationErrors(['name']); // Vérifier que le champ "name" manque
    }
}
