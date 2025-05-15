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

        $product = Product::factory()->create(['id' => 200]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        // Panier initial : 3 unités
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // Ajoute 2 de plus → total doit passer à 5
        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 5])
            ->assertNoContent();
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        // Réduit à 1 → total 1
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
}
