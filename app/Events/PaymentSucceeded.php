<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

/**
 * Event triggered when a payment completes successfully.
 *
 * Contains the Payment model and locale for any downstream processing
 * (e.g., notifications, receipts).
 */
class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    /**
     * The payment instance that was processed successfully.
     *
     * @var Payment
     */
    public Payment $payment;
    /**
     * The locale to use for any messages or formatting.
     *
     * @var string
     */
    public string $locale;

    /**
     * Create a new event instance.
     *
     * @param  Payment      $payment  The payment that succeeded.
     * @param  string|null  $locale   Optional locale; defaults to the app’s current locale.
     */
    public function __construct(Payment $payment, string $locale = null)
    {
        $this->payment = $payment;
        $this->locale  = $locale ?? app()->getLocale();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasting on a private channel scoped to the user ensures
     * that only authorized clients receive the event.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
