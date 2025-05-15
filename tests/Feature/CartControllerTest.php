<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Redis;
use Mockery;
use App\Services\CartService;
use Illuminate\Support\Collection;

class CartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testShowAsGuestReturnsEmptyGuestCart()
    {
        $this->getJson('/api/cart')
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    'cart_items' => [],
                ]
            ]);
    }

    public function testShowAsAuthenticatedUserReturnsMinimalCart()
    {
        // 1) Crée un utilisateur et un panier avec deux produits
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $p1 = Product::factory()->create();
        $p2 = Product::factory()->create();

        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $p1->id,
            'quantity'   => 2,
        ]);
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $p2->id,
            'quantity'   => 5,
        ]);

        // 2) Appelle l’API en tant qu'utilisateur
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $cart->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonCount(2, 'data.cart_items')
             // vérifie la structure d’un item
            ->assertJsonStructure([
                'data' => [
                    'cart_items' => [
                        '*' => [
                            'id',
                            'product_id',   // passe désormais
                            'quantity',
                            'unit_price',
                            'total_price',
                            'product' => [
                                'name',
                                'image',
                                'date',
                                'time',
                                'location',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function testUpdateItemGuestAddsToRedisAndSetsGuestCartIdInSession()
    {
        // 1) On "fake" Redis pour intercepter les commandes
        Redis::spy();

        // Création d'un produit
        $product = Product::factory()->create(['id' => 100]);

        // 2) On démarre la session vide
        $response = $this->withSession([])
                        ->patchJson("/api/cart/items/{$product->id}", [
                            'quantity' => 4
                        ]);

        // 3) On vérifie le code HTTP et le JSON vide
        $response->assertStatus(204);

        // 4) On récupère le guest_cart_id en session
        $guestId = session('guest_cart_id');
        $this->assertNotNull($guestId, 'La session devrait contenir guest_cart_id');

        // 5) On reconstruit la clé Redis attendue
        $expectedKey = "cart:guest:{$guestId}";

        // 6) On s’assure que Redis::hincrby a été appelé avec la bonne clé et la bonne quantité
        Redis::shouldHaveReceived('hincrby')
            ->once()
            ->with($expectedKey, (string) $product->id, 4);

        // 7) Et que TTL a été réinitialisé
        Redis::shouldHaveReceived('expire')
            ->once()
            ->with($expectedKey, Mockery::type('int'));
    }

    public function testUpdateItemGuestDecrementsAndDeletesInRedis()
    {
        // Produit factice
        $product = Product::factory()->create(['id' => 101]);

        // 1) Initialise une session pour créer guest_cart_id
        $this->withSession([])->getJson('/api/cart');
        $guestId = session('guest_cart_id');
        $key     = "cart:guest:{$guestId}";

        // 2) Pré-remplit Redis : 10 unités
        Redis::hset($key, (string) $product->id, 10);
        // Vérifie que le TTL est par défaut à -1 (pas de TTL)
        $this->assertEquals(-1, Redis::ttl($key));

        // 3) Décrémente à 6 (delta = -4)
        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 6])
            ->assertNoContent();

        // 4) Vérifie la nouvelle quantité
        $this->assertEquals(
            6,
            (int) Redis::hget($key, (string) $product->id)
        );

        // 5) Vérifie que le TTL a été mis à jour (strictement positif)
        $this->assertGreaterThan(0, Redis::ttl($key));

        // 6) Passe à quantity = 0 → suppression
        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 0])
            ->assertNoContent();

        // 7) Vérifie que l’item a été supprimé
        $this->assertNull(Redis::hget($key, (string) $product->id));
        // (Optionnel) Vérifiez que la clé existe toujours ou non, selon votre implémentation
    }

    public function testUpdateItemUserAddsAndRemovesInDatabase()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // on précise un stock suffisant pour la montée à 5 unités
        $product = Product::factory()->create([
            'id'             => 200,
            'stock_quantity' => 10,
        ]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        // Panier initial : 3 unités
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // Maintenant on passe à 5, et le stock est suffisant
        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 5])
            ->assertNoContent();
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        // Puis on redescend à 1
        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 1])
            ->assertNoContent();
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);
    }

    public function testClearCartRemovesAllItemsForAuthenticatedUser()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        // Crée 3 items
        CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

        $this->deleteJson('/api/cart/items')
            ->assertNoContent();

        // Tous les items doivent avoir disparu
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function testShowAsGuestMapsItemsCorrectlyWithoutDiscount()
    {
        // 1) On monte localement un mock de CartService
        $mock = Mockery::mock(CartService::class);
        $this->app->instance(CartService::class, $mock);

        // 2) Crée un produit sans remise, stock OK
        $product = Product::factory()->create([
            'price'           => 20.00,
            'sale'            => null,
            'stock_quantity'  => 10,
            'product_details' => [
                'image'    => 'https://example.com/img.jpg',
                'date'     => '2025-09-10',
                'time'     => '14:00',
                'location' => 'Hall C',
            ],
        ]);

        // 3) On définit le comportement du mock
        $mock->shouldReceive('getCurrentCart')
            ->once()
            ->andReturn([ $product->id => 3 ]);

        // 4) On appelle l’endpoint
        $response = $this->getJson('/api/cart');

        // 5) Assertions sur la structure et les calculs
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.cart_items');

        $item = $response->json('data.cart_items.0');

        $this->assertEquals($product->id,       $item['product_id']);
        $this->assertEquals(3,                  $item['quantity']);
        $this->assertTrue($item['in_stock']);
        $this->assertEquals(10,                 $item['available_quantity']);
        $this->assertEquals(20.00,              $item['unit_price']);
        $this->assertEquals(60.00,              $item['total_price']);
        $this->assertNull($item['original_price']);
        $this->assertNull($item['discount_rate']);
        $this->assertEquals('Hall C',           $item['product']['location']);
        $this->assertEquals('https://example.com/img.jpg', $item['product']['image']);
    }

    public function testShowAsGuestMapsItemsCorrectlyWithDiscountAndOutOfStock()
    {
        // 1) Mock local de CartService
        $mock = Mockery::mock(CartService::class);
        $this->app->instance(CartService::class, $mock);

        // 2) Produit avec remise, stock insuffisant
        $product = Product::factory()->create([
            'price'           => 100.00,
            'sale'            => 0.25,
            'stock_quantity'  => 1,
            'product_details' => [
                'image'    => 'https://example.com/img2.jpg',
                'date'     => '2025-10-01',
                'time'     => '19:30',
                'location' => 'Arena D',
            ],
        ]);

        // 3) On définit le comportement du mock
        $mock->shouldReceive('getCurrentCart')
            ->once()
            ->andReturn([ $product->id => 5 ]);

        // 4) Appel à l’API
        $response = $this->getJson('/api/cart');

        // 5) Assertions
        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.cart_items');

        $item = $response->json('data.cart_items.0');

        $this->assertEquals($product->id,       $item['product_id']);
        $this->assertEquals(5,                  $item['quantity']);
        $this->assertFalse($item['in_stock']);
        $this->assertEquals(1,                  $item['available_quantity']);
        $this->assertEquals(75.00,              $item['unit_price']);
        $this->assertEquals(375.00,             $item['total_price']);
        $this->assertEquals(100.00,             $item['original_price']);
        $this->assertEquals(0.25,               $item['discount_rate']);
        $this->assertEquals('Arena D',          $item['product']['location']);
        $this->assertEquals('https://example.com/img2.jpg', $item['product']['image']);
    }
}
