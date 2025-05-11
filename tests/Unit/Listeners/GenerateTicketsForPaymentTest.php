<?php

namespace Tests\Unit\Listeners;

use App\Events\PaymentSucceeded;
use App\Listeners\GenerateTicketsForPayment;
use App\Models\Payment;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateTicketsForPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function testHandleCallsGenerateForPaymentUuidOnTicketService(): void
    {
        // 1) Préparer un paiement avec un UUID fixe
        $payment = Payment::factory()->create([
            'uuid' => 'test-uuid-1234',
        ]);

        // 2) Construire l'événement
        $event = new PaymentSucceeded($payment);

        // 3) Créer un mock PHPUnit pour TicketService
        $mockService = $this->getMockBuilder(TicketService::class)
                            ->disableOriginalConstructor()
                            ->getMock();

        // 4) S'assurer que generateForPaymentUuid est appelé UNE seule fois avec notre UUID
        $mockService->expects($this->once())
                    ->method('generateForPaymentUuid')
                    ->with('test-uuid-1234');

        // 5) Lier ce mock dans le container Laravel
        $this->app->instance(TicketService::class, $mockService);

        // 6) Récupérer le listener (injection automatique du mock)
        $listener = $this->app->make(GenerateTicketsForPayment::class);

        // 7) Exécuter la méthode handle()
        $listener->handle($event);

        // (l’attente du mock PHPUnit fait office d’assertion)
    }
}
