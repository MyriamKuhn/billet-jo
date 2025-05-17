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
        // Give the payment UUID to the ticket service to generate tickets
        $this->tickets->generateForPaymentUuid($event->payment->uuid, $event->locale);
    }
}
