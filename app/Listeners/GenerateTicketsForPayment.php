<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Services\TicketService;
use Illuminate\Support\Facades\Storage;

class GenerateTicketsForPayment
{
    protected TicketService $tickets;

    /**
     * Create the event listener.
     */
    public function __construct(TicketService $tickets)
    {
        $this->tickets = $tickets;
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment;
        // Si des tickets sont déjà en base pour ce paiement, on ne régénère pas
        if ($payment->tickets()->exists()) {
            \Log::info("Tickets already generated for payment {$payment->uuid}, skipping.");
            return;
        }
        // Sinon, on génère
        $this->tickets->generateForPaymentUuid($payment->uuid, $event->locale);
    }
}
