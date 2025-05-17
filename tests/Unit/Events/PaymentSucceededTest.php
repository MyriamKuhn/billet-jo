<?php

namespace Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use Illuminate\Broadcasting\PrivateChannel;
use App\Events\PaymentSucceeded;
use App\Models\Payment;

class PaymentSucceededTest extends TestCase
{
    public function testBroadcastOnReturnsPrivateChannel(): void
    {
        $payment = new Payment();

        // On fournit explicitement une locale pour éviter l’appel à app()->getLocale()
        $event   = new PaymentSucceeded($payment, 'fr');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

        // Laravel préfixe "private-" devant le nom
        $this->assertEquals('private-channel-name', $channels[0]->name);
    }
}
