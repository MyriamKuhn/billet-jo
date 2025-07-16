<?php

namespace App\Listeners;

use App\Events\PaymentSucceeded;
use App\Services\TicketService;
use Illuminate\Support\Facades\Storage;

/**
 * Class GenerateTicketsForPayment
 *
 * This listener handles the generation of tickets when a payment succeeds.
 * It checks if tickets already exist for the payment and generates them if not.
 */
class GenerateTicketsForPayment
{
    protected TicketService $tickets;

    /**
     * GenerateTicketsForPayment constructor.
     *
     * @param TicketService $tickets
     */
    public function __construct(TicketService $tickets)
    {
        $this->tickets = $tickets;
    }

    /**
     * Handle the event.
     *
     * @param PaymentSucceeded $event
     * @return void
     */
    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment;
        // If tickets already exist for this payment, skip generation
        if ($payment->tickets()->exists()) {
            \Log::info("Tickets already generated for payment {$payment->uuid}, skipping.");
            return;
        }
        // Otherwise, generate tickets for this payment UUID and locale
        $this->tickets->generateForPaymentUuid($payment->uuid, $event->locale);
    }
}
