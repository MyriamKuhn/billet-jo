<?php

namespace Tests\Unit\Events;

use Tests\TestCase;
use App\Events\InvoiceRequested;
use App\Models\Payment;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceRequestedTest extends TestCase
{
    use RefreshDatabase;

    public function testBroadcastOnReturnsPrivateChannelNamedChannelName()
    {
        // Crée un payment factice
        $payment = Payment::factory()->create();

        // Instancie l'événement
        $event = new InvoiceRequested($payment);

        // Appelle broadcastOn()
        $channels = $event->broadcastOn();

        // On doit avoir exactement un channel
        $this->assertCount(1, $channels);

        // C'est bien un PrivateChannel
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

        // Le cast en string doit donner "private-channel-name"
        $this->assertEquals('private-channel-name', (string) $channels[0]);
    }
}
