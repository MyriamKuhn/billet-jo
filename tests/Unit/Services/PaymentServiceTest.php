<?php

namespace Tests\Unit\Services;

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
        // Préparation de l’utilisateur, du produit et du panier
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'sale' => 0]);
        $cart    = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->for($cart)->create([
            'quantity'   => 2,
            'product_id' => $product->id,
        ]);

        // On fabrique un PaymentIntent factice
        $fakeIntent = PaymentIntent::constructFrom([
            'id'            => 'pi_123',
            'client_secret' => 'cs_abc',
        ], 'sk_test');

        // Mock du client Stripe
        $stripe = $this->makeStripeClientMock($fakeIntent, null);

        // Mock du CartService pour qu’il ne vérifie rien (stock toujours dispo)
        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService
            ->shouldReceive('assertStockAvailable')
            ->once()
            ->andReturnNull();

        // Instanciation du service avec les deux dépendances
        $svc     = new PaymentService($stripe, $cartService);

        // Exécution
        $payment = $svc->createFromCart($user->id, $cart->id, 'stripe');

        // Vérifications
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals('pi_123',  $payment->transaction_id);
        $this->assertEquals('cs_abc',  $payment->client_secret);
        $this->assertDatabaseHas('payments', [
            'id'             => $payment->id,
            'user_id'        => $user->id,
            'payment_method' => 'stripe',
        ]);

        Mockery::close();
    }

    public function testMarkAsPaidByUuidAndByIntent()
    {
        // 1) Crée un paiement en pending avec un transaction_id
        $payment = Payment::factory()->create([
            'status'         => PaymentStatus::Pending,
            'transaction_id' => 'pi_999',
        ]);

        // 2) Instancie un StripeClient factice (ici on n’en a pas besoin réellement)
        $stripeClient = new StripeClient('sk_test');

        // 3) Fournit un CartService bidon
        $cartService = \Mockery::mock(\App\Services\CartService::class);
        // pas d’appel attendu sur assertStockAvailable ici

        // 4) Crée le service avec les deux dépendances
        $svc = new PaymentService($stripeClient, $cartService);

        // --- Test markAsPaidByUuid ---
        $paid = $svc->markAsPaidByUuid($payment->uuid);
        $this->assertEquals(PaymentStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);

        // Remet le paiement en pending pour la 2ᵉ partie
        $payment->update(['status' => PaymentStatus::Pending, 'paid_at' => null]);

        // --- Test markAsPaidByIntentId ---
        $svc->markAsPaidByIntentId('pi_999');
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Paid, $payment->status);

        Mockery::close();
    }

    public function testMarkAsFailedByIntentId()
    {
        // 1) Prépare un paiement en Pending
        $payment = Payment::factory()->create([
            'status'         => PaymentStatus::Pending,
            'transaction_id' => 'pi_fail',
        ]);

        // 2) Crée un StripeClient factice
        $stripeClient = new StripeClient('sk_test');

        // 3) Fournit un CartService bidon (non utilisé ici)
        $cartService = Mockery::mock(\App\Services\CartService::class);

        // 4) Instancie le service avec les deux dépendances
        $svc = new PaymentService($stripeClient, $cartService);

        // 5) Exécute la méthode sous test
        $svc->markAsFailedByIntentId('pi_fail');

        // 6) Rafraîchit et vérifie
        $payment->refresh();
        $this->assertEquals(PaymentStatus::Failed, $payment->status);

        Mockery::close();
    }

    public function testPaginateAppliesFilters()
    {
        // 1) Création de deux users
        $user1 = User::factory()->create(['email' => 'foo@example.com']);
        $user2 = User::factory()->create(['email' => 'bar@example.com']);

        // 2) Création de paiements associées
        Payment::factory()->count(3)->create([
            'user_id' => $user1->id,
            'status'  => PaymentStatus::Paid,
        ]);
        Payment::factory()->count(2)->create([
            'user_id' => $user2->id,
            'status'  => PaymentStatus::Pending,
        ]);

        // 3) Instanciation du service avec un StripeClient et un CartService factice
        $stripeClient = new StripeClient('sk_test');
        $cartService  = Mockery::mock(\App\Services\CartService::class);
        $svc          = new PaymentService($stripeClient, $cartService);

        // 4) Filtre par status “paid”
        $page = $svc->paginate(
            ['status' => PaymentStatus::Paid->value],
            'created_at',
            'asc',
            10
        );

        $this->assertCount(3, $page->items());
        foreach ($page->items() as $p) {
            $this->assertEquals(PaymentStatus::Paid, $p->status);
        }

        // 5) Recherche globale par email
        $page2 = $svc->paginate(
            ['q' => 'bar@example.com'],
            'created_at',
            'desc',
            10
        );
        $this->assertCount(2, $page2->items());

        Mockery::close();
    }

    public function testRefundByUuidPartialAndFull()
    {
        // 1) Création du paiement initial
        $payment = Payment::factory()->create([
            'amount'          => 100.00,
            'refunded_amount' => 0.00,
            'status'          => PaymentStatus::Paid,
            'transaction_id'  => 'pi_refund',
        ]);

        // 2) Fake du refund Stripe
        $fakeRefund = Refund::constructFrom(['id' => 're_123'], 'sk_test');
        $stripe     = $this->makeStripeClientMock(null, $fakeRefund);

        // 3) Stub de CartService (inutile ici, mais nécessaire au constructeur)
        $cartService = Mockery::mock(\App\Services\CartService::class);

        // 4) Instanciation correcte du service
        $svc = new PaymentService($stripe, $cartService);

        // 5) Remboursement partiel
        $updated = $svc->refundByUuid($payment->uuid, 30.00);
        $this->assertEquals(30.00, $updated->refunded_amount);
        $this->assertEquals(PaymentStatus::Paid, $updated->status);

        // 6) Remboursement total
        $final = $svc->refundByUuid($payment->uuid, 100.00);
        $this->assertEquals(100.00, $final->refunded_amount);
        $this->assertEquals(PaymentStatus::Refunded, $final->status);

        Mockery::close();
    }

    public function testPaginateAppliesDateFilters()
    {
        // 1) Paiements à différentes dates
        Payment::factory()->create(['created_at' => now()->subDays(5)]);
        Payment::factory()->create(['created_at' => now()->subDays(3)]);
        Payment::factory()->create(['created_at' => now()->subDay()]);

        // 2) On fake/mocque le CartService (inutile ici, juste pour satisfaire le constructeur)
        $cartService = \Mockery::mock(\App\Services\CartService::class);

        // 3) Instanciation correcte du service
        $svc = new PaymentService(new StripeClient('sk_test'), $cartService);

        // 4) Filtres de date
        $dateFrom = now()->subDays(4)->toDateString();
        $dateTo   = now()->subDays(2)->toDateString();

        $page = $svc->paginate([
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ], 'created_at', 'asc', 10);

        // 5) Assertions
        $this->assertCount(1, $page->items());
        $this->assertTrue(
            $page->items()[0]->created_at->between(
                now()->subDays(4)->startOfDay(),
                now()->subDays(2)->endOfDay()
            )
        );

        Mockery::close();
    }

    public function testPaginateAppliesAmountFilters()
    {
        // 1) Crée trois paiements à différents montants
        Payment::factory()->create(['amount' => 50.00]);
        Payment::factory()->create(['amount' => 100.00]);
        Payment::factory()->create(['amount' => 200.00]);

        // 2) On mocke un CartService (inutile pour paginate, mais requis par le constructeur)
        $cartService = \Mockery::mock(\App\Services\CartService::class);

        // 3) Instanciation correcte du PaymentService avec StripeClient et CartService
        $svc = new PaymentService(
            new StripeClient('sk_test'),
            $cartService
        );

        // 4) Filtre les montants entre 60 et 150
        $page = $svc->paginate([
            'amount_min' => 60.00,
            'amount_max' => 150.00,
        ], 'amount', 'asc', 10);

        // 5) Seul le paiement à 100 € doit passer
        $this->assertCount(1, $page->items());
        $this->assertEquals(100.00, (float) $page->items()[0]->amount);

        Mockery::close();
    }

    public function testCreateFromCartReturnsExistingPendingWithoutCallingStripe()
    {
        // 1) Crée un utilisateur et un paiement Pending existant avec cart_id = 999
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id'       => $user->id,
            'status'        => PaymentStatus::Pending,
            'cart_snapshot' => ['cart_id' => 999],
        ]);

        // 2) Prépare un mock de StripeClient (inutile ici car on ne doit pas l'appeler)
        $stripe = $this->makeStripeClientMock(); // Votre helper existant

        // 3) Mock minimaliste de CartService (non utilisé pour ce chemin)
        $cartService = Mockery::mock(\App\Services\CartService::class);

        // 4) Instancie le service avec les deux dépendances
        $svc = new PaymentService($stripe, $cartService);

        // 5) Appelle la méthode en passant le même cart_id qui existe déjà
        $returned = $svc->createFromCart($user->id, 999, 'stripe');

        // 6) S'assure qu'on récupère bien l'instance existante
        $this->assertTrue($returned->is($payment));

        Mockery::close();
    }

    public function testCreateFromCartHandlesStripeApiErrorException()
    {
        // 1) Préparation : user, product, cart, cart item
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 10, 'sale' => 0]);
        $cart    = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->for($cart)->create([
            'quantity'   => 1,
            'product_id' => $product->id,
        ]);

        // 2) Mock du CartService
        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService
            ->shouldReceive('assertStockAvailable')
            ->once()
            ->with(Mockery::type(\App\Models\Cart::class));

        // 3) Préparation du mock de ApiErrorException
        $stripeError = Mockery::mock(\Stripe\Exception\ApiErrorException::class);

        // 4) Mock du service paymentIntents
        $intentSvc = Mockery::mock();
        $intentSvc
            ->shouldReceive('create')
            ->once()
            ->andThrow($stripeError);

        // 5) Mock du StripeClient en passant par makePartial pour conserver le constructeur
        $stripe = Mockery::mock(StripeClient::class, ['sk_test'])->makePartial();
        // On injecte la propriété paymentIntents
        $stripe->paymentIntents = $intentSvc;

        // 6) Instanciation du service
        $svc = new PaymentService($stripe, $cartService);

        // 7) On s'attend à une HttpException 502
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Payment gateway error, please try again later');

        try {
            $svc->createFromCart($user->id, $cart->id, 'stripe');
        } catch (HttpException $e) {
            // Vérifie qu’aucun paiement n’a été persisté
            $this->assertDatabaseCount('payments', 0);
            // Et que c’est bien un 502
            $this->assertEquals(502, $e->getStatusCode());
            throw $e;
        } finally {
            Mockery::close();
        }
    }

    public function testCreateFromCartHandlesUnexpectedException()
    {
        // 1) Prépare un utilisateur sans panier valide
        $user = User::factory()->create();
        $invalidCartId = 999;

        // 2) StripeClient « réel »
        $stripe = new StripeClient('sk_test');

        // 3) Mock du CartService (qui ne doit pas être appelé ici)
        $cartService = Mockery::mock(\App\Services\CartService::class);
        $cartService->shouldNotReceive('assertStockAvailable');

        // 4) Instanciation du service avec les deux dépendances
        $svc = new PaymentService($stripe, $cartService);

        // 5) On attend une HttpException 500 avec le bon message
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Internal payment error');
        // **Ne pas** faire expectExceptionCode(500)

        try {
            $svc->createFromCart($user->id, $invalidCartId, 'stripe');
        } catch (HttpException $e) {
            // On vérifie ici le status code HTTP
            $this->assertEquals(500, $e->getStatusCode(), 'Le code de statut doit être 500');
            // Et qu’aucun paiement n’a été créé
            $this->assertDatabaseCount('payments', 0);
            throw $e;
        } finally {
            Mockery::close();
        }
    }

    public function testFindByUuidForUserReturnsPayment()
    {
        // 1) Préparation des données
        $user    = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'status'  => PaymentStatus::Paid,
        ]);

        // 2) Instanciation d’un CartService factice (non utilisé dans findByUuidForUser)
        $cartServiceMock = Mockery::mock(\App\Services\CartService::class);

        // 3) Création du service avec ses deux dépendances
        $svc = new \App\Services\PaymentService(
            new StripeClient('sk_test'),
            $cartServiceMock
        );

        // 4) Exécution et assertions
        $found = $svc->findByUuidForUser($payment->uuid, $user->id);

        $this->assertInstanceOf(Payment::class, $found);
        $this->assertTrue($found->is($payment));

        Mockery::close();
    }

    public function testFindByUuidForUserThrowsWhenNotFound()
    {
        $user    = User::factory()->create();
        // On crée un paiement pour un autre user
        $otherPayment = Payment::factory()->create();

        // 1) Mock minimal de CartService (non utilisé ici)
        $cartServiceMock = Mockery::mock(\App\Services\CartService::class);

        // 2) Instanciation du service avec ses deux dépendances
        $svc = new PaymentService(
            new StripeClient('sk_test'),
            $cartServiceMock
        );

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // 3) Appel : devrait lancer ModelNotFoundException
        $svc->findByUuidForUser($otherPayment->uuid, $user->id);

        Mockery::close();
    }

    public function testMarkAsPaidOnlyTransitionsFromPending()
    {
        // 1) Cas où le paiement est en Pending
        $payment = Payment::factory()->create([
            'status'  => PaymentStatus::Pending,
            'paid_at' => null,
        ]);

        // On mocke un CartService (non utilisé ici)
        $cartServiceMock = Mockery::mock(\App\Services\CartService::class);

        // On instancie avec StripeClient et CartService
        $svc = new PaymentService(
            new StripeClient('sk_test'),
            $cartServiceMock
        );

        $svc->markAsPaid($payment);
        $payment->refresh();

        $this->assertEquals(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        // 2) Cas où le paiement n'est PAS en Pending (déjà Paid)
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

        Mockery::close();
    }

    public function testRefundByUuidHandlesStripeApiErrorException()
    {
        // 1) Prépare un paiement déjà en “paid”
        $payment = Payment::factory()->create([
            'amount'          => 100.00,
            'refunded_amount' => 0.00,
            'status'          => PaymentStatus::Paid,
            'transaction_id'  => 'pi_refund_error',
        ]);

        // 2) Crée une exception concrète pour Stripe\Exception\ApiErrorException
        if (! class_exists(DummyStripeException::class)) {
            eval('
                namespace Stripe\Exception;
                class DummyStripeException extends ApiErrorException {}
            ');
        }

        // 3) Mock du StripeClient pour que refunds->create jette l’exception
        $stripe = Mockery::mock(\Stripe\StripeClient::class)->makePartial();
        $refundSvc = Mockery::mock();
        $refundSvc->shouldReceive('create')
                ->once()
                ->andThrow(new DummyStripeException('Refund failed'));
        // selon la version de stripe-php, utilisez getService ou property refunds
        $stripe->shouldReceive('getService')
            ->with('refunds')
            ->andReturn($refundSvc);

        // 4) Mock du CartService (non utilisé par refundByUuid)
        $cartServiceMock = Mockery::mock(\App\Services\CartService::class);

        // 5) Injection des deux dépendances
        $svc = new \App\Services\PaymentService($stripe, $cartServiceMock);

        // 6) On s'attend à un HttpException 502
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Payment gateway error, please try again later.');

        try {
            $svc->refundByUuid($payment->uuid, 50.00);
        } catch (HttpException $e) {
            // 7) Vérifie que rien n’a été persité / mis à jour
            $payment->refresh();
            $this->assertEquals(0.00, $payment->refunded_amount);
            $this->assertEquals(PaymentStatus::Paid, $payment->status);
            $this->assertEquals(502, $e->getStatusCode());
            throw $e;
        } finally {
            Mockery::close();
        }
    }
}
