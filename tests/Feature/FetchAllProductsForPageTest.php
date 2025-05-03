<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use App\Services\ProductFilterService;
use App\Models\Product;

class FetchAllProductsForPageTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturnsProductsWithPaginationAndValidation()
    {
        // Crée des produits de test
        Product::factory(25)->create();  // Crée 25 produits

        // Envoie la requête GET à l'API des produits avec pagination
        $response = $this->getJson('/api/products?per_page=10&page=1');

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
        // Envoie la requête GET avec un paramètre inattendu 'foo'
        $response = $this->getJson('/api/products?foo=bar');

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
        // Envoie la requête GET avec un paramètre invalide 'per_page' (par exemple supérieur à 100)
        $response = $this->getJson('/api/products?per_page=200');

        // Vérifie que la réponse a un code 400
        $response->assertStatus(400);

        // Vérifie que le message d'erreur indique bien que le paramètre 'per_page' est trop grand
        $response->assertJsonFragment([
            'per_page' => [
                'The per page field must not be greater than 100.'
            ]
        ]);
    }

    public function testItReturnsErrorWhenNoProductsFound()
    {
        // Envoie une requête pour rechercher des produits qui n'existent pas (par exemple, avec une catégorie inexistante)
        $response = $this->getJson('/api/products?category=NonExistentCategory');

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
        // Désactive le cache dans le test (si nécessaire)
        Cache::shouldReceive('store')->once()->andThrow(new \Exception('Cache error'));

        // Envoie la requête pour tester la gestion des erreurs
        $response = $this->getJson('/api/products');

        // Vérifie que la réponse a un code 500
        $response->assertStatus(500);

        // Vérifie que le message d'erreur est correct
        $response->assertJsonFragment([
            'status' => 'error',
            'error' => 'An error occurred while fetching the products. Please try again later.'
        ]);
    }

    public function testItFiltersProductsByName()
    {
        // Créer un produit de test dans la base de données
        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100.00,
            'stock_quantity' => 10,
            'product_details' => json_encode([
                'places' => 100,
                'description' => 'Description of the product.',
                'date' => '2024-12-12',
                'time' => '10:00',
                'location' => 'Stade de France',
                'category' => 'Sports',
                'image' => 'https://example.com/image.jpg',
            ]),
        ]);

        // Applique le filtre
        $filters = ['name' => 'Test Product'];
        $query = (new ProductFilterService())->buildQuery($filters);

        // Vérifie que le produit filtré existe
        $this->assertTrue($query->exists());
    }

    public function testItAppliesLocationFilter()
    {
        $filters = ['location' => 'Stade de France'];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête contient le filtre sur location, en tenant compte de la fonction JSON
        $this->assertStringContainsString("json_unquote(json_extract(`product_details`, '$.\"location\"')) LIKE", $sql);
    }

    public function testItAppliesDateFilter()
    {
        $filters = ['date' => '2024-12-12'];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête contient le bon filtre sur la date
        $this->assertStringContainsString("json_unquote(json_extract(`product_details`, '$.\"date\"')) =", $sql);
    }

    public function testItAppliesPlacesFilter()
    {
        $filters = ['places' => 50];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête contient le bon filtre sur places
        $this->assertStringContainsString("json_unquote(json_extract(`product_details`, '$.\"places\"')) >=", $sql);
    }

    public function testItAppliesSortByDate()
    {
        $filters = ['sort_by' => 'product_details->date', 'order' => 'desc'];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête contient un tri par product_details->date
        $this->assertStringContainsStringIgnoringCase("ORDER BY JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.date')) desc", $sql);
    }

    public function testItDoesNotApplyFiltersWhenNoFiltersAreGiven()
    {
        $filters = [];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête ne contient pas de filtres supplémentaires
        $this->assertStringNotContainsString('WHERE', $sql);
    }

    public function testItAppliesCategoryFilter()
    {
        $filters = ['category' => 'Sports'];

        $query = (new ProductFilterService())->buildQuery($filters);

        $sql = $query->toSql(); // Récupère la requête SQL générée

        // Vérifie que la requête contient le filtre correct pour product_details->category
        $this->assertStringContainsString('json_unquote(json_extract(`product_details`, \'$."category"\')) = ?', strtolower($sql)); // Vérification du filtre sur la catégorie
    }
}
