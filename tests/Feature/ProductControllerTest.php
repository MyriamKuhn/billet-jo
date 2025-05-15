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

        // 3) On prépare les données de base via la factory
        $base = Product::factory()->make()->only(['name', 'price', 'sale']);

        // 4) On génère un faux fichier image
        $file = UploadedFile::fake()->image('product.png');

        // 5) On prépare le détail commun à chaque traduction
        $details = [
            'description' => 'Une description valide',
            'date'        => now()->addDay()->toDateString(),
            'time'        => now()->addHour()->format('H:i:s'),
            'location'    => 'Paris',
            'category'    => 'Électronique',
            'places'      => 10,
            'image'       => $file,
        ];

        // 6) On construit le payload complet avec translations pour en, fr et de
        $payload = [
            'price'          => $base['price'],
            'sale'           => $base['sale'],
            'stock_quantity' => 50,
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

        // 7) On mocke le service pour qu'il retourne un Product
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('create')
            ->once()
            ->withArgs(function(array $data) {
                // On peut vérifier que les clés principales sont bien présentes
                return isset($data['translations']['en']['product_details'])
                    && isset($data['translations']['fr']['product_details'])
                    && isset($data['translations']['de']['product_details']);
            })
            ->andReturn(new Product());
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 8) On envoie la requête (Laravel détecte l'UploadedFile et bascule en multipart)
        $response = $this->post('/api/products', $payload);

        // 9) On vérifie le statut HTTP
        $response->assertStatus(201);

        // 10) On vérifie qu'un seul fichier a bien été stocké
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files, 'On attend exactement un fichier stocké.');

        // 11) On vérifie que ce fichier est bien un .png
        $filename = basename($files[0]);
        $this->assertStringEndsWith('.png', $filename);

        // 12) On s'assure que Mockery ne laisse pas de mocks ouverts
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
            // pas d'« image » : on conserve l’ancienne
        ];

        // 4) Payload complet avec sale et translations
        $payload = [
            'price'          => 123,
            'sale'           => 0.15,
            'stock_quantity' => 42,
            'translations'   => [
                'en' => [
                    'name'            => 'Nouveau nom',
                    'product_details' => $details,
                ],
                'fr' => [
                    'name'            => 'Nouveau nom',
                    'product_details' => $details,
                ],
                'de' => [
                    'name'            => 'Nouveau nom',
                    'product_details' => $details,
                ],
            ],
        ];

        // 5) Mock du service : on s’assure juste qu’il reçoit un Product et un array
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('update')
            ->once()
            ->with(
                Mockery::type(Product::class),
                Mockery::type('array')           // on n’impose plus le subset exact
            )
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 6) Requête PUT en JSON
        $response = $this->putJson("/api/products/{$product->id}", $payload);

        // 7) On attend bien 204 No Content
        $response->assertStatus(204);

        Mockery::close();
    }

    public function testUpdateReplacesImageAndModifiesProduct()
    {
        Storage::fake('images');

        // 1) Admin authentifié
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user, ['*']);

        // 2) Produit existant (sans traduction pour l'instant)
        $product = Product::factory()->create();

        // 3) On ajoute manuellement les traductions pour chaque locale,
        //    avec l'ancienne image 'old.png'
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

        // 4) On crée l'ancienne image sur le disque fake
        Storage::disk('images')->put('old.png', 'dummy');

        // 5) Nouveau fichier à uploader
        $newFile = UploadedFile::fake()->image('new.png');

        // 6) Détail commun avec la nouvelle image
        $details = [
            'description' => 'Desc mise à jour',
            'date'        => now()->addDay()->toDateString(),
            'time'        => now()->addHour()->format('H:i:s'),
            'location'    => 'Lyon',
            'category'    => 'Art',
            'places'      => 20,
            'image'       => $newFile,
        ];

        // 7) Payload complet avec translations (on modifie aussi le nom)
        $updateData = [
            'price'          => 200,
            'sale'           => 0.20,
            'stock_quantity' => 5,
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

        // 8) Mock du service (on ne vérifie pas le contenu exact ici)
        $serviceMock = Mockery::mock(ProductManagementService::class);
        $serviceMock
            ->shouldReceive('update')
            ->once()
            ->andReturn($product);
        $this->app->instance(ProductManagementService::class, $serviceMock);

        // 9) Requête multipart (Laravel détecte UploadedFile)
        $response = $this->put("/api/products/{$product->id}", $updateData);

        // 10) Vérifie le statut 204
        $response->assertStatus(204);

        // 11) L'ancienne image doit avoir été supprimée
        Storage::disk('images')->assertMissing('old.png');

        // 12) Une seule nouvelle image doit exister
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
}
