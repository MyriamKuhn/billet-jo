<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Payment;
use App\Models\Product;
use App\Enums\TicketStatus;

class TicketModelTest extends TestCase
{
    use RefreshDatabase;

    public function testItHasTheCorrectFillableAttributes()
    {
        $expected = [
            'product_snapshot',
            'token',
            'qr_filename',
            'pdf_filename',
            'status',
            'used_at',
            'refunded_at',
            'cancelled_at',
            'user_id',
            'payment_id',
            'product_id',
        ];

        $this->assertEquals(
            $expected,
            (new Ticket())->getFillable()
        );
    }

    public function testItGeneratesAUuidTokenOnCreation()
    {
        $ticket = Ticket::factory()->create();
        $this->assertTrue(Str::isUuid($ticket->token));
    }

    public function testProductSnapshotIsCastToArray()
    {
        $snapshot = [
            'product_name' => 'Test',
            'unit_price' => 10.0,
        ];
        $ticket = Ticket::factory()->create([ 'product_snapshot' => $snapshot ]);

        $this->assertIsArray($ticket->product_snapshot);
        $this->assertEquals($snapshot, $ticket->product_snapshot);
    }

    public function testStatusIsCastToTicketStatusEnum()
    {
        $ticket = Ticket::factory()->create();

        $this->assertInstanceOf(TicketStatus::class, $ticket->status);
        $this->assertEquals(TicketStatus::Issued, $ticket->status);
    }

    public function testDateAttributesAreCastToCarbonInstances()
    {
        $ticket = Ticket::factory()->create([
            'used_at' => now(),
            'refunded_at' => now(),
            'cancelled_at' => now()
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ticket->used_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ticket->refunded_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $ticket->cancelled_at);
    }

    public function testItUsesTokenForRouteBinding()
    {
        $this->assertEquals(
            'token',
            (new Ticket())->getRouteKeyName()
        );
    }

    public function testQrCodeUrlAccessorReturnsCorrectUrl()
    {
        Storage::shouldReceive('url')
            ->once()
            ->with('qrcodes/test.png')
            ->andReturn('http://cdn/qrcodes/test.png');

        $ticket = Ticket::factory()->create([ 'qr_filename' => 'test.png' ]);

        $this->assertEquals(
            'http://cdn/qrcodes/test.png',
            $ticket->qr_code_url
        );
    }

    public function testPdfUrlAccessorReturnsCorrectUrl()
    {
        Storage::shouldReceive('url')
            ->once()
            ->with('tickets/test.pdf')
            ->andReturn('http://cdn/tickets/test.pdf');

        $ticket = Ticket::factory()->create([ 'pdf_filename' => 'test.pdf' ]);

        $this->assertEquals(
            'http://cdn/tickets/test.pdf',
            $ticket->pdf_url
        );
    }

    public function testItBelongsToUserPaymentAndProduct()
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([ 'user_id' => $user->id ]);
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'product_id' => $product->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $ticket->user());
        $this->assertInstanceOf(BelongsTo::class, $ticket->payment());
        $this->assertInstanceOf(BelongsTo::class, $ticket->product());

        $this->assertTrue($ticket->user->is($user));
        $this->assertTrue($ticket->payment->is($payment));
        $this->assertTrue($ticket->product->is($product));
    }
}
