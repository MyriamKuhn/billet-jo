<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DbPerformanceTest extends TestCase
{
    use RefreshDatabase;

        public function testPaymentHasManyTicketsPerformance()
    {
        $payment = Payment::factory()->create(); // Crée un paiement
        $ticket = Ticket::factory()->create(['payment_id' => $payment->id]); // Crée un ticket lié au paiement

        // Mesure le temps d'exécution pour charger les tickets
        $startTime = microtime(true);
        $payment->tickets()->get(); // Charge les tickets associés au paiement
        $endTime = microtime(true);

        $executionTime = $endTime - $startTime; // Temps d'exécution en secondes
        $this->assertTrue($executionTime < 0.5, "La requête a pris trop de temps : {$executionTime}s"); // S'assure que le temps est inférieur à 0.5 seconde
    }
}

