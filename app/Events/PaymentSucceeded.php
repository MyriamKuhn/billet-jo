<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Payment;

class PaymentSucceeded
{
    use Dispatchable, SerializesModels;

    public Payment $payment;
    public string $locale;

    /**
     * Create a new event instance.
     */
    public function __construct(Payment $payment, string $locale = null)
    {
        $this->payment = $payment;
        $this->locale  = $locale ?? app()->getLocale();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
