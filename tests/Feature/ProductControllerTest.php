<?php

namespace Tests\Feature;

use App\Services\ProductListingService;
use App\Services\ProductManagementService;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use Mockery;
use App\Models\User;
use Laravel\Sanctum\Sanctum;


class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testIndexReturnsProducts()
    {
        // Préparer des produits factices
        $products = Product::factory()->count(3)->make();
        $paginator = new LengthAwarePaginator($products, 3, 15, 1);

        // Mock du service de listing
        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $listingServiceMock */
        $listingServiceMock = Mockery::mock(ProductListingService::class);
        $listingServiceMock
            ->shouldReceive('handle')
            ->with([], true)
            ->once()
            ->andReturn($paginator);
        $this->app->instance(ProductListingService::class, $listingServiceMock);

        // Appel de l'endpoint
        $response = $this->getJson('/api/products');

        // Assertions
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        ['id', 'name', 'price']
                    ],
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ]);
    }

    public function testIndexReturns404WhenEmpty()
    {
        $paginator = new LengthAwarePaginator([], 0, 15, 1);

        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $listingServiceMock */
        $listingServiceMock = Mockery::mock(ProductListingService::class);
        $listingServiceMock
            ->shouldReceive('handle')
            ->with([], true)
            ->once()
            ->andReturn($paginator);
        $this->app->instance(ProductListingService::class, $listingServiceMock);

        $this->getJson('/api/products')
            ->assertStatus(404);
    }

    public function testShowReturnsProduct()
    {
        $product = Product::factory()->create();
        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                ->assertJsonFragment(['id' => $product->id]);
    }

    public function testStoreCreatesProduct()
    {
        // 1) Crée et auth l'utilisateur
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'sanctum');

        // 2) Construis un payload valide
        $base = Product::factory()->make()->only([
            'name',
            'price',
            // ajoute ici tout ce que ta factory cover déjà et qui est dans tes règles
        ]);

        $data = array_merge($base, [
            'stock_quantity'   => 50,                       // entier requis
            'product_details'  => [
                'description' => 'Une description valide',  // string
                'date'        => now()->addDay()->toDateString(),
                'time'        => now()->addHour()->format('H:i:s'), // ex. "14:30:00"
                'location'    => 'Paris',
                'category'    => 'Électronique',
                'places'      => 10,
                'image'       => 'https://example.com/img.jpg',
            ],
        ]);

        // 3) Mock du ProductManagementService
        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $managementServiceMock */
        $managementServiceMock = Mockery::mock(ProductManagementService::class);
        $managementServiceMock
            ->shouldReceive('create')
            ->with(Mockery::subset($data))
            ->once()
            ->andReturn(new Product());
        $this->app->instance(ProductManagementService::class, $managementServiceMock);

        // 4) L’appel authentifié vers l’endpoint
        $response = $this->postJson('/api/products', $data);

        // 5) Assert 201
        $response->assertStatus(201);
    }

    public function testUpdateModifiesProduct()
    {
        // 1) Crée et auth l’utilisateur admin
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Produit existant
        $product = Product::factory()->create();

        // 3) Payload complet (tous les champs requis)
        $updateData = [
            'name'           => 'Nouveau nom',
            'price'          => 123,
            'stock_quantity' => 42,
            'product_details' => [
                'description' => 'Une nouvelle description',
                'date'        => now()->addDay()->toDateString(),
                'time'        => now()->addHour()->format('H:i:s'),
                'location'    => 'Paris',
                'category'    => 'Électronique',
                'places'      => 10,
                'image'       => 'https://example.com/img.jpg',
            ],
        ];

        // 4) Mock du service avec un type matcher pour le Product
        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $managementServiceMock */
        $managementServiceMock = Mockery::mock(ProductManagementService::class);
        $managementServiceMock
            ->shouldReceive('update')
            ->with(
                Mockery::type(Product::class),
                Mockery::subset($updateData)
            )
            ->once()
            ->andReturn(new Product());
        $this->app->instance(ProductManagementService::class, $managementServiceMock);

        // 5) Requête authentifiée
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // 6) On attend bien 204
        $response->assertStatus(204);
    }

    public function testGetProductsReturnsProducts()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        $products = Product::factory()->count(2)->make();
        $paginator = new LengthAwarePaginator($products, 2, 15, 1);

        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $listingServiceMock */
        $listingServiceMock = Mockery::mock(ProductListingService::class);
        $listingServiceMock
            ->shouldReceive('handle')
            ->with([], false)
            ->once()
            ->andReturn($paginator);
        $this->app->instance(ProductListingService::class, $listingServiceMock);

        $response = $this->getJson('/api/products/all');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [ ['id', 'name', 'price'] ],
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ]);
    }

    public function testGetProductsReturns404WhenEmpty()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        $paginator = new LengthAwarePaginator([], 0, 15, 1);

        /** @var \Mockery\MockInterface&\App\Services\ProductListingService $listingServiceMock */
        $listingServiceMock = Mockery::mock(ProductListingService::class);
        $listingServiceMock
            ->shouldReceive('handle')
            ->with([], false)
            ->once()
            ->andReturn($paginator);
        $this->app->instance(ProductListingService::class, $listingServiceMock);

        $this->getJson('/api/products/all')
            ->assertStatus(404);
    }

    public function testGetProductsUnauthorizedUser(): void
    {
        $user = User::factory()->create(['role'=>'user']);
        Sanctum::actingAs($user,['*']);

        $this->getJson('/api/products/all')->assertStatus(403);
    }
}
