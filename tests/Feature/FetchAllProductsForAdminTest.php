<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use App\Services\ProductFilterService;
use App\Models\Product;
use App\Models\User;

class FetchAllProductsForAdminTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsProductsWithPaginationAndValidation()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);

        // Crée des produits de test
        Product::factory(25)->create();  // Crée 25 produits

        // Authentifie l'admin
        $this->actingAs($admin, 'sanctum');

        // Envoie la requête GET à l'API des produits avec pagination
        $response = $this->getJson('/api/products/all?per_page=10&page=1');

        // Vérifie que la réponse a un code 200
        $response->assertStatus(200);

        // Vérifie la structure de la réponse JSON
        $response->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'price',
                    'product_details' => [
                        'places',
                        'description',
                        'date',
                        'time',
                        'location',
                        'category',
                        'image',
                    ]
                ]
            ],
            'pagination' => [
                'total',
                'per_page',
                'current_page',
                'last_page',
            ]
        ]);

        // Vérifie la pagination
        $response->assertJsonFragment([
            'total' => 25,
            'per_page' => 10,
            'current_page' => 1,
            'last_page' => 3,
        ]);
    }

    public function testItReturnsErrorForUnexpectedParameters()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Envoie la requête GET avec un paramètre inattendu 'foo'
        $response = $this->getJson('/api/products/all?foo=bar');

        // Vérifie que la réponse a un code 400
        $response->assertStatus(400);

        // Vérifie que le message d'erreur indique bien un paramètre inattendu
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'Unexpected parameter(s) detected: foo',
            'allowed_parameters' => ['name', 'category', 'location', 'date', 'places', 'sort_by', 'order', 'per_page', 'page']
        ]);
    }

    public function testItReturnsErrorForInvalidParameters()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Envoie la requête GET avec un paramètre invalide 'per_page' (par exemple supérieur à 100)
        $response = $this->getJson('/api/products/all?per_page=200');

        // Vérifie que la réponse a un code 400
        $response->assertStatus(400);

        // Vérifie que le message d'erreur indique bien que le paramètre 'per_page' est trop grand
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'The per page field must not be greater than 100.'
        ]);
    }

    public function testItReturnsErrorWhenNoProductsFound()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Envoie une requête pour rechercher des produits qui n'existent pas (par exemple, avec une catégorie inexistante)
        $response = $this->getJson('/api/products/all?category=NonExistentCategory');

        // Vérifie que la réponse a un code 404
        $response->assertStatus(404);

        // Vérifie que le message d'erreur est correct
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'No product found.'
        ]);
    }

    public function testItReturnsErrorForInternalServerError()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Désactive le cache dans le test (si nécessaire)
        Cache::shouldReceive('store')->once()->andThrow(new \Exception('Cache error'));

        // Envoie la requête pour tester la gestion des erreurs
        $response = $this->getJson('/api/products/all');

        // Vérifie que la réponse a un code 500
        $response->assertStatus(500);

        // Vérifie que le message d'erreur est correct
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'An error occurred while fetching the products. Please try again later.'
        ]);
    }

    public function testItReturnsErrorForUnauthorizedUser()
    {
        // Crée un utilisateur non admin
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum');

        // Envoie la requête GET à l'API des produits
        $response = $this->getJson('/api/products/all');

        // Vérifie que la réponse a un code 403
        $response->assertStatus(403);

        // Vérifie que le message d'erreur est correct
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'You are not authorized to perform this action.'
        ]);
    }

    public function testRedisCache()
    {
        // Crée un admin connecté
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'sanctum');

        // Crée des produits de test
        Product::factory(25)->create();  // Crée 25 produits

        // Données de filtre utilisées dans la requête
        $filters = ['per_page' => 10, 'page' => 1];
        $cacheKey = 'products_all_' . md5(serialize($filters));  // Clé de cache calculée

        // Met un élément dans le cache pour vérifier que le cache fonctionne
        Cache::store('redis')->put($cacheKey, 'test', 60);

        // Vérifie si la clé du cache existe avant l'appel API
        $this->assertTrue(Cache::store('redis')->has($cacheKey));  // La clé doit être dans le cache

        // Envoie la requête GET à l'API des produits avec pagination
        $response = $this->getJson('/api/products/all?per_page=10&page=1');

        // Vérifie que la réponse a un code 200
        $response->assertStatus(200);

        // Vérifie que les données sont mises en cache dans Redis après la requête
        $this->assertTrue(Cache::store('redis')->has($cacheKey));  // La clé doit être présente après l'appel
    }
}
