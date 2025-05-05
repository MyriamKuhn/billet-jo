<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Illuminate\Pagination\Paginator;

class ProductListingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProductListingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use array driver for 'redis' store to avoid real Redis dependency
        config()->set('cache.stores.redis.driver', 'array');
        Cache::store('redis')->flush();

        $this->service = new ProductListingService();
    }

    public function testHandleOnlyAvailableStock(): void
    {
        // one product with zero stock, one with stock > 0
        Product::factory()->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 5]);

        $result = $this->service->handle([], true);

        $this->assertCount(1, $result);
        $this->assertGreaterThan(0, $result->first()->stock_quantity);
    }

    public function testHandleAllStockWhenFlagFalse(): void
    {
        Product::factory()->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 3]);

        $result = $this->service->handle([], false);

        $this->assertCount(2, $result);
    }

    public function testFilterByName(): void
    {
        Product::factory()->create(['name' => 'TestOne', 'stock_quantity' => 1]);
        Product::factory()->create(['name' => 'Other', 'stock_quantity' => 1]);

        $result = $this->service->handle(['name' => 'Test'], true);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Test', $result->first()->name);
    }

    public function testFilterByCategoryAndLocationAndDateAndPlaces(): void
    {
        $matching = Product::factory()->create([
            'stock_quantity' => 1,
            'product_details' => [
                'category' => 'CatA',
                'location' => 'LocX',
                'date'     => '2025-06-01',
                'places'   => 10,
            ],
        ]);
        Product::factory()->create([
            'stock_quantity' => 1,
            'product_details' => [
                'category' => 'CatB',
                'location' => 'LocY',
                'date'     => '2025-07-01',
                'places'   => 5,
            ],
        ]);

        $result = $this->service->handle([
            'category' => 'CatA',
            'location' => 'LocX',
            'date'     => '2025-06-01',
            'places'   => 8,
        ], true);

        $this->assertCount(1, $result);
        $this->assertEquals($matching->id, $result->first()->id);
    }

    public function testSortingByNameAndPriceAndJsonDate(): void
    {
        // Create items with same stock
        $a = Product::factory()->create([
            'name'           => 'Alpha',
            'price'          => 200,
            'stock_quantity' => 1,
            'product_details' => ['date' => '2025-01-01'],
        ]);
        $b = Product::factory()->create([
            'name'           => 'Beta',
            'price'          => 100,
            'stock_quantity' => 1,
            'product_details' => ['date' => '2025-02-01'],
        ]);

        // Sort by name desc
        $resNameDesc = $this->service->handle(['sort_by' => 'name', 'order' => 'desc'], true);
        $this->assertEquals('Beta', $resNameDesc->first()->name);

        // Sort by price asc
        $resPriceAsc = $this->service->handle(['sort_by' => 'price', 'order' => 'asc'], true);
        $this->assertEquals(100, $resPriceAsc->first()->price);

        // Sort by JSON date desc
        $resDateDesc = $this->service->handle(['sort_by' => 'product_details->date', 'order' => 'desc'], true);
        $this->assertEquals('2025-02-01', $resDateDesc->first()->product_details['date']);
    }

    public function testPaginationParameters(): void
    {
        // Création de 30 produits
        Product::factory()->count(30)->create(['stock_quantity' => 1]);

        // Page 1, include 'page' dans les filtres pour variation de clé cache
        Paginator::currentPageResolver(fn() => 1);
        $page1 = $this->service->handle(['per_page' => 10, 'page' => 1], true);

        // Page 2, nouvelle clé cache (page change)
        Paginator::currentPageResolver(fn() => 2);
        $page2 = $this->service->handle(['per_page' => 10, 'page' => 2], true);

        $this->assertCount(10, $page1);
        $this->assertEquals(3, $page1->lastPage());
        $this->assertNotEquals($page1->first()->id, $page2->first()->id);
    }

    public function testCachingStoresResult(): void
    {
        Cache::store('redis')->flush();
        $filters = ['name' => 'X'];

        // First call should write cache
        $first = $this->service->handle($filters, true);
        // Create extra product that should not appear due to cache
        Product::factory()->create(['name' => 'X', 'stock_quantity' => 1]);

        $second = $this->service->handle($filters, true);
        $this->assertEquals($first->total(), $second->total());
    }
}
