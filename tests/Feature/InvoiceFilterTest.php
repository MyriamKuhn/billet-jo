<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Carbon;

class InvoiceFilterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // On fixe la date pour être certain des comparaisons
        Carbon::setTestNow(Carbon::parse('2025-05-15'));
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testItFiltersInvoicesByStatus()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Création de deux paiements, un "paid" et un "pending"
        $paid    = Payment::factory()->create([
            'user_id' => $user->id,
            'status'  => PaymentStatus::Paid,
        ]);
        $pending = Payment::factory()->create([
            'user_id' => $user->id,
            'status'  => PaymentStatus::Pending,
        ]);

        $response = $this->getJson('/api/invoices?status=paid');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonFragment(['uuid' => $paid->uuid])
                ->assertJsonMissing(['uuid' => $pending->uuid]);
    }

    public function testItFiltersInvoicesByDateFrom()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Paiement ancien et paiement récent
        $old = Payment::factory()->create([
            'user_id'    => $user->id,
            'created_at' => Carbon::parse('2025-05-10'),
        ]);
        $new = Payment::factory()->create([
            'user_id'    => $user->id,
            'created_at' => Carbon::parse('2025-05-16'),
        ]);

        // date_from = 2025-05-15 => ne doit récupérer que le paiement du 16
        $response = $this->getJson('/api/invoices?date_from=2025-05-15');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonFragment(['uuid' => $new->uuid])
                ->assertJsonMissing(['uuid' => $old->uuid]);
    }

    public function testItFiltersInvoicesByDateTo()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Paiement ancien et paiement récent
        $old = Payment::factory()->create([
            'user_id'    => $user->id,
            'created_at' => Carbon::parse('2025-05-10'),
        ]);
        $new = Payment::factory()->create([
            'user_id'    => $user->id,
            'created_at' => Carbon::parse('2025-05-16'),
        ]);

        // date_to = 2025-05-15 => ne doit récupérer que le paiement du 10
        $response = $this->getJson('/api/invoices?date_to=2025-05-15');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonFragment(['uuid' => $old->uuid])
                ->assertJsonMissing(['uuid' => $new->uuid]);
    }
}
