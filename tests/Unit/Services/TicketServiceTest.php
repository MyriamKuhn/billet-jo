<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Ticket;
use App\Enums\TicketStatus;
use App\Enums\PaymentStatus;
use App\Events\InvoiceRequested;
use App\Events\PaymentSucceeded;
use App\Mail\TicketsGenerated;
use App\Services\TicketService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TicketService::class);
    }

    public function testGetFilteredTickets(): void
    {
        User::factory()->count(2)->create();
        $tickets = Ticket::factory()->count(5)->create();
        $filters = ['per_page' => 2];
        $paginator = $this->service->getFilteredTickets($filters);
        $this->assertEquals(2, $paginator->count());
        $this->assertEquals(5, $paginator->total());
    }

    public function testGetUserTicketsSearchByName(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        // Deux tickets pour $user (Alpha et Beta) et un ticket “Alpha” pour $other
        Ticket::factory()->create([
            'user_id'          => $user->id,
            'product_snapshot' => ['product_name' => 'Alpha'],
        ]);
        Ticket::factory()->create([
            'user_id'          => $user->id,
            'product_snapshot' => ['product_name' => 'Beta'],
        ]);
        Ticket::factory()->create([
            'user_id'          => $other->id,
            'product_snapshot' => ['product_name' => 'Alpha'],
        ]);

        $filters   = ['q' => 'Alpha', 'per_page' => 10];
        $paginator = $this->service->getUserTickets($user->id, $filters);

        // On reçoit bien les deux tickets de $user (Alpha et Beta)
        $this->assertCount(2, $paginator->items());

        $names = array_map(fn($ticket) => $ticket->product_snapshot['product_name'], $paginator->items());
        $this->assertContains('Alpha', $names);
        $this->assertContains('Beta',  $names);
    }

    public function testChangeStatusUpdatesTimestamps(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Issued->value]);

        // Passe en 'used'
        $this->service->changeStatus($ticket->id, TicketStatus::Used->value);

        // 1) Check database
        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatus::Used->value,
        ]);

        $refreshed = Ticket::find($ticket->id);
        $this->assertNotNull($refreshed->used_at, 'used_at should not be null after marking as used');

        // Passe en 'refunded'
        $this->service->changeStatus($ticket->id, TicketStatus::Refunded->value);

        // 2) Check database again
        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatus::Refunded->value,
        ]);

        $refreshed = $refreshed->fresh();
        $this->assertNotNull($refreshed->refunded_at, 'refunded_at should not be null after marking as refunded');
    }

    public function testCreateFreeTicketsGeneratesPaymentInvoiceAndTickets(): void
    {
        Storage::fake('invoices');
        Event::fake();
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price'           => 100,
            'product_details' => [
                'category' => 'cat',
                'places'   => 5,        // <— ajouté ici
            ],
        ]);

        // On n’oublie pas le locale
        $this->service->createFreeTickets($user->id, $product->id, 2, 'en');

        // Vérification du paiement
        $payment = Payment::where('user_id', $user->id)->first();
        $this->assertEquals(0.0, (float)$payment->amount);
        $this->assertEquals(PaymentStatus::Paid->value, $payment->status->value);
        $this->assertNotNull($payment->invoice_link);

        // Événements déclenchés
        Event::assertDispatched(InvoiceRequested::class, fn($e) => $e->payment->id === $payment->id);
        Event::assertDispatched(PaymentSucceeded::class, fn($e) => $e->payment->id === $payment->id);
    }

    public function testGenerateForPaymentUuidStoresQrAndPdfAndCreatesTickets(): void
    {
        Mail::fake();
        Storage::fake('qrcodes');
        Storage::fake('tickets');
        Event::fake();

        $user = User::factory()->create();

        $nowDate  = now()->toDateString();
        $nowTime  = now()->format('H:i:s');
        $location = 'TestLocation';
        $places   = 5;

        $product = Product::factory()->create([
            'product_details' => [
                'category' => 'cat',
                'places'   => $places,
                'date'     => $nowDate,
                'time'     => $nowTime,
                'location' => $location,
            ],
            'price' => 50,
            'sale'  => 0.1,
        ]);

        $payment = Payment::factory()->create([
            'user_id'       => $user->id,
            'cart_snapshot' => [
                'items' => [[
                    'product_id'       => $product->id,
                    'quantity'         => 1,
                    'product_name'     => $product->name,
                    'ticket_type'      => 'cat',
                    'unit_price'       => 50,
                    'discount_rate'    => 0.1,
                    'discounted_price' => 45.0,
                    'date'             => $nowDate,
                    'time'             => $nowTime,
                    'location'         => $location,
                    'ticket_places'    => $places,     // ← ajouté
                ]],
            ],
        ]);

        // on n’oublie pas le locale
        $this->service->generateForPaymentUuid($payment->uuid, 'en');

        $this->assertDatabaseCount('tickets', 1);
        $ticket = Ticket::first();

        Storage::disk('qrcodes')->assertExists($ticket->qr_filename);
        Storage::disk('tickets')->assertExists($ticket->pdf_filename);

        Mail::assertSent(TicketsGenerated::class, fn($mail) => $mail->hasTo($user->email));
    }

    public function testItFiltersByStatusAndLogsTheSql(): void
    {
        $user    = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();

        // 2 issued
        Ticket::factory()->count(2)->create([
            'user_id'    => $user->id,
            'payment_id' => $payment->id,
            'product_id' => $product->id,
        ]);
        // 3 used
        Ticket::factory()->count(3)->used()->create([
            'user_id'    => $user->id,
            'payment_id' => $payment->id,
            'product_id' => $product->id,
        ]);

        $this->assertDatabaseCount('tickets', 5);
        $usedCount = Ticket::where('status', TicketStatus::Used->value)->count();
        $this->assertEquals(3, $usedCount);

        DB::listen(fn($query) => fwrite(STDERR, "SQL: {$query->sql} [".implode(',',$query->bindings)."]\n"));

        $paginated = $this->service->getFilteredTickets([
            'status'   => TicketStatus::Used->value,
            'per_page' => 10,
        ]);

        $this->assertCount(3, $paginated->items());

        foreach ($paginated->items() as $ticket) {
            $this->assertEquals(
                TicketStatus::Used->value,
                $ticket->status->value,
                "Le ticket #{$ticket->id} devrait avoir status 'used'"
            );
        }
    }

    public function testItFiltersByUserIdAndEmail(): void
    {
        $u1 = User::factory()->create(['email' => 'a@a.com']);
        $u2 = User::factory()->create(['email' => 'b@b.com']);

        Ticket::factory()->count(2)->create(['user_id' => $u1->id]);
        Ticket::factory()->count(3)->create(['user_id' => $u2->id]);

        // By id
        $pag1 = $this->service->getFilteredTickets([
            'user_id'  => $u1->id,
            'per_page' => 10,
        ]);
        $this->assertCount(2, $pag1->items());
        // By email
        $pag2 = $this->service->getFilteredTickets([
            'user_email'=> 'b@b.com',
            'per_page'  => 10,
        ]);
        $this->assertCount(3, $pag2->items());
    }

    public function testItFiltersByDates(): void
    {
        // Création de tickets à différentes dates
        Ticket::factory()->create(['created_at' => Carbon::now()->subDays(5)]);
        Ticket::factory()->create(['created_at' => Carbon::now()->subDays(2)]);
        Ticket::factory()->create(['created_at' => Carbon::now()]);

        // Filtrer entre J-3 et aujourd'hui
        $pag = $this->service->getFilteredTickets([
            'created_from' => Carbon::now()->subDays(3)->toDateString(),
            'created_to'   => Carbon::now()->toDateString(),
            'per_page'     => 10,
        ]);
        // On doit obtenir juste les 2 tickets récents
        $this->assertCount(2, $pag->items());
    }

    public function testItPaginatesAndAppendsFilters(): void
    {
        Ticket::factory()->count(30)->create();

        $pag = $this->service->getFilteredTickets([
            'per_page' => 5,
            'status'   => TicketStatus::Issued->value,
        ]);

        $this->assertEquals(5, $pag->perPage());
        $this->assertStringContainsString('status='.TicketStatus::Issued->value, $pag->withQueryString()->url(2));
    }

    public function testGetFilteredTicketsByQ(): void
    {
        // Ticket matching by token
        $t1 = Ticket::factory()->create([
            'token' => 'ABC123',
            'product_snapshot' => [
                'product_name' => 'Concert X',
                'ticket_type'  => 'VIP',
            ],
        ]);
        // Ticket non-matching
        $t2 = Ticket::factory()->create([
            'token' => 'OTHER',
            'product_snapshot' => [
                'product_name' => 'Other Product',
                'ticket_type'  => 'Regular',
            ],
        ]);

        // Filter by q matching product_name
        $paginator = $this->service->getFilteredTickets(['q' => 'Concert']);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertCount(1, $paginator->items());
        $this->assertEquals($t1->id, $paginator->items()[0]->id);
    }

    public function testGetFilteredTicketsByProductIdAndPaymentUuid(): void
    {
        $product = Product::factory()->create();
        $payment = Payment::factory()->create(['uuid' => 'PAY-UUID-123']);

        // Ticket matching both filters
        $t1 = Ticket::factory()->create([
            'product_id' => $product->id,
            'payment_id' => $payment->id,
        ]);
        // Ticket with different product_id
        $t2 = Ticket::factory()->create([
            'product_id' => $product->id + 1,
            'payment_id' => $payment->id,
        ]);
        // Ticket with different payment_uuid
        $payment2 = Payment::factory()->create(['uuid' => 'OTHER-UUID']);
        $t3 = Ticket::factory()->create([
            'product_id' => $product->id,
            'payment_id' => $payment2->id,
        ]);

        $filters = [
            'product_id'   => $product->id,
            'payment_uuid' => 'PAY-UUID-123',
        ];

        $paginator = $this->service->getFilteredTickets($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        // Only t1 matches both product_id and payment_uuid
        $this->assertCount(1, $paginator->items());
        $this->assertEquals($t1->id, $paginator->items()[0]->id);
    }

    public function testGetUserTicketsFiltersByEventDateFrom(): void
    {
        // Crée un utilisateur
        $user = User::factory()->create();

        // Crée deux produits avec des dates d'événement différentes
        $product1 = Product::factory()->create([
            // On fusionne avec les données par défaut pour ne pas écraser tout le product_details
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-05-01']
            ),
        ]);
        $product2 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-06-15']
            ),
        ]);

        // Ticket pour le product1 (date avant le filtre → ne doit PAS apparaître)
        Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product1->id,
        ]);
        // Ticket pour le product2 (date après le filtre → doit apparaître)
        $t2 = Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product2->id,
        ]);

        $filters   = ['event_date_from' => '2025-06-01'];
        $paginator = $this->service->getUserTickets($user->id, $filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);

        $ids = collect($paginator->items())->pluck('id')->all();
        $this->assertEquals([$t2->id], $ids);
    }

    public function testGetUserTicketsFiltersByEventDateTo(): void
    {
        $user = User::factory()->create();

        // Crée deux produits avec des dates d'événement différentes
        $product1 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-08-01']
            ),
        ]);
        $product2 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-07-01']
            ),
        ]);

        // Ticket pour product1 (après la date_to → ne doit pas apparaître)
        Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product1->id,
        ]);
        // Ticket pour product2 (avant ou égal à la date_to → doit apparaître)
        $t2 = Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product2->id,
        ]);

        $filters   = ['event_date_to' => '2025-07-15'];
        $paginator = $this->service->getUserTickets($user->id, $filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);

        $ids = collect($paginator->items())->pluck('id')->all();
        $this->assertEquals([$t2->id], $ids);
    }

    public function testGetUserTicketsFiltersByBothDates(): void
    {
        $user = User::factory()->create();

        // Produit avec date 2025‑05‑01 (hors plage)
        $product1 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-05-01']
            ),
        ]);
        // Produit avec date 2025‑06‑10 (dans la plage)
        $product2 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-06-10']
            ),
        ]);
        // Produit avec date 2025‑08‑01 (hors plage)
        $product3 = Product::factory()->create([
            'product_details' => array_merge(
                Product::factory()->raw(['product_details']),
                ['date' => '2025-08-01']
            ),
        ]);

        // Tickets liés à chaque produit
        Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product1->id,
        ]);
        $t2 = Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product2->id,
        ]);
        Ticket::factory()->create([
            'user_id'    => $user->id,
            'product_id' => $product3->id,
        ]);

        // Filtre sur la plage 2025‑06‑01 → 2025‑07‑01
        $filters   = [
            'event_date_from' => '2025-06-01',
            'event_date_to'   => '2025-07-01',
        ];
        $paginator = $this->service->getUserTickets($user->id, $filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);

        // On ne doit obtenir que le ticket lié à product2
        $ids = collect($paginator->items())->pluck('id')->all();
        $this->assertEquals([$t2->id], $ids);
    }

    public function testGetSalesStatsDefaults(): void
    {
        // Création de 2 produits
        $p1 = Product::factory()->create(['name' => 'Prod A']);
        $p2 = Product::factory()->create(['name' => 'Prod B']);

        // Tickets non annulés/refundés
        Ticket::factory()->count(3)->create([
            'product_id' => $p1->id,
            'status'     => 'issued',
        ]);
        Ticket::factory()->count(1)->create([
            'product_id' => $p2->id,
            'status'     => 'issued',
        ]);
        // Tickets annulés/refundés (ne doivent pas compter)
        Ticket::factory()->count(5)->create([
            'product_id' => $p1->id,
            'status'     => 'cancelled',
        ]);

        $paginator = $this->service->getSalesStats([]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        // On attend 2 produits
        $this->assertCount(2, $paginator->items());

        // Vérifie l'ordre descendant par sales_count
        $items = $paginator->items();
        $this->assertEquals($p1->id, $items[0]['product_id']);
        $this->assertEquals(3,       $items[0]['sales_count']);
        $this->assertEquals($p2->id, $items[1]['product_id']);
        $this->assertEquals(1,       $items[1]['sales_count']);
    }

    public function testGetSalesStatsWithQFilter(): void
    {
        $p1 = Product::factory()->create(['name' => 'Alpha Product']);
        $p2 = Product::factory()->create(['name' => 'Beta Item']);

        Ticket::factory()->count(2)->create(['product_id' => $p1->id, 'status' => 'issued']);
        Ticket::factory()->count(2)->create(['product_id' => $p2->id, 'status' => 'issued']);

        // Filtre q cherche "alpha"
        $paginator = $this->service->getSalesStats(['q' => 'alpha']);
        $ids = collect($paginator->items())->pluck('product_id')->all();
        $this->assertEquals([$p1->id], $ids);
    }

    public function testGetSalesStatsWithCustomSortAndPerPage(): void
    {
        // 10 produits avec 1 ticket chacun
        $products = Product::factory()->count(10)->create();
        foreach ($products as $prod) {
            Ticket::factory()->create(['product_id' => $prod->id, 'status' => 'issued']);
        }

        // per_page à 5, tri par product_id ASC
        $filters = [
            'per_page'  => 5,
            'sort_by'   => 'product_id',
            'sort_order'=> 'asc',
        ];
        $paginator = $this->service->getSalesStats($filters);

        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);
        $this->assertCount(5, $paginator->items());
        $this->assertEquals(10, $paginator->total());

        // Le premier id doit être le plus petit
        $first = $paginator->items()[0]['product_id'];
        $this->assertEquals(
            $products->sortBy('id')->first()->id,
            $first
        );
    }

    public function testGetUserTicketsFiltersByStatus(): void
    {
        // 1) Créer un user et 3 tickets “used” pour lui
        $user = User::factory()->create();

        Ticket::factory()->count(3)->create([
            'user_id' => $user->id,
            'status'  => TicketStatus::Used->value,
        ]);
        // + quelques “issued” pour vérifier qu’ils ne remontent pas
        Ticket::factory()->count(2)->create([
            'user_id' => $user->id,
            'status'  => TicketStatus::Issued->value,
        ]);

        // 2) Appel avec filtre status=used
        $paginator = $this->service->getUserTickets($user->id, [
            'status'   => TicketStatus::Used->value,
            'per_page' => 10,
        ]);

        // 3) On couvre bien le code (les deux where('status',…) du service sont exécutés)
        $this->assertInstanceOf(LengthAwarePaginator::class, $paginator);

        // 4) Et maintenant on vérifie qu’on récupère exactement nos 3 tickets “used”
        $this->assertCount(3, $paginator->items());
        foreach ($paginator->items() as $ticket) {
            // ATTENTION : sur le modèle, status est un enum, donc on compare la valeur
            $this->assertSame(TicketStatus::Used->value, $ticket->status->value);
        }
    }

    public function testGetInfoByQrTokenReturnsExpectedStructure(): void
    {
        // 1) Préparation : user, product et ticket
        $user = User::factory()->create([
            'firstname' => 'Jane',
            'lastname'  => 'Roe',
            'email'     => 'jane.roe@example.com',
        ]);

        $product = Product::factory()->create([
            'name'            => 'Fantastic Show',
            'product_details' => [
                'date'     => '2025-09-01',
                'time'     => '20:30',
                'location' => 'Grand Hall',
                'places'   => 100,
            ],
        ]);

        // On génère nous‑même le token et le nom de fichier QR
        $token = (string) Str::uuid();
        $qrFilename = "qr_{$token}.png";

        // 2) Créer le ticket et forcer la mise à jour des attributs
        $ticket = Ticket::factory()->create();
        $ticket->forceFill([
            'user_id'     => $user->id,
            'product_id'  => $product->id,
            'token'       => $token,
            'qr_filename' => $qrFilename,
            'status'      => TicketStatus::Issued->value,
        ])->save();

        // 3) Exécution
        $info = $this->service->getInfoByQrToken($token);

        // 4) Assertions
        $this->assertSame($token, $info['token']);

        // Compare correctement l'enum
        $this->assertInstanceOf(TicketStatus::class, $info['status']);
        $this->assertSame(
            TicketStatus::Issued->value,
            $info['status']->value
        );

        $this->assertEquals([
            'firstname' => 'Jane',
            'lastname'  => 'Roe',
            'email'     => 'jane.roe@example.com',
        ], $info['user']);

        $this->assertEquals([
            'name'     => 'Fantastic Show',
            'date'     => '2025-09-01',
            'time'     => '20:30',
            'location' => 'Grand Hall',
            'places'   => 100,
        ], $info['event']);
    }
}
