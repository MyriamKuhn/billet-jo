<?php

namespace Tests\Unit\Listeners;

use App\Events\PaymentSucceeded;
use App\Listeners\GenerateTicketsForPayment;
use App\Models\Payment;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;
use App\Models\Ticket;

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

    public function testHandleSkipsGenerationWhenTicketsAlreadyExist(): void
    {
        // 1) Créer un vrai paiement avec un UUID fixe
        $payment = Payment::factory()->create([
            'uuid' => 'test-uuid-5678',
        ]);

        // 2) Créer un ticket lié à ce paiement (tickets()->exists() sera true)
        Ticket::factory()->create([
            'payment_id' => $payment->id,
        ]);

        // 3) Mocker TicketService pour s'assurer que generateForPaymentUuid n'est jamais appelé
        $mockService = $this->getMockBuilder(TicketService::class)
                            ->disableOriginalConstructor()
                            ->getMock();
        $mockService->expects($this->never())
                    ->method('generateForPaymentUuid');
        $this->app->instance(TicketService::class, $mockService);

        // 4) Mocker le Log pour vérifier qu'on logge bien le message de skip
        Log::shouldReceive('info')
            ->once()
            ->with("Tickets already generated for payment {$payment->uuid}, skipping.");

        // 5) Exécuter le listener
        $listener = $this->app->make(GenerateTicketsForPayment::class);
        $event = new PaymentSucceeded($payment);
        $listener->handle($event);
    }
}
