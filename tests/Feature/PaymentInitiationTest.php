<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;
use Mockery;
use Stripe\PaymentIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    public function testStoreReturns201WithExpectedJson()
    {
        // 1. Prépare user, product, cart, cartItem et token
        $user    = User::factory()->create();
        $token   = $user->createToken('test')->plainTextToken;
        $product = Product::factory()->create();                // ← Création du produit
        $cart    = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()
            ->for($cart)
            ->create([
                'quantity'   => 1,
                'product_id' => $product->id,                    // ← on utilise son ID
            ]);

        // 2. Construis un vrai PaymentIntent “factice”
        $fakeIntent = PaymentIntent::constructFrom(
            [
                'id'            => 'pi_456',
                'client_secret' => 'cs_def',
            ],
            'sk_test_dummy'    // la clé API factice
        );

        // 2a) Mocke le service interne “paymentIntents”
        $intentService = Mockery::mock();
        $intentService
            ->shouldReceive('create')
            ->once()
            ->andReturn($fakeIntent);

        // 2b) Partial mock du StripeClient pour stubber getService('paymentIntents')
        $stripeMock = Mockery::mock(StripeClient::class)->makePartial();
        $stripeMock
            ->shouldReceive('getService')
            ->with('paymentIntents')
            ->andReturn($intentService);

        // Remplace dans le container
        $this->app->instance(StripeClient::class, $stripeMock);

        // 3. Appel POST /api/payments (avec les bons headers)
        $response = $this
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/payments', [
                'cart_id'        => $cart->id,
                'payment_method' => 'stripe',
            ]);

        // 4. Assertions
        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['uuid','status','transaction_id','client_secret']
            ])
            ->assertJsonPath('data.transaction_id', 'pi_456')
            ->assertJsonPath('data.client_secret', 'cs_def')
            ->assertJsonPath('data.status', 'pending');
    }
}
