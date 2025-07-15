<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\Product;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Exceptions\TicketAlreadyProcessedException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use App\Events\InvoiceRequested;
use App\Events\PaymentSucceeded;
use App\Services\TicketService;
use Mockery;
use Laravel\Sanctum\Sanctum;
use App\Http\Controllers\Api\TicketController;
use App\Http\Requests\TicketScanRequest;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tickets');
        Storage::fake('qrcodes');
    }

    public function testIndexRequiresAdminAndReturnsPaginatedList(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        Ticket::factory()->count(3)->create();

        $this->getJson('/api/tickets')->assertStatus(401);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/tickets')
            ->assertStatus(403);

        $res = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/tickets?per_page=2');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'token',
                        'product_snapshot', // ← on garde celui-ci
                        'status',
                        'used_at',
                        'refunded_at',
                        'cancelled_at',
                        'qr_filename',
                        'pdf_filename',
                        'user',
                        'payment',
                        // 'product',        // ← on supprime
                        'created_at',
                        'updated_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }

    public function testUserTicketsReturnsOnlyOwnEntries(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Ticket::factory()->count(2)->create(['user_id' => $user1->id]);
        Ticket::factory()->count(3)->create(['user_id' => $user2->id]);

        // Non-auth → 401
        $this->getJson('/api/tickets/user')->assertStatus(401);

        // Auth user1 → voit 2
        $this->actingAs($user1, 'sanctum')
            ->getJson('/api/tickets/user')
            ->assertJsonCount(2, 'data');
    }

    public function testDownloadTicketUserAndAdminBehaviors(): void
    {
        $user    = User::factory()->create();
        $admin   = User::factory()->create(['role' => 'admin']);
        $ticket  = Ticket::factory()->create([
            'user_id'      => $user->id,
            'pdf_filename' => $fn = 'ticket1.pdf',
        ]);

        // Place file on disk
        Storage::disk('tickets')->put($fn, 'PDFDATA');

        // Non-auth → 401 JSON
        $this->getJson("/api/tickets/{$fn}")->assertStatus(401);

        // Auth wrong user → 404
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/tickets/{$fn}")
            ->assertStatus(404);

        // Auth rightful user → 200 + content-type PDF
        $res = $this->actingAs($user, 'sanctum')
                    ->get("/api/tickets/{$fn}");
        $res->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // Admin download any → 200 PDF
        $this->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/{$fn}")
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');

        // Missing file → 404
        Storage::disk('tickets')->delete($fn);
        $this->actingAs($user, 'sanctum')
            ->get("/api/tickets/{$fn}")
            ->assertStatus(404);
    }

    public function testDownloadQrUserAndAdminBehaviors(): void
    {
        $user  = User::factory()->create();
        $admin = User::factory()->create(['role'=>'admin']);
        $ticket = Ticket::factory()->create([
            'user_id'     => $user->id,
            'qr_filename' => $fn = 'qr1.png',
        ]);
        Storage::disk('qrcodes')->put($fn, 'IMAGEDATA');

        // User
        $this->actingAs($user, 'sanctum')
            ->get("/api/tickets/qr/{$fn}")
            ->assertStatus(200)
            ->assertHeader('content-type', 'image/png');

        // Admin
        $this->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/qr/{$fn}")
            ->assertStatus(200);

        // Not found
        Storage::disk('qrcodes')->delete($fn);
        $this->actingAs($user, 'sanctum')
            ->get("/api/tickets/qr/{$fn}")
            ->assertStatus(404);
    }

    public function testNonAdminCannotUpdateTicketStatus(): void
    {
        $user   = User::factory()->create();              // role = 'user'
        $ticket = Ticket::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tickets/admin/{$ticket->id}/status", [
                'status' => TicketStatus::Used->value,
            ])
            ->assertForbidden();
    }

    public function testAdminCannotSubmitInvalidStatus(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/tickets/admin/{$ticket->id}/status", [
                'status' => 'statut_inexistant',
            ])
            ->assertUnprocessable();
    }

    public function testAdminCanUpdateToValidStatusAndPersists(): void
    {
        $admin  = User::factory()->create(['role' => 'admin']);
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/tickets/admin/{$ticket->id}/status", [
                'status' => TicketStatus::Used->value,
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatus::Used->value,
        ]);
    }

    public function testNonAdminCannotCreateTickets(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
        ]);
        $product = Product::factory()->create();

        $this
            ->actingAs($user, 'sanctum')
            ->postJson('/api/tickets', [
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'quantity'   => 2,
            ])
            ->assertForbidden()
            ->assertJsonFragment([
                'code' => 'forbidden',
                'message' => 'You do not have permission to perform this action',
            ]);
    }

    public function testAdminGetsValidationErrorsWhenPayloadMissing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/tickets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'product_id', 'quantity']);
    }

    public function testAdminWithValidPayloadCallsServiceAndReturnsNoContent(): void
    {
        // On ne mocke QUE pour ce scénario
        $this->mock(TicketService::class, function ($mock) {
            $mock->shouldReceive('createFreeTickets')
                ->once()
                ->withArgs(fn($userId, $productId, $qty) =>
                    is_int($userId) &&
                    is_int($productId) &&
                    $qty === 2
                );
        });

        $user    = User::factory()->create();
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();

        $this
            ->actingAs($admin, 'sanctum')
            ->postJson('/api/tickets', [
                'user_id'    => $user->id,
                'product_id' => $product->id,
                'quantity'   => 2,
            ])
            ->assertNoContent();
    }

    public function testScanTicketMarksIssuedAndReturnsStatusAndTimestamp(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $ticket   = Ticket::factory()->create(['status' => TicketStatus::Issued->value]);

        // non-auth → 401
        $this->postJson("/api/tickets/scan/{$ticket->token}")
            ->assertStatus(401);

        // auth non‑employé → 403
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/tickets/scan/{$ticket->token}")
            ->assertStatus(403);

        // auth employé → scan OK → 200 + retourne seulement status & used_at
        $res = $this->actingAs($employee, 'sanctum')
                    ->postJson("/api/tickets/scan/{$ticket->token}");

        $res->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'used_at',
            ]);

        $this->assertDatabaseHas('tickets', [
            'id'     => $ticket->id,
            'status' => TicketStatus::Used->value,
        ]);

        // deuxième tentative → 409 et code d’erreur approprié
        $this->actingAs($employee, 'sanctum')
            ->postJson("/api/tickets/scan/{$ticket->token}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'ticket_already_processed');
    }

    public function testNonAdminCannotDownloadTicket(): void
    {
        $user     = User::factory()->create(); // rôle “user” par défaut
        $filename = 'ticket_nonexistent.pdf';

        $this
            ->actingAs($user, 'sanctum')
            ->get("/api/tickets/admin/{$filename}")
            ->assertForbidden();
    }

    public function testAdminCannotDownloadNonexistentTicket(): void
    {
        Storage::fake('tickets');
        $admin    = User::factory()->create(['role' => 'admin']);
        $filename = 'ticket_nonexistent.pdf';

        $this
            ->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/{$filename}")
            ->assertNotFound();
    }

    public function testAdminCanDownloadExistingTicket(): void
    {
        Storage::fake('tickets');
        $admin    = User::factory()->create(['role' => 'admin']);
        $filename = 'ticket_valid.pdf';
        $content  = 'dummy pdf';
        Storage::disk('tickets')->put($filename, $content);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/{$filename}");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload($filename);
    }

    public function testNonAdminCannotDownloadQrCode(): void
    {
        $user     = User::factory()->create(['role' => 'user']);
        $filename = 'qr_nonexistent.png';

        $this
            ->actingAs($user, 'sanctum')
            ->get("/api/tickets/admin/qr/{$filename}")
            ->assertForbidden();
    }

    public function testAdminCannotDownloadNonexistentQrCode(): void
    {
        Storage::fake('qrcodes');
        $admin    = User::factory()->create(['role' => 'admin']);
        $filename = 'qr_nonexistent.png';

        $this
            ->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/qr/{$filename}")
            ->assertNotFound();
    }

    public function testAdminCanDownloadExistingQrCode(): void
    {
        Storage::fake('qrcodes');
        $admin    = User::factory()->create(['role' => 'admin']);
        $filename = 'qr_valid.png';
        $content  = 'fake-png-content';
        Storage::disk('qrcodes')->put($filename, $content);

        $response = $this
            ->actingAs($admin, 'sanctum')
            ->get("/api/tickets/admin/qr/{$filename}");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload($filename);
    }

    public function testShowTicketRequiresAuthenticationAndEmployeeRole(): void
    {
        // Générer un UUID valide
        $token = (string) Str::uuid();

        // non-authentifié → 401
        $this->getJson("/api/tickets/scan/{$token}")
            ->assertStatus(401);

        // auth comme utilisateur non-employé → 403
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/tickets/scan/{$token}")
            ->assertStatus(403);
    }

    public function testEmployeeCanRetrieveTicketInfo(): void
    {
        // Générer un UUID valide et un tableau d’info à retourner
        $token = (string) Str::uuid();
        $info = [
            'token'  => $token,
            'status' => 'issued',
            'user'   => [
                'firstname' => 'John',
                'lastname'  => 'Doe',
                'email'     => 'john.doe@example.com',
            ],
            'event'  => [
                'name'     => 'Concert Live',
                'date'     => '2024-07-26',
                'time'     => '20:00',
                'location' => 'Stade de France',
            ],
        ];

        // Mocker le service pour renvoyer notre $info
        $this->mock(TicketService::class, function ($mock) use ($token, $info) {
            $mock->shouldReceive('getInfoByQrToken')
                ->once()
                ->with($token)
                ->andReturn($info);
        });

        // Authentifié en tant qu’employé → 200 + JSON exact
        $employee = User::factory()->create(['role' => 'employee']);
        $response = $this->actingAs($employee, 'sanctum')
                        ->getJson("/api/tickets/scan/{$token}");

        $response->assertStatus(200)
                ->assertExactJson($info);
    }
}
