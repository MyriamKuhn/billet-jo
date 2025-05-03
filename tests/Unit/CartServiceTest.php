<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\CartService;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Predis\Connection\ConnectionException as RedisConnectionException;
use InvalidArgumentException;
use Illuminate\Database\QueryException;
use Predis\Connection\NodeConnectionInterface;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CartService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Swap Redis facade par un fake en mémoire
        $fakeRedis = new class {
            private array $store = [];

            public function hgetall(string $key): array
            {
                $items = $this->store[$key] ?? [];
                // Laravel Redis renvoie toujours des string values
                return array_map('strval', $items);
            }

            public function hincrby(string $key, $field, int $increment): int
            {
                $field = (string)$field;
                if (! isset($this->store[$key])) {
                    $this->store[$key] = [];
                }
                $this->store[$key][$field] = ($this->store[$key][$field] ?? 0) + $increment;
                return $this->store[$key][$field];
            }

            public function hset(string $key, $field, $value): int
            {
                $field = (string)$field;
                if (! isset($this->store[$key])) {
                    $this->store[$key] = [];
                }
                $this->store[$key][$field] = $value;
                return 1;
            }

            public function hget(string $key, $field): ?string
            {
                $field = (string)$field;
                if (! isset($this->store[$key][$field])) {
                    return null;
                }
                return (string)$this->store[$key][$field];
            }

            public function expire(string $key, $ttl): bool
            {
                return true;
            }

            public function del(string $key): int
            {
                if (isset($this->store[$key])) {
                    unset($this->store[$key]);
                    return 1;
                }
                return 0;
            }

            public function flushall(): bool
            {
                $this->store = [];
                return true;
            }
        };
        Redis::swap($fakeRedis);

        // Démarre la session pour guestKey()
        Session::start();

        // Instancie le service
        $this->service = app(CartService::class);
    }

    public function testGetCurrentCartReturnsGuestCartWhenNotAuthenticated(): void
    {
        Auth::shouldReceive('check')->andReturn(false);

        // Simule un guest_cart_id et des items
        Session::put('guest_cart_id', 'guest-123');
        Redis::hset('cart:guest:guest-123', 1, 2);

        $cart = $this->service->getCurrentCart();

        $this->assertIsArray($cart);
        $this->assertArrayHasKey('1', $cart);           // clé = '1'
        $this->assertSame('2', $cart['1']);             // valeur string '2'
    }

    public function testGetCurrentCartReturnsUserCartWhenAuthenticated(): void
    {
        $user = User::factory()->create();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $cart = $this->service->getCurrentCart();

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);
    }

    public function testAddItemThrowsExceptionForInvalidQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->addItem(1, 0);
    }

    public function testAddItemUpdatesRedisForGuest(): void
    {
        Auth::shouldReceive('check')->andReturn(false);

        // On ajoute 3 units du produit 5
        $this->service->addItem(5, 3);

        // Vérifie bien le stockage en Redis
        $guestId = Session::get('guest_cart_id');
        $this->assertNotNull($guestId);
        $qty = Redis::hget('cart:guest:' . $guestId, 5);
        $this->assertSame('3', $qty);
    }

    public function testAddItemPersistsToDbForAuthenticatedUser(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create();  // 👈 on crée d’abord un produit

        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        // Ajout en deux fois
        $this->service->addItem($product->id, 1);
        $this->service->addItem($product->id, 2);

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $item = $cart->cartItems()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(3, $item->quantity);
    }

    public function testMergeGuestIntoUserMergesEmptyUserCart(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create();

        // Simule le panier invité
        Session::put('guest_cart_id', 'merge-123');
        Redis::hset('cart:guest:merge-123', $product->id, 4);

        // Simule l’utilisateur authentifié
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        // Merge
        $this->service->mergeGuestIntoUser($user->id);

        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 4,
        ]);
    }

    public function testMergeGuestIntoUserMergesAndIncrementsExistingItems(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create();

        // Créé un cart et un item existant
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        // Simule le panier invité
        Session::put('guest_cart_id', 'merge-456');
        Redis::hset('cart:guest:merge-456', $product->id, 2);

        // Merge
        $this->service->mergeGuestIntoUser($user->id);

        $item = CartItem::where('cart_id', $cart->id)
                        ->where('product_id', $product->id)
                        ->firstOrFail();
        $this->assertSame(7, $item->quantity);
    }

    public function testGetGuestCartHandlesRedisFailureGracefully(): void
    {
        // Simule un utilisateur non authentifié
        Auth::shouldReceive('check')->andReturn(false);

        // Mock Redis::hgetall() pour qu’il jette une Exception simple
        Redis::shouldReceive('hgetall')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        // Appel de la méthode
        $cart = $this->service->getGuestCart();

        // Assertions : on retombe sur un tableau vide
        $this->assertIsArray($cart);
        $this->assertEmpty($cart);
    }

    public function testAddItemRethrowsQueryExceptionForAuthenticatedUserOnDbError(): void
    {
        $user = User::factory()->create();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        // Pas de Product en base → clé étrangère invalide → QueryException
        $this->expectException(QueryException::class);
        $this->service->addItem(99999, 1);
    }

    public function testGetGuestCartHandlesRedisFailure(): void
    {
        // 1) Simule un utilisateur non authentifié
        Auth::shouldReceive('check')->andReturn(false);

        // 2) On génère un stub PHPUnit implémentant NodeConnectionInterface
        $connectionStub = $this->createMock(NodeConnectionInterface::class);

        // 3) Crée l’exception avec le stub typé et le message
        $exception = new RedisConnectionException(
            $connectionStub,
            'Connection failed'
        );

        // 4) Mock Redis::hgetall() pour qu’il jette cette exception
        Redis::shouldReceive('hgetall')
            ->once()
            ->andThrow($exception);

        // 5) Appel de la méthode
        $cart = $this->service->getGuestCart();

        // 6) Assertions
        $this->assertIsArray($cart);
        $this->assertEmpty($cart);
    }

    public function testAddItemSwallowsRedisExceptionForGuest(): void
    {
        // 1) Simule un guest non authentifié
        Auth::shouldReceive('check')->andReturn(false);

        // 2) Crée un stub implementing NodeConnectionInterface
        $connectionStub = $this->createMock(NodeConnectionInterface::class);
        $ex = new RedisConnectionException($connectionStub, 'Redis down');

        // 3) Mock Redis::hincrby() pour qu’il jette cette exception
        Redis::shouldReceive('hincrby')->once()->andThrow($ex);
        // expire() ne doit pas être appelé après l’exception
        Redis::shouldReceive('expire')->never();

        // 4) Appel de addItem() — ne doit **pas** remonter d’exception
        $this->service->addItem(5, 2);

        // 5) Le guest_cart_id doit toujours exister en session
        $this->assertNotNull(Session::get('guest_cart_id'));
    }

    public function testMergeGuestIntoUserSwallowsRedisCleanupFailure(): void
    {
        // Prépare user et product
        $user    = User::factory()->create();
        $product = Product::factory()->create();

        // Simule l’utilisateur authentifié
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        // Simule le panier invité existant
        Session::put('guest_cart_id', 'fail-merge');
        Redis::shouldReceive('hgetall')
            ->once()
            ->with('cart:guest:fail-merge')
            ->andReturn([(string)$product->id => 3]);

        // Mock Redis::del() pour qu’il jette l’exception
        $connectionStub = $this->createMock(NodeConnectionInterface::class);
        $ex = new RedisConnectionException($connectionStub, 'DEL failed');
        Redis::shouldReceive('del')->once()->andThrow($ex);

        // Appel du merge — ne doit **pas** remonter d’exception
        $this->service->mergeGuestIntoUser($user->id);

        // Vérifie que les items ont quand même été copiés en base
        $cart = Cart::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // Comme le cleanup a échoué, la session doit rester intacte
        $this->assertEquals('fail-merge', Session::get('guest_cart_id'));
    }
}
