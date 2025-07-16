<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

/**
 * Event fired when an invoice is requested for a payment.
 *
 * Carries the Payment model and the locale to use for invoice generation.
 */
class InvoiceRequested
{
    use Dispatchable, SerializesModels;

    /**
     * The payment instance for which the invoice is requested.
     *
     * @var Payment
     */
    public Payment $payment;
    /**
     * The locale string to use when generating the invoice.
     *
     * @var string
     */
    public string $locale;

    /**
     * Create a new event instance.
     *
     * @param  Payment      $payment  The payment tied to this invoice request.
     * @param  string|null  $locale   Optional locale; defaults to the app’s current locale.
     */
    public function __construct(Payment $payment, ?string $locale = null)
    {
        $this->payment = $payment;
        $this->locale  = $locale ?? app()->getLocale();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Using a private channel ensures only authorized listeners receive it.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
