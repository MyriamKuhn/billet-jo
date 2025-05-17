<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use App\Http\Controllers\Api\TicketController;
use App\Http\Requests\SalesStatsRequest;
use App\Services\TicketService;
use App\Services\ProductListingService;
use App\Services\ProductManagementService;
use App\Models\Product;

class TicketControllerSalesStatsTest extends TestCase
{
    use RefreshDatabase, MockeryPHPUnitIntegration;

    public function testSalesStatsReturnsPaginatedJson()
    {
        // 1) Stub du request pour renvoyer des filtres arbitraires
        $filters = ['from' => '2025-01-01', 'to' => '2025-12-31'];
        $request = Mockery::mock(SalesStatsRequest::class);
        $request
            ->shouldReceive('validatedFilters')
            ->once()
            ->andReturn($filters);

        // 2) On crée deux vrais produits
        $prod1 = Product::factory()->create();
        $prod2 = Product::factory()->create();

        // 3) Prépare les items : chaque stdClass a product_id, product et sales_count
        $items = collect([
            (object)[
                'product_id'  => $prod1->id,
                'product'     => $prod1,
                'sales_count' => 100,
            ],
            (object)[
                'product_id'  => $prod2->id,
                'product'     => $prod2,
                'sales_count' => 200,
            ],
        ]);

        // 4) Monte un paginator
        $paginator = new LengthAwarePaginator(
            $items->all(),       // les items
            $items->count(),     // total
            15,                  // par page
            1                    // page courante
        );
        $paginator->setCollection($items);

        // 5) Mock du TicketService
        $ticketService = Mockery::mock(TicketService::class);
        $ticketService
            ->shouldReceive('getSalesStats')
            ->once()
            ->with($filters)
            ->andReturn($paginator);

        // 6) Bind de tous les services du constructeur
        $this->app->instance(TicketService::class,            $ticketService);
        $this->app->instance(ProductListingService::class,    Mockery::mock(ProductListingService::class));
        $this->app->instance(ProductManagementService::class, Mockery::mock(ProductManagementService::class));

        // 7) Résout et appelle la méthode salesStats()
        /** @var TicketController $controller */
        $controller = $this->app->make(TicketController::class);
        $response   = $controller->salesStats($request);

        // 8) Assertions
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $json = $response->getData(true);

        // – on a bien 2 entrées dans data
        $this->assertCount(2, $json['data']);

        // – chaque entrée contient product_id et sales_count
        $this->assertSame($prod1->id, $json['data'][0]['product_id']);
        $this->assertSame(100,       $json['data'][0]['sales_count']);
        $this->assertSame($prod2->id, $json['data'][1]['product_id']);
        $this->assertSame(200,       $json['data'][1]['sales_count']);

        // – la section meta reflète le paginator
        $this->assertSame(1,  $json['meta']['current_page']);
        $this->assertSame(1,  $json['meta']['last_page']);
        $this->assertSame(15, $json['meta']['per_page']);
        $this->assertSame(2,  $json['meta']['total']);
    }
}
