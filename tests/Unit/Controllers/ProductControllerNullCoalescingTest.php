<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Api\ProductController;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Services\ProductListingService;
use App\Services\ProductManagementService;

class ProductControllerNullCoalescingTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testUpdateExercisesNullCoalescingAndStoresImage()
    {
        // 1) Fake du disque 'images'
        Storage::fake('images');

        // 2) Fichier factice pour EN
        $fileEn = UploadedFile::fake()->image('new-en.jpg');

        // 3) Payload validé (sans clé 'image')
        $validated = [
            'price'          => 50.0,
            'sale'           => 0.1,
            'stock_quantity' => 5,
            'translations'   => [
                'en' => [
                    'name'            => 'Name EN',
                    'product_details' => [
                        'places'      => 1,
                        'description' => 'Desc EN',
                        'date'        => '2025-09-01',
                        'time'        => '20:00',
                        'location'    => 'Place EN',
                        'category'    => 'Cat EN',
                    ],
                ],
                'fr' => [
                    'name'            => 'Name FR',
                    'product_details' => [
                        'places'      => 2,
                        'description' => 'Desc FR',
                        'date'        => '2025-09-02',
                        'time'        => '21:00',
                        'location'    => 'Place FR',
                        'category'    => 'Cat FR',
                    ],
                ],
                'de' => [
                    'name'            => 'Name DE',
                    'product_details' => [
                        'places'      => 3,
                        'description' => 'Desc DE',
                        'date'        => '2025-09-03',
                        'time'        => '22:00',
                        'location'    => 'Place DE',
                        'category'    => 'Cat DE',
                    ],
                ],
            ],
        ];

        // 4) Stub de StoreProductRequest
        $request = new class($validated, $fileEn) extends StoreProductRequest {
            private array $data;
            private $file;
            public function __construct(array $data, $file)
            {
                parent::__construct();
                $this->data = $data;
                $this->file = $file;
            }

            // signature conforme à FormRequest::validated()
            public function validated($key = null, $default = null): array
            {
                return $this->data;
            }

            // signature conforme à Request::file()
            public function file($key = null, $default = null)
            {
                // retourne le fake seulement pour la locale 'en'
                if ($key === 'translations.en.product_details.image') {
                    return $this->file;
                }
                return $default;
            }
        };

        // 5) Vrai produit sans traductions
        $product = Product::factory()->create();

        // 6) Mock des services
        $listingMock = Mockery::mock(ProductListingService::class);
        $manageMock  = Mockery::mock(ProductManagementService::class);

        // On ne vérifie plus le nom exact, juste qu'une string existe
        $manageMock->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn($p)    => $p->id === $product->id),
                Mockery::on(fn($data) =>
                    is_string(
                        data_get($data, 'translations.en.product_details.image')
                    )
                )
            )
            ->andReturn($product);

        $this->app->instance(ProductListingService::class,    $listingMock);
        $this->app->instance(ProductManagementService::class, $manageMock);

        // 7) Exécution directe
        $controller = new ProductController($listingMock, $manageMock);
        $response   = $controller->update($request, $product);

        // 8) Assertions
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(204, $response->getStatusCode());

        // 9) Le fake file a bien été écrit sous un nom aléatoire *.jpg
        $files = Storage::disk('images')->allFiles();
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.jpg', $files[0]);
    }
}
