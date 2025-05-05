<?php

namespace Tests\Unit;

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
        $data = [
            'name'           => 'Test Product',
            'price'          => 99.99,
            'sale'           => 79.99,
            'stock_quantity' => 10,
            'product_details'=> [
                'description' => 'A test product description',
                'date'        => '2025-05-10',
                'time'        => '12:00:00',
                'location'    => 'Paris',
                'category'    => 'TestCat',
                'places'      => 5,
                'image'       => 'https://example.com/image.jpg',
            ],
        ];

        $product = $this->service->create($data);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertDatabaseHas('products', [
            'id'             => $product->id,
            'name'           => 'Test Product',
            'price'          => 99.99,
            'sale'           => 79.99,
            'stock_quantity' => 10,
        ]);

        $this->assertEquals(
            $data['product_details'],
            $product->product_details
        );
    }

    public function testCreateWithoutSale(): void
    {
        $data = [
            'name'           => 'No Sale Product',
            'price'          => 50.00,
            'stock_quantity' => 3,
            'product_details'=> [
                'description' => 'No sale',
                'date'        => '2025-06-01',
                'time'        => '08:30:00',
                'location'    => 'Lyon',
                'category'    => 'NoSaleCat',
                'places'      => 2,
                'image'       => 'https://example.com/no-sale.jpg',
            ],
        ];

        $product = $this->service->create($data);

        $this->assertNull($product->sale);
        $this->assertDatabaseHas('products', ['name' => 'No Sale Product']);
    }

    public function testUpdateModifiesExistingProduct(): void
    {
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

        $updateData = [
            'name'           => 'Updated Name',
            'price'          => 25.00,
            'sale'           => null,
            'stock_quantity' => 8,
            'product_details'=> [
                'description' => 'New description',
                'date'        => '2025-08-01',
                'time'        => '10:00:00',
                'location'    => 'Marseille',
                'category'    => 'NewCat',
                'places'      => 6,
                'image'       => 'https://example.com/new.jpg',
            ],
        ];

        $updated = $this->service->update($product, $updateData);

        $this->assertInstanceOf(Product::class, $updated);
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(25.00, $updated->price);
        $this->assertNull($updated->sale);
        $this->assertEquals(8, $updated->stock_quantity);
        $this->assertEquals($updateData['product_details'], $updated->product_details);

        $this->assertDatabaseHas('products', ['name' => 'Updated Name', 'price' => 25.00]);
    }
}
