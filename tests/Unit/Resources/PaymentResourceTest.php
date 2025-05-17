<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Payment;
use App\Models\User;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Resources\PaymentResource;

class PaymentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testToArrayFallbacksWhenProductNotLoaded(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create two products
        $prod1 = Product::factory()->create([
            'name' => 'RealName1',
            'product_details' => ['category' => 'RealCategory1'],
        ]);
        $prod2 = Product::factory()->create([
            'name' => 'RealName2',
            'product_details' => ['category' => 'RealCategory2'],
        ]);

        // Define two line items
        $line1 = [
            'product_id'       => $prod1->id,
            'product_name'     => 'FallbackName1',
            'ticket_type'      => 'FallbackType1',
            'ticket_places'    => 2,
            'quantity'         => 3,
            'unit_price'       => 10.0,
            'discount_rate'    => 0.2,
            'discounted_price' => 8.0,
        ];
        $line2 = [
            'product_id'       => $prod2->id,
            'product_name'     => 'FallbackName2',
            'ticket_type'      => 'FallbackType2',
            'ticket_places'    => 1,
            'quantity'         => 1,
            'unit_price'       => 20.0,
            'discount_rate'    => 0.0,
            'discounted_price' => 20.0,
        ];

        // Create payment with cart_snapshot
        $payment = Payment::factory()->create([
            'uuid'             => '550e8400-e29b-41d4-a716-446655440000',
            'invoice_link'     => 'https://example.com/invoice/12345',
            'cart_snapshot'    => ['items' => [$line1, $line2]],
            'amount'           => 100.00,
            'payment_method'   => PaymentMethod::Paypal,
            'status'           => PaymentStatus::Paid,
            'transaction_id'   => 'tx_123',
            'paid_at'          => Carbon::parse('2025-05-01T10:00:00Z'),
            'refunded_at'      => null,
            'refunded_amount'  => null,
        ]);
        // Set relations
        $payment->setRelation('user', $user);
        // Only map first product
        $payment->setRelation('snapshot_products', collect([$prod1->id => $prod1]));

        $resource = new PaymentResource($payment);
        $array = $resource->toArray(Request::create('/'));

        // First item uses real product fields
        $this->assertEquals($prod1->id, $array['cart_snapshot'][0]['product_id']);
        $this->assertEquals('RealName1', $array['cart_snapshot'][0]['product_name']);
        $this->assertEquals('RealCategory1', $array['cart_snapshot'][0]['ticket_type']);

        // Second item falls back to line data
        $this->assertEquals($prod2->id, $array['cart_snapshot'][1]['product_id']);
        $this->assertEquals('FallbackName2', $array['cart_snapshot'][1]['product_name']);
        $this->assertEquals('FallbackType2', $array['cart_snapshot'][1]['ticket_type']);
    }

    public function testToArrayIncludesAllTopLevelFields(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $paidAt = Carbon::parse('2025-04-01T12:00:00Z');
        $updatedAt = Carbon::parse('2025-04-02T15:00:00Z');
        $createdAt = Carbon::parse('2025-01-01T00:00:00Z');

        $payment = Payment::factory()->create([
            'uuid'            => '550e8400-e29b-41d4-a716-446655440000',
            'invoice_link'    => 'https://example.com/invoice/12345',
            'cart_snapshot'   => ['items' => []],
            'amount'          => 130.00,
            'payment_method'  => PaymentMethod::Stripe,
            'status'          => PaymentStatus::Pending,
            'transaction_id'  => null,
            'paid_at'         => $paidAt,
            'refunded_at'     => $updatedAt,
            'refunded_amount' => 50.00,
            'created_at'      => $createdAt,
            'updated_at'      => $updatedAt,
        ]);
        $payment->setRelation('user', $user);
        $payment->setRelation('snapshot_products', collect());

        $array = (new PaymentResource($payment))->toArray(Request::create('/'));

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $array['uuid']);
        $this->assertEquals('https://example.com/invoice/12345', $array['invoice_link']);
        $this->assertEquals([], $array['cart_snapshot']);
        $this->assertEquals(130.00, $array['amount']);
        $this->assertEquals(PaymentMethod::Stripe->value, $array['payment_method']);
        $this->assertEquals(PaymentStatus::Pending->value, $array['status']);
        $this->assertNull($array['transaction_id']);
        $this->assertEquals($paidAt->toIso8601String(), $array['paid_at']);
        $this->assertEquals($updatedAt->toIso8601String(), $array['refunded_at']);
        $this->assertEquals(50.00, $array['refunded_amount']);
        $this->assertEquals(['id' => $user->id, 'email' => 'user@example.com'], $array['user']);
        $this->assertEquals($createdAt->toIso8601String(), $array['created_at']);
        $this->assertEquals($updatedAt->toIso8601String(), $array['updated_at']);
    }
}
