<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testIndexListsOnlyAuthenticatedUserInvoices()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Crée des paiements pour chaque user
        Payment::factory()->create([
            'user_id'      => $user1->id,
            'amount'       => 10,
            'status'       => PaymentStatus::Paid,
            'invoice_link' => 'inv1.pdf'
        ]);
        Payment::factory()->create([
            'user_id'      => $user2->id,
            'amount'       => 20,
            'status'       => PaymentStatus::Paid,
            'invoice_link' => 'inv2.pdf'
        ]);

        $this->actingAs($user1, 'sanctum')
            ->getJson('/api/invoices')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'invoice_link' => 'inv1.pdf',
                'amount'       => 10.0,
                'status'       => 'paid'
            ]);

        // user2 ne doit pas voir inv1
        $this->actingAs($user2, 'sanctum')
            ->getJson('/api/invoices')
            ->assertJsonFragment(['invoice_link' => 'inv2.pdf'])
            ->assertJsonMissing(['invoice_link' => 'inv1.pdf']);
    }

    public function testDownloadStreamsPdfForOwner()
    {
        $user = User::factory()->create();
        $filename = 'inv_owner.pdf';

        // Crée paiement et fichier
        Payment::factory()->create([
            'user_id'      => $user->id,
            'invoice_link' => $filename,
        ]);
        Storage::fake('invoices');
        Storage::disk('invoices')->put("private/invoices/{$filename}", 'PDF-CONTENT');

        $response = $this->actingAs($user, 'sanctum')
                        ->get("/api/invoices/{$filename}");

        // Vérifie le statut et l'en-tête
        $response->assertStatus(200);
        $response->assertHeader('content-disposition', "attachment; filename={$filename}");

        // Capture le flux de sortie pour en vérifier le contenu
        ob_start();
        $response->sendContent();
        $streamed = ob_get_clean();

        $this->assertEquals('PDF-CONTENT', $streamed);
    }

    public function testDownloadReturns404IfNotOwnerOrMissing()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $filename = 'inv_missing.pdf';

        // Pas de paiement pour user1 ni fichier
        $this->actingAs($user1, 'sanctum')
            ->get("/api/invoices/{$filename}")
            ->assertStatus(404);

        // Crée paiement pour user2, mais user1 essaie de télécharger
        Payment::factory()->create([
            'user_id'      => $user2->id,
            'invoice_link' => $filename,
        ]);
        $this->actingAs($user1, 'sanctum')
            ->get("/api/invoices/{$filename}")
            ->assertStatus(404);
    }

    public function testAdminDownloadAllowsAdminAndStreamsPdf()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $filename = 'inv_admin.pdf';

        Storage::fake('invoices');
        Storage::disk('invoices')->put("private/invoices/{$filename}", 'PDF-ADMIN');

        $response = $this->actingAs($admin, 'sanctum')
                        ->get("/api/invoices/admin/{$filename}");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', "attachment; filename={$filename}");

        // Capture le flux de sortie
        ob_start();
        $response->sendContent();
        $streamed = ob_get_clean();

        $this->assertEquals('PDF-ADMIN', $streamed);
    }

    public function testAdminDownloadReturns403ForNonAdmin()
    {
        $user = User::factory()->create(['role' => 'user']);
        $filename = 'inv_noaccess.pdf';

        Storage::fake('invoices');
        Storage::disk('invoices')->put("private/invoices/{$filename}", 'X');

        $this->actingAs($user, 'sanctum')
            ->get("/api/invoices/admin/{$filename}")
            ->assertStatus(403);
    }

    public function testAdminDownloadReturns404WhenFileMissing()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $filename = 'inv_notfound.pdf';

        Storage::fake('invoices');
        // Pas de fichier

        $this->actingAs($admin, 'sanctum')
            ->get("/api/invoices/admin/{$filename}")
            ->assertStatus(404);
    }
}
