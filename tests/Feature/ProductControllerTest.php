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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\ProductService;


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
        // 1) On fake le disque 'images'
        Storage::fake('images');

        // 2) On crée et authentifie un admin
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'sanctum');

        // 3) On prépare le payload
        $base = Product::factory()->make()->only(['name', 'price', 'sale']);

        $file = UploadedFile::fake()->image('product.png');

        $payload = array_merge($base, [
            'stock_quantity'  => 50,
            'product_details' => [
                'description' => 'Une description valide',
                'date'        => now()->addDay()->toDateString(),
                'time'        => now()->addHour()->format('H:i:s'),
                'location'    => 'Paris',
                'category'    => 'Électronique',
                'places'      => 10,
                'image'       => $file,
            ],
        ]);

        // 4) On mocke le service pour qu'il retourne un Product
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('create')
            ->once()
            ->andReturn(new Product());
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 5) On envoie la requête (Laravel détecte l'UploadedFile et bascule en multipart)
        $response = $this->post('/api/products', $payload);

        // 6) On vérifie le statut HTTP
        $response->assertStatus(201);

        // 7) On vérifie qu'un seul fichier a bien été stocké
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files, 'On attend exactement un fichier stocké.');

        // 8) On vérifie que ce fichier est bien un .png
        $filename = basename($files[0]);
        $this->assertStringEndsWith('.png', $filename);

        // 9) Enfin, on s'assure que le service a bien été appelé
        Mockery::close();
    }

    public function testUpdateModifiesProductWithoutImage()
    {
        // 1) Crée et auth l’utilisateur admin
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Produit existant
        $product = Product::factory()->create();

        // 3) Payload complet (tous les champs sauf 'image')
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
                // plus d'image ici : on garde l'ancienne
            ],
        ];

        // 4) Mock du service (vérifie qu'on reçoit bien un Product et $updateData est subset)
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('update')
            ->with(
                Mockery::type(Product::class),
                Mockery::subset($updateData)
            )
            ->once()
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 5) Requête authentifiée en JSON
        $response = $this->putJson("/api/products/{$product->id}", $updateData);

        // 6) On attend bien 204 No Content
        $response->assertStatus(204);
    }

    public function testUpdateReplacesImageAndModifiesProduct()
    {
        Storage::fake('images');

        // 1) Admin authentifié
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Produit existant avec une image initiale
        $product = Product::factory()->create([
            'product_details' => [
                'image' => 'old.png',
                // autres détails…
            ],
        ]);
        // On crée l'ancienne image sur le disque fake
        Storage::disk('images')->put('old.png', '');

        // 3) Nouveau fichier à uploader
        $newFile = UploadedFile::fake()->image('new.png');

        $updateData = [
            'name'           => 'Nom modifié',
            'price'          => 200,
            'stock_quantity' => 5,
            'product_details' => [
                'description' => 'Desc mise à jour',
                'date'        => now()->addDay()->toDateString(),
                'time'        => now()->addHour()->format('H:i:s'),
                'location'    => 'Lyon',
                'category'    => 'Art',
                'places'      => 20,
                'image'       => $newFile,
            ],
        ];

        // 4) Mock du service (on ne vérifie pas l'image ici)
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('update')
            ->once()
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 5) Requête multipart
        $response = $this->put(
            "/api/products/{$product->id}",
            $updateData
        );

        $response->assertStatus(204);

        // 6) L'ancienne doit avoir été supprimée, la nouvelle stockée
        Storage::disk('images')->assertMissing('old.png');
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', basename($files[0]));
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
