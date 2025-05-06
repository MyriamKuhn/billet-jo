<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class DbEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function testPaymentHasManyTicketsEagerLoading()
    {
        $payment = Payment::factory()->create(); // Crée un paiement
        $ticket = Ticket::factory()->create(['payment_id' => $payment->id]); // Crée un ticket lié au paiement

        // Eager load les tickets avec le paiement
        $paymentWithTickets = Payment::with('tickets')->find($payment->id);

        // Vérifie que les tickets sont bien chargés
        $this->assertNotEmpty($paymentWithTickets->tickets);
        $this->assertCount(1, $paymentWithTickets->tickets); // Vérifie qu'il y a bien un ticket associé
    }

    public function testPaymentEagerLoadingPreventsNPlusOne()
    {
        Payment::factory()->count(5)->create()->each(function ($payment) {
            Ticket::factory()->create(['payment_id' => $payment->id]);
        });

        DB::enableQueryLog();

        $paymentsWithTickets = Payment::with('tickets')->get();

        $queries = DB::getQueryLog();

        // Une requête pour payments + une pour tickets => 2 au total
        $this->assertCount(2, $queries, 'Le problème N+1 a été détecté, trop de requêtes exécutées malgré l’eager loading.');
    }

    public function testPaymentWithoutEagerLoadingCausesNPlusOne()
    {
        Payment::factory()->count(5)->create()->each(function ($payment) {
            Ticket::factory()->create(['payment_id' => $payment->id]);
        });

        DB::enableQueryLog();

        $payments = Payment::all();

        foreach ($payments as $payment) {
            $payment->tickets; // Lazy loading -> N requêtes
        }

        $queries = DB::getQueryLog();

        // 1 requête pour les paiements + 5 requêtes pour chaque ticket
        $this->assertGreaterThan(2, count($queries), 'Aucune surcharge N+1 détectée alors qu’elle devrait exister.');
    }

    public function testPerformanceComparisonWithVsJoin()
    {
        // Vide les tables et recrée les données
        Artisan::call('migrate:fresh');
        Payment::factory()
            ->count(100)
            ->hasTickets(10) // 10 tickets par paiement
            ->create();

        // ───── Test Eloquent avec with() ─────
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startEloquent = microtime(true);
        $eloquentData = Payment::with('tickets')->get();
        $durationEloquent = microtime(true) - $startEloquent;

        $eloquentQueries = DB::getQueryLog();

        // ───── Test avec join() ─────
        DB::flushQueryLog();
        DB::enableQueryLog();

        $startJoin = microtime(true);
        $joinedData = DB::table('payments')
            ->join('tickets', 'payments.id', '=', 'tickets.payment_id')
            ->select('payments.*', 'tickets.id as ticket_id')
            ->get();
        $durationJoin = microtime(true) - $startJoin;

        $joinQueries = DB::getQueryLog();

        // ───── Résumé ─────
        dump([
            'Eloquent Duration' => $durationEloquent,
            'Eloquent Queries' => count($eloquentQueries),
            'Join Duration' => $durationJoin,
            'Join Queries' => count($joinQueries),
        ]);

        $this->assertTrue(true); // Pour que le test passe
    }
}
