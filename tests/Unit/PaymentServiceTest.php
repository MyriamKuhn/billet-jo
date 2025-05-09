<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\Payment;
use App\Services\PaymentService;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Exception\ApiErrorException;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Stripe\Exception\InvalidRequestException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// Dummy concrete subclass to allow instantiation
class DummyStripeException extends ApiErrorException {}

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeStripeClientMock($intentReturn = null, $refundReturn = null)
    {
        $intentSvc = Mockery::mock();
        if ($intentReturn !== null) {
            $intentSvc->shouldReceive('create')->once()->andReturn($intentReturn);
        }

        $refundSvc = Mockery::mock();
        if ($refundReturn !== null) {
            // Autorise deux appels à create()
            $refundSvc->shouldReceive('create')
                    ->twice()
                    ->andReturn($refundReturn);
        }

        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $stripe->shouldReceive('getService')
            ->with('paymentIntents')
            ->andReturn($intentSvc);
        $stripe->shouldReceive('getService')
            ->with('refunds')
            ->andReturn($refundSvc);

        return $stripe;
    }

    public function testCreateFromCartStoresPaymentAndCallsStripe()
    {
        // Setup
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'sale' => 0]);
        $cart    = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->for($cart)->create([
            'quantity'   => 2,
            'product_id' => $product->id,
        ]);

        $fakeIntent = PaymentIntent::constructFrom([
            'id'            => 'pi_123',
            'client_secret' => 'cs_abc',
        ], 'sk_test');

        $stripe = $this->makeStripeClientMock($fakeIntent, null);

        // Execute
        $svc     = new PaymentService($stripe);
        $payment = $svc->createFromCart($user->id, $cart->id, 'stripe');

        // Assertions
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals('pi_123',  $payment->transaction_id);
        $this->assertEquals('cs_abc',  $payment->client_secret);
        $this->assertDatabaseHas('payments', [
            'id'             => $payment->id,
            'user_id'        => $user->id,
            'payment_method' => 'stripe',
        ]);
    }

    public function testMarkAsPaidByUuidAndByIntent()
    {
        $payment = Payment::factory()->create([
            'status'         => PaymentStatus::Pending,
            'transaction_id' => 'pi_999'
        ]);

        $svc = new PaymentService(new StripeClient('sk_test'));

        // By UUID
        $paid = $svc->markAsPaidByUuid($payment->uuid);
        $this->assertEquals(PaymentStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);

        // Reset status for next test
        $payment->update(['status' => PaymentStatus::Pending, 'paid_at' => null]);

        // By Intent ID
        $svc->markAsPaidByIntentId('pi_999');
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Paid, $payment->status);
    }

    public function testMarkAsFailedByIntentId()
    {
        $payment = Payment::factory()->create([
            'status'         => PaymentStatus::Pending,
            'transaction_id' => 'pi_fail'
        ]);

        $svc = new PaymentService(new StripeClient('sk_test'));
        $svc->markAsFailedByIntentId('pi_fail');

        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);
    }

    public function testPaginateAppliesFilters()
    {
        $user1 = User::factory()->create(['email' => 'foo@example.com']);
        $user2 = User::factory()->create(['email' => 'bar@example.com']);

        Payment::factory()->count(3)->create(['user_id' => $user1->id, 'status' => PaymentStatus::Paid]);
        Payment::factory()->count(2)->create(['user_id' => $user2->id, 'status' => PaymentStatus::Pending]);

        $svc = new PaymentService(new StripeClient('sk_test'));
        $page = $svc->paginate(['status' => PaymentStatus::Paid->value], 'created_at', 'asc', 10);

        $this->assertCount(3, $page->items());
        foreach ($page->items() as $p) {
            $this->assertEquals(PaymentStatus::Paid, $p->status);
        }

        // Global search by email
        $page2 = $svc->paginate(['q' => 'bar@example.com'], 'created_at', 'desc', 10);
        $this->assertCount(2, $page2->items());
    }

    public function testRefundByUuidPartialAndFull()
    {
        $payment = Payment::factory()->create([
            'amount'            => 100.00,
            'refunded_amount'   => 0.00,
            'status'            => PaymentStatus::Paid,
            'transaction_id'    => 'pi_refund'
        ]);

        // Fake Stripe refund object
        $fakeRefund = Refund::constructFrom(['id'=>'re_123'], 'sk_test');
        $stripe     = $this->makeStripeClientMock(null, $fakeRefund);

        $svc = new PaymentService($stripe);

        // Partial refund
        $updated = $svc->refundByUuid($payment->uuid, 30.00);
        $this->assertEquals(30.00, $updated->refunded_amount);
        $this->assertEquals(PaymentStatus::Paid, $updated->status);

        // Full refund
        $final = $svc->refundByUuid($payment->uuid, 100.00);
        $this->assertEquals(100.00, $final->refunded_amount);
        $this->assertEquals(PaymentStatus::Refunded, $final->status);
    }

    public function testPaginateAppliesDateFilters()
    {
        // Crée trois paiements à différentes dates
        Payment::factory()->create(['created_at' => now()->subDays(5)]);
        Payment::factory()->create(['created_at' => now()->subDays(3)]);
        Payment::factory()->create(['created_at' => now()->subDay()]);

        $svc = new PaymentService(new StripeClient('sk_test'));

        // Filtre entre -4 jours et -2 jours
        $dateFrom = now()->subDays(4)->toDateString(); // e.g. 2025-05-05
        $dateTo   = now()->subDays(2)->toDateString(); // e.g. 2025-05-07

        $page = $svc->paginate([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ], 'created_at', 'asc', 10);

        // Seul le paiement créé il y a 3 jours doit passer
        $this->assertCount(1, $page->items());
        $this->assertTrue(
            $page->items()[0]->created_at->between(
                now()->subDays(4)->startOfDay(),
                now()->subDays(2)->endOfDay()
            )
        );
    }

    public function testPaginateAppliesAmountFilters()
    {
        // Crée trois paiements à différents montants
        Payment::factory()->create(['amount' => 50.00]);
        Payment::factory()->create(['amount' => 100.00]);
        Payment::factory()->create(['amount' => 200.00]);

        $svc = new PaymentService(new StripeClient('sk_test'));

        // Filtre les montants entre 60 et 150
        $page = $svc->paginate([
            'amount_min' => 60.00,
            'amount_max' => 150.00,
        ], 'amount', 'asc', 10);

        // Seul le paiement à 100 € doit passer
        $this->assertCount(1, $page->items());
        $this->assertEquals(100.00, (float) $page->items()[0]->amount);
    }

    public function testCreateFromCartReturnsExistingPendingWithoutCallingStripe()
    {
        $user    = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status'  => PaymentStatus::Pending,
            'cart_snapshot' => ['cart_id' => 999]
        ]);

        // On passe un cartId qui matche le snapshot du paiement
        $stripe = $this->makeStripeClientMock();

        $svc = new PaymentService($stripe);
        $returned = $svc->createFromCart($user->id, 999, 'stripe');

        $this->assertTrue($returned->is($payment));
    }

    public function testCreateFromCartHandlesStripeApiErrorException()
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price'=>10,'sale'=>0]);
        $cart    = Cart::factory()->create(['user_id'=>$user->id]);
        CartItem::factory()->for($cart)->create(['quantity'=>1,'product_id'=>$product->id]);

        // Mock StripeClient so that paymentIntents->create throws our dummy exception
        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $intentSvc = Mockery::mock();
        $intentSvc->shouldReceive('create')
                    ->once()
                    ->andThrow(new DummyStripeException('Stripe down'));
        $stripe->shouldReceive('getService')
                ->with('paymentIntents')
                ->andReturn($intentSvc);

        $svc = new PaymentService($stripe);

        // Expect a HttpException 502 with our abort message
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Payment gateway error, please try again later.');

        try {
            $svc->createFromCart($user->id, $cart->id, 'stripe');
        } catch (HttpException $e) {
            // Vérifier qu'aucun paiement n’a été persité (rollback total)
            $this->assertDatabaseCount('payments', 0);
            $this->assertEquals(Response::HTTP_BAD_GATEWAY, $e->getStatusCode());
            throw $e;
        }
    }

    public function testCreateFromCartHandlesUnexpectedException()
    {
        $user = User::factory()->create();
        // Aucun panier n'existe pour l'utilisateur, on utilise un ID au hasard
        $invalidCartId = 999;

        $stripe = new StripeClient('sk_test');
        $svc = new PaymentService($stripe);

        // On s'attend à un HttpException 500
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Internal payment error');

        try {
            $svc->createFromCart($user->id, $invalidCartId, 'stripe');
        } catch (HttpException $e) {
            // Vérifier qu'aucun paiement n’a été créé
            $this->assertDatabaseCount('payments', 0);
            $this->assertEquals(500, $e->getStatusCode());
            throw $e;
        }
    }

    public function testFindByUuidForUserReturnsPayment()
    {
        $user    = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status'  => PaymentStatus::Paid,
        ]);

        $svc = new PaymentService(new StripeClient('sk_test'));
        $found = $svc->findByUuidForUser($payment->uuid, $user->id);

        $this->assertInstanceOf(Payment::class, $found);
        $this->assertTrue($found->is($payment));
    }

    public function testFindByUuidForUserThrowsWhenNotFound()
    {
        $user    = User::factory()->create();
        // On crée un paiement pour un autre user
        $otherPayment = Payment::factory()->create();

        $svc = new PaymentService(new StripeClient('sk_test'));

        $this->expectException(ModelNotFoundException::class);

        // UUID non associé à $user
        $svc->findByUuidForUser($otherPayment->uuid, $user->id);
    }

    public function testMarkAsPaidOnlyTransitionsFromPending()
    {
        // 1) Cas où le paiement est en Pending
        $payment = Payment::factory()->create([
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);

        $svc = new PaymentService(new StripeClient('sk_test'));

        $svc->markAsPaid($payment);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        // 2) Cas où le paiement n'est PAS en Pending (par ex. already Paid)
        $paidPayment = Payment::factory()->create([
            'status'  => PaymentStatus::Paid,
            'paid_at' => now()->subDay(),
        ]);

        $originalPaidAt = $paidPayment->paid_at;

        $svc->markAsPaid($paidPayment);
        $paidPayment->refresh();

        // Le statut et paid_at ne changent pas
        $this->assertEquals(PaymentStatus::Paid, $paidPayment->status);
        $this->assertTrue($paidPayment->paid_at->equalTo($originalPaidAt));
    }

    public function testRefundByUuidHandlesStripeApiErrorException()
    {
        // 1) Prépare un paiement déjà en “paid”
        $payment = Payment::factory()->create([
            'amount'         => 100.00,
            'refunded_amount'=> 0.00,
            'status'         => PaymentStatus::Paid,
            'transaction_id' => 'pi_refund_error',
        ]);

        // 2) Mock StripeClient pour que refunds->create jette une exception concrète
        //    Dummy concrete subclass to allow instantiation
        if (! class_exists('DummyStripeException')) {
            eval('
                namespace Stripe\Exception;
                class DummyStripeException extends ApiErrorException {}
            ');
        }

        $stripe = Mockery::mock(StripeClient::class)->makePartial();
        $refundSvc = Mockery::mock();
        $refundSvc->shouldReceive('create')
                ->once()
                ->andThrow(new \Stripe\Exception\DummyStripeException('Refund failed'));
        $stripe->shouldReceive('getService')
            ->with('refunds')
            ->andReturn($refundSvc);

        $svc = new PaymentService($stripe);

        // 3) On s'attend à un HttpException 502
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Payment gateway error, please try again later.');

        try {
            $svc->refundByUuid($payment->uuid, 50.00);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // 4) Vérifie que rien n’a été persité / mis à jour
            $payment->refresh();
            $this->assertEquals(0.00, $payment->refunded_amount);
            $this->assertEquals(PaymentStatus::Paid, $payment->status);
            $this->assertEquals(502, $e->getStatusCode());
            throw $e;
        }
    }
}
