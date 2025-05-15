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
        $user = User::factory()->create();
        $other = User::factory()->create();
        // Create tickets with snapshot names
        Ticket::factory()->create(['user_id' => $user->id, 'product_snapshot' => ['product_name'=>'Alpha']]);
        Ticket::factory()->create(['user_id' => $user->id, 'product_snapshot' => ['product_name'=>'Beta']]);
        Ticket::factory()->create(['user_id' => $other->id, 'product_snapshot' => ['product_name'=>'Alpha']]);

        $filters = ['q' => 'Alpha', 'per_page' => 10];
        $paginator = $this->service->getUserTickets($user->id, $filters);
        $this->assertCount(1, $paginator->items());
        $this->assertEquals('Alpha', $paginator[0]->product_snapshot['product_name']);
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
}
