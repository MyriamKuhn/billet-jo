<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Services\ProductManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductManagementService();
    }

    public function testCreateStoresProductWithAllFields(): void
    {
        $baseDetails = [
            'description' => 'A test product description',
            'date'        => '2025-05-10',
            'time'        => '12:00:00',
            'location'    => 'Paris',
            'category'    => 'TestCat',
            'places'      => 5,
            'image'       => 'https://example.com/image.jpg',
        ];

        // **EXTRAIT les valeurs de price/sale/stock au niveau racine**
        $payload = [
            'price'          => 99.99,
            'sale'           => 79.99,
            'stock_quantity' => 10,

            'translations' => [
                'en' => [
                    'name'            => 'Test Product',
                    'product_details' => $baseDetails,
                ],
                'fr' => [
                    'name'            => 'Test Product FR',
                    'product_details' => $baseDetails,
                ],
                'de' => [
                    'name'            => 'Test Product DE',
                    'product_details' => $baseDetails,
                ],
            ],
        ];

        $product = $this->service->create($payload);

        $this->assertInstanceOf(Product::class, $product);

        $this->assertDatabaseHas('products', [
            'id'             => $product->id,
            'name'           => 'Test Product',
            'price'          => 99.99,
            'sale'           => 79.99,
            'stock_quantity' => 10,
        ]);

        $this->assertEquals(
            $baseDetails,
            $product->product_details
        );
    }

    public function testCreateWithoutSale(): void
    {
        $baseDetails = [
            'description' => 'No sale',
            'date'        => '2025-06-01',
            'time'        => '08:30:00',
            'location'    => 'Lyon',
            'category'    => 'NoSaleCat',
            'places'      => 2,
            'image'       => 'https://example.com/no-sale.jpg',
        ];

        $payload = [
            // ← on met PRICE et STOCK_QUANTITY au bon niveau
            'price'          => 50.00,
            'stock_quantity' => 3,
            // 'sale' omis → la méthode prendra null par défaut

            'translations' => [
                'en' => [
                    'name'            => 'No Sale Product',
                    'product_details' => $baseDetails,
                ],
                'fr' => [
                    'name'            => 'No Sale Product FR',
                    'product_details' => $baseDetails,
                ],
                'de' => [
                    'name'            => 'No Sale Product DE',
                    'product_details' => $baseDetails,
                ],
            ],
        ];

        $product = $this->service->create($payload);

        // Le champ 'sale' doit être null par défaut
        $this->assertNull($product->sale);

        // Vérification en base
        $this->assertDatabaseHas('products', [
            'id'             => $product->id,
            'name'           => 'No Sale Product',
            'price'          => 50.00,
            'stock_quantity' => 3,
        ]);

        // Le product_details doit correspondre
        $this->assertEquals(
            $baseDetails,
            $product->product_details
        );
    }

    public function testUpdateModifiesExistingProduct(): void
    {
        // Création d’un produit d’origine
        $product = Product::factory()->create([
            'name'           => 'Old Name',
            'price'          => 20.00,
            'sale'           => 15.00,
            'stock_quantity' => 5,
            'product_details'=> [
                'description' => 'Old description',
                'date'        => '2025-07-01',
                'time'        => '09:00:00',
                'location'    => 'Nice',
                'category'    => 'OldCat',
                'places'      => 4,
                'image'       => 'https://example.com/old.jpg',
            ],
        ]);

        // Nouvelles données
        $newDetails = [
            'description' => 'New description',
            'date'        => '2025-08-01',
            'time'        => '10:00:00',
            'location'    => 'Marseille',
            'category'    => 'NewCat',
            'places'      => 6,
            'image'       => 'https://example.com/new.jpg',
        ];

        // **PRICE, SALE et STOCK_QUANTITY doivent être au niveau racine**
        $payload = [
            'price'          => 25.00,
            'sale'           => null,   // on veut enlever la promo
            'stock_quantity' => 8,

            'translations' => [
                'en' => [
                    'name'            => 'Updated Name',
                    'product_details' => $newDetails,
                ],
                'fr' => [
                    'name'            => 'Nom modifié FR',
                    'product_details' => $newDetails,
                ],
                'de' => [
                    'name'            => 'Geänderter Name DE',
                    'product_details' => $newDetails,
                ],
            ],
        ];

        // Appel de la méthode update du service
        $updated = $this->service->update($product, $payload);

        // Vérifications sur l’objet retourné
        $this->assertInstanceOf(Product::class, $updated);
        $this->assertEquals('Updated Name',        $updated->name);
        $this->assertEquals(25.00,                 $updated->price);
        $this->assertNull($updated->sale);
        $this->assertEquals(8,                     $updated->stock_quantity);
        $this->assertEquals($newDetails,           $updated->product_details);

        // Et bien entendu la base de données a été mise à jour
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'name'  => 'Updated Name',
            'price' => 25.00,
        ]);
    }

    public function testUpdatePricingUpdatesPriceSaleAndStock(): void
    {
        // 1) Crée un produit avec des valeurs initiales
        $product = Product::factory()->create([
            'price'          => 100.00,
            'sale'           => 0.00,
            'stock_quantity' => 10,
        ]);

        // 2) Nouveau jeu de données à appliquer
        $data = [
            'price'          => 123.45,
            'sale'           => 0.25,
            'stock_quantity' => 20,
        ];

        // 3) Appelle la méthode
        $this->service->updatePricing($product, $data);

        // 4) Rafraîchit le modèle depuis la base
        $product->refresh();

        // 5) Vérifie que les champs ont bien été mis à jour
        $this->assertEquals(123.45, $product->price);
        $this->assertEquals(0.25, $product->sale);
        $this->assertEquals(20, $product->stock_quantity);
    }

    public function testUpdatePricingDoesNotModifyOtherAttributes(): void
    {
        // 1) Crée un produit avec un nom personnalisé
        $product = Product::factory()->create([
            'name'           => 'Original Name',
            'price'          => 50.00,
            'sale'           => 0.10,
            'stock_quantity' => 5,
        ]);

        // 2) Ne met à jour que le pricing et le stock
        $data = [
            'price'          => 75.00,
            'sale'           => 0.15,
            'stock_quantity' => 8,
        ];

        $this->service->updatePricing($product, $data);
        $product->refresh();

        // 3) Vérifie que le nom est resté inchangé
        $this->assertEquals('Original Name', $product->name);

        // Et que les champs pricing/stock ont bien changé
        $this->assertEquals(75.00, $product->price);
        $this->assertEquals(0.15, $product->sale);
        $this->assertEquals(8, $product->stock_quantity);
    }
}
