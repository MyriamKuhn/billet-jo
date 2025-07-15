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
use App\Enums\UserRole;
use App\Http\Controllers\Api\ProductController;
use App\Http\Requests\UpdateProductPricingRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreProductRequest;

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
        // 1) Fake the disk
        Storage::fake('images');

        // 2) Auth a fake admin
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user, 'sanctum');

        // 3) Base data from factory
        $base = Product::factory()->make()->only(['name', 'price', 'sale']);

        // 4) The uploaded file
        $file = UploadedFile::fake()->image('product.png');

        // 5) Shared details *without* image
        $details = [
            'description' => 'Une description valide',
            'date'        => now()->addDay()->toDateString(),
            'time'        => now()->addHour()->format('H:i:s'),
            'location'    => 'Paris',
            'category'    => 'Électronique',
            'places'      => 10,
        ];

        // 6) Build the payload, *including* top‐level 'image' => $file
        $payload = [
            'price'          => $base['price'],
            'sale'           => $base['sale'],
            'stock_quantity' => 50,
            'image'          => $file,
            'translations'   => [
                'en' => [
                    'name'            => $base['name'],
                    'product_details' => $details,
                ],
                'fr' => [
                    'name'            => $base['name'],
                    'product_details' => $details,
                ],
                'de' => [
                    'name'            => $base['name'],
                    'product_details' => $details,
                ],
            ],
        ];

        // 7) Mock the create() call
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $data) =>
                // the stored filename should have been injected
                isset($data['translations']['en']['product_details']['image'])
            )
            ->andReturn(new Product());
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 8) Send as multipart/form-data
        $response = $this->post('/api/products', $payload);

        // 9) Assert 201
        $response->assertStatus(201);

        // 10) Exactly one file should have been stored
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files, 'On attend exactement un fichier stocké.');

        // 11) It should end in .png
        $this->assertStringEndsWith('.png', basename($files[0]));

        Mockery::close();
    }

    public function testUpdateModifiesProductWithoutImage()
    {
        // 1) Crée et authentifie l’utilisateur admin
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Produit existant
        $product = Product::factory()->create();

        // 3) Détail commun sans image
        $details = [
            'description' => 'Une nouvelle description',
            'date'        => now()->addDay()->toDateString(),
            'time'        => now()->addHour()->format('H:i:s'),
            'location'    => 'Paris',
            'category'    => 'Électronique',
            'places'      => 10,
        ];

        // 4) Payload complet
        $payload = [
            'price'          => 123,
            'sale'           => 0.15,
            'stock_quantity' => 42,
            'translations'   => [
                'en' => ['name' => 'Nouveau nom', 'product_details' => $details],
                'fr' => ['name' => 'Nouveau nom', 'product_details' => $details],
                'de' => ['name' => 'Nouveau nom', 'product_details' => $details],
            ],
        ];

        // 5) Mock du service
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::type(Product::class), Mockery::type('array'))
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 6) **POST** JSON instead of PUT
        $response = $this->postJson("/api/products/{$product->id}", $payload);

        // 7) On attend bien 204 No Content
        $response->assertStatus(204);

        Mockery::close();
    }

    public function testUpdateReplacesImageAndModifiesProduct()
    {
        Storage::fake('images');

        // 1) Admin auth
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Create product and set old image on the base JSON column
        $product = Product::factory()->create();
        $product->update([
            'product_details' => ['image' => 'old.png'],
        ]);

        // 3) Seed translations (as before) so your service mock still works
        foreach (['en', 'fr', 'de'] as $locale) {
            $product->translations()->create([
                'locale'          => $locale,
                'name'            => "Ancien nom {$locale}",
                'product_details' => [
                    'image'       => 'old.png',
                    'description' => 'Ancienne description',
                    'date'        => now()->toDateString(),
                    'time'        => now()->format('H:i:s'),
                    'location'    => 'Lieu',
                    'category'    => 'Catégorie',
                    'places'      => 5,
                ],
            ]);
        }

        // 4) Put the old file on the fake disk
        Storage::disk('images')->put('old.png', 'dummy');

        // 5) Prepare the new upload
        $newFile = UploadedFile::fake()->image('new.png');

        // 6) Shared details *without* any image key
        $details = [
            'description' => 'Desc mise à jour',
            'date'        => now()->addDay()->toDateString(),
            'time'        => now()->addHour()->format('H:i:s'),
            'location'    => 'Lyon',
            'category'    => 'Art',
            'places'      => 20,
        ];

        // 7) Full payload with top‐level image
        $payload = [
            'price'          => 200,
            'sale'           => 0.20,
            'stock_quantity' => 5,
            'image'          => $newFile,      // ← critical
            'translations'   => [
                'en' => [
                    'name'            => 'Nom modifié EN',
                    'product_details' => $details,
                ],
                'fr' => [
                    'name'            => 'Nom modifié FR',
                    'product_details' => $details,
                ],
                'de' => [
                    'name'            => 'Nom modifié DE',
                    'product_details' => $details,
                ],
            ],
        ];

        // 8) Mock the service
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock->shouldReceive('update')
            ->once()
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 9) Call POST (multipart) to update
        $response = $this->post("/api/products/{$product->id}", $payload);

        // 10) Expect 204
        $response->assertStatus(204);

        // 11) old.png must be deleted
        Storage::disk('images')->assertMissing('old.png');

        // 12) Exactly one new image stored
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', basename($files[0]));

        Mockery::close();
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

    public function testUpdateHandlesMissingOldImageViaNullCoalescingFeature()
    {
        Storage::fake('images');

        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create();

        // Mock du service en renvoyant $product pour satisfaire le return type
        $manageMock = Mockery::mock(ProductManagementService::class);
        $manageMock->shouldReceive('update')
            ->once()
            ->withAnyArgs()
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $manageMock);

        // Payload complet pour la validation
        $payload = [
            'price'          => 25.0,
            'sale'           => 0.1,
            'stock_quantity' => 10,
            'translations'   => [
                'en' => [
                    'name'            => 'Test EN',
                    'product_details' => [
                        'places'      => 1,
                        'description' => 'Desc EN',
                        'date'        => '2025-07-01',
                        'time'        => '18:00',
                        'location'    => 'Lieu EN',
                        'category'    => 'Cat EN',
                    ],
                ],
                'fr' => [
                    'name'            => 'Test FR',
                    'product_details' => [
                        'places'      => 2,
                        'description' => 'Desc FR',
                        'date'        => '2025-07-02',
                        'time'        => '19:00',
                        'location'    => 'Lieu FR',
                        'category'    => 'Cat FR',
                    ],
                ],
                'de' => [
                    'name'            => 'Test DE',
                    'product_details' => [
                        'places'      => 3,
                        'description' => 'Desc DE',
                        'date'        => '2025-07-03',
                        'time'        => '20:00',
                        'location'    => 'Lieu DE',
                        'category'    => 'Cat DE',
                    ],
                ],
            ],
        ];

        // Fake upload pour 'en'
        $file = UploadedFile::fake()->image('new-image.jpg');
        $files = [
            'translations' => [
                'en' => [
                    'product_details' => ['image' => $file],
                ],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->call(
                'POST',
                "/api/products/{$product->id}",
                $payload,
                [],      // cookies
                $files,  // fichiers imbriqués
                ['CONTENT_TYPE' => 'multipart/form-data']
            );

        $response->assertStatus(204);
    }

    public function testAdminCanUpdateProductPricingAndGetsNoContent()
    {
        // Fake disk if your updatePricing stores anything
        // Storage::fake('images'); // only if needed by pricing

        // 1) Create an admin and a product
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $product = Product::factory()->create();

        // 2) Prepare the valid payload
        $payload = [
            'price'          => 59.99,
            'sale'           => 0.15,
            'stock_quantity' => 120,
        ];

        // 3) Stub out the listing service (required by the controller, even if unused here)
        $listingMock = Mockery::mock(ProductListingService::class);
        $this->app->instance(ProductListingService::class, $listingMock);

        // 4) Mock the ProductManagementService (the one your controller calls)
        $manageMock = Mockery::mock(ProductManagementService::class);
        $manageMock
            ->shouldReceive('updatePricing')
            ->once()
            ->withArgs(function ($passedProduct, $data) use ($product, $payload) {
                return $passedProduct->id === $product->id
                    && $data === $payload;
            })
            // return whatever your real method signature expects (often the Product, or null)
            ->andReturnNull();
        $this->app->instance(ProductManagementService::class, $manageMock);

        // 5) Hit the endpoint as an admin with PUT (not PATCH)
        $response = $this
            ->actingAs($admin, 'sanctum')
            ->patchJson("/api/products/{$product->id}/pricing", $payload);

        // 6) Assert 204 No Content
        $response->assertNoContent();
    }
}
