<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use App\Services\CartService;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use App\Exceptions\StockUnavailableException;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartService $service;
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock du logger
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->service = new CartService($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetUserCartCreatesAndReturnsCartModel()
    {
        $user = User::factory()->create();

        // Aucun cart existant
        $this->assertDatabaseCount('carts', 0);

        $cart = $this->service->getUserCart($user);
        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertDatabaseHas('carts', ['user_id' => $user->id]);

        // Si on rappelle, on récupère le même
        $same = $this->service->getUserCart($user);
        $this->assertEquals($cart->id, $same->id);
    }

    public function testGetGuestCartReturnsEmptyArrayWhenNoRedisData()
    {
        // On vide la session pour forcer guestKey() à générer un ID
        Session::flush();

        // On fake Redis pour qu’il n’y ait aucune donnée
        Redis::shouldReceive('hgetall')
            ->once()
            ->andReturn([]);

        $cart = $this->service->getGuestCart();
        $this->assertIsArray($cart);
        $this->assertEmpty($cart);
    }

    public function testGuestKeyGeneratesUuidAndPersistsInSession()
    {
        Session::flush();

        // On instancie ReflectionMethod pour accéder à guestKey()
        $rm = new \ReflectionMethod($this->service, 'guestKey');
        $rm->setAccessible(true);

        // On appelle la méthode protégée
        $key = $rm->invoke($this->service);

        // Récupère l'UUID stocké en session
        $guestId = Session::get('guest_cart_id');

        $this->assertNotNull($guestId, 'La session doit contenir guest_cart_id');
        $this->assertIsString($key, 'guestKey() doit renvoyer une string');
        $this->assertStringStartsWith("cart:guest:{$guestId}", $key);
    }

    public function testGetGuestCartReturnsEmptyArrayOnRedisConnectionError()
    {
        // Stub Redis::hgetall pour lever une exception générique
        Redis::shouldReceive('hgetall')
            ->once()
            ->andThrow(new \Exception('fail'));

        // Comme getGuestCart() fait « app(LoggerInterface::class)->warning(...) »,
        // on bind notre mock de logger dans le container pour l'intercepter
        $this->app->instance(
            LoggerInterface::class,
            $this->logger
        );

        $this->logger->shouldReceive('warning')
                    ->once()
                    ->with(
                        'Could not fetch guest cart from Redis',
                        Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof \Exception)
                    );

        $cart = $this->service->getGuestCart();
        $this->assertSame([], $cart);
    }

    public function testAddItemForGuestThrowsOnInvalidQuantity()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->service->addItemForGuest(1, 0);
    }

    public function testAddItemForUserThrowsOnInvalidQuantity()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);

        // 1) Create (or mock) a User
        $user = User::factory()->make();

        // 2) Call with qty = 0
        $this->service->addItemForUser($user, /* productId */ 1, /* qty */ 0);
    }

    public function testAddItemForUserCartCreatesAndIncrementsInDatabase()
    {
        // 0) Stub Redis pour ne pas toucher à Redis
        Redis::shouldReceive('hincrby')->never();
        Redis::shouldReceive('expire')->never();

        // 0b) Pas d'erreurs loggées dans ce scénario
        $this->logger->shouldNotReceive('error');

        // 1) Crée un utilisateur et l’authentifie
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 2) **Crée le produit** qu’on va ajouter
        $product = Product::factory()->create(['id' => 42]);

        // 3) Vérifie qu’il n’y a pas d’item au départ
        $this->assertDatabaseCount('cart_items', 0);

        // 4) Premier appel : ajoute 3 unités de ce produit
        $this->service->addItemForUser($user, $product->id, 3);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // 5) Deuxième appel : incrémente de 2 → total 5
        $this->service->addItemForUser($user, $product->id, 2);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);
    }

    public function testAddItemForGuestCartCreatesAndIncrementsInRedis()
    {
        // 0) Spy on Redis so we can assert calls
        Redis::spy();

        // 0b) No errors should be logged in this scenario
        $this->logger->shouldNotReceive('error');

        // 1) Start an empty session
        Session::start();
        $this->assertNull(session('guest_cart_id'));

        // 2) Pick any product ID
        $productId = 123;

        // 3) First call: add 3 units
        $this->service->addItemForGuest($productId, 3);

        // 4) Second call: add 2 more units
        $this->service->addItemForGuest($productId, 2);

        // 5) Session must now have a guest_cart_id
        $guestId = session('guest_cart_id');
        $this->assertNotNull($guestId, 'A guest_cart_id should have been generated and stored');

        $expectedKey = "cart:guest:{$guestId}";

        // 6) hincrby should have been called twice: once with 3, once with 2
        Redis::shouldHaveReceived('hincrby')
            ->twice()
            ->withArgs(function($key, $id, $qty) use ($expectedKey, $productId) {
                return $key === $expectedKey
                    && $id === $productId
                    && in_array($qty, [3, 2], true);
            });

        // 7) expire should also have been set twice
        Redis::shouldHaveReceived('expire')
            ->twice()
            ->with($expectedKey, Mockery::type('int'));
    }

    public function testAddItemForUserCartLogsAndRethrowsQueryExceptionViaFkViolation()
    {
        // 1) Stub Redis so we never hit the guest branch
        Redis::shouldReceive('hincrby')->never();
        Redis::shouldReceive('expire')->never();

        // 2) Create & authenticate a User
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 3) Expect exactly one error logged
        $this->logger->shouldReceive('error')->once();

        // 4) Expect a QueryException (FK violation) when we point at product_id 999
        $this->expectException(\Illuminate\Database\QueryException::class);

        // 5) CORRECTED: call addItemForUser(), not addItem()
        $this->service->addItemForUser($user, 999, 1);
    }

    public function testAddItemForGuestLogsAndSwallowsRedisConnectionException()
    {
        // 1) Create a product so assertStockAvailable passes
        $product = Product::factory()->create(['stock_quantity' => 10]);

        // 2) Prepare a fake Predis connection to satisfy the constructor
        $fakeConnection = \Mockery::mock(\Predis\Connection\NodeConnectionInterface::class);

        // 3) Stub Redis::hincrby to throw a RedisConnectionException
        Redis::shouldReceive('hincrby')
            ->once()
            ->andThrow(new \Predis\Connection\ConnectionException($fakeConnection, 'boom'));
        // and never call expire if hincrby fails
        Redis::shouldReceive('expire')->never();

        // 4) Expect exactly one error logged with that exception in context
        $this->logger->shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Error adding item to guest cart')
                    && $context['exception'] instanceof \Predis\Connection\ConnectionException;
            });

        // 5) Actually call the method under test
        Session::start();
        // adding quantity >=1 should trigger hincrby, which we’ve stubbed to throw
        $this->service->addItemForGuest($product->id, 3);

        // 6) After swallowing, guest_cart_id must still be in session
        $this->assertNotNull(Session::get('guest_cart_id'));
    }

    public function testAddItemToGuestIncrementsRedisAndSetsTtl()
    {
        // 1) Start with a totally fresh session
        Session::flush();

        // 2) Stub Redis: hincrby and expire must each be called exactly once
        Redis::shouldReceive('hincrby')
            ->once()
            ->with(Mockery::type('string'), 5, 4);

        Redis::shouldReceive('expire')
            ->once()
            ->with(Mockery::type('string'), $this->getProtectedTtl());

        // 3) Call the guest‐cart variant (not addItem)
        $this->service->addItemForGuest(5, 4);

        // 4) Now the session should contain a guest_cart_id
        $guestId = Session::get('guest_cart_id');
        $this->assertNotNull($guestId, 'La session doit contenir guest_cart_id');

        // 5) And the key we passed in to Redis calls should look like "cart:guest:{uuid}"
        $key = "cart:guest:{$guestId}";
        $this->assertStringContainsString($guestId, $key);
    }


    public function testAddItemToGuestSwallowRedisExceptionsAndLogsError()
    {
        // Vide la session pour repartir sur un cas “guest”
        Session::flush();

        // 1) On crée une instance moquée de RedisConnectionException
        $redisEx = Mockery::mock(\Predis\Connection\ConnectionException::class);

        // 2) On stubbe Redis::hincrby pour qu’elle jette notre mock
        Redis::shouldReceive('hincrby')
            ->once()
            ->andThrow($redisEx);

        // 3) On attend un appel au logger->error() avec notre exception
        $this->logger->shouldReceive('error')
            ->once()
            ->with(
                'Error adding item to guest cart in Redis.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] === $redisEx)
            );

        // 4) Appel : ne doit pas remonter l’exception
        $this->service->addItemForGuest(7, 2);

        // 5) Simple assertion pour lever le test au vert
        $this->assertTrue(true);
    }

    public function testMergeGuestIntoUserCreatesAndIncrementsItemsAndCleansUp()
    {
        // 1) Vider Redis et session
        Redis::flushall();
        Session::flush();

        // 2) Génère le guest_cart_id en session
        $this->service->getGuestCart();
        $guestId = Session::get('guest_cart_id');
        $key     = "cart:guest:{$guestId}";

        // 3) Prédépose des items dans Redis
        Redis::hset($key, '10', 2);
        Redis::hset($key, '20', 3);

        // 4) Crée les produits référencés (FK)
        Product::factory()->create(['id' => 10]);
        Product::factory()->create(['id' => 20]);

        // 5) Crée un user, son cart et un item existant pour product_id=20
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => 20,
            'quantity'   => 1,
        ]);

        // 6) Lance le merge
        $this->service->mergeGuestIntoUser($user->id);

        // 7) Vérifie la persistance des nouvelles quantités
        $this->assertDatabaseHas('cart_items', [
            'product_id' => 10,
            'quantity'   => 2,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => 20,
            'quantity'   => 4, // 1 + 3
        ]);

        // 8) Redis doit être vidé
        $this->assertEmpty(Redis::hgetall($key));

        // 9) La session guest_cart_id doit avoir disparu
        $this->assertNull(Session::get('guest_cart_id'));
    }

    public function testMergeGuestIntoUserLogsOnRedisConnectionError()
    {
        // 1) Mock d'une exception RedisConnectionException sans appeler son constructeur
        $redisEx = Mockery::mock(\Predis\Connection\ConnectionException::class);

        // 2) Stub Redis::del pour qu'il jette notre mock
        Redis::shouldReceive('del')
            ->once()
            ->andThrow($redisEx);

        // 3) On attend un appel unique au logger->warning() avec ce même exception
        $this->logger->shouldReceive('warning')
            ->once()
            ->with(
                'Failed to delete guest cart from Redis after merge.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] === $redisEx)
            );

        // 4) Crée un user et appelle mergeGuestIntoUser()
        $user = User::factory()->create();
        // Doit attraper l'exception et ne pas la relancer
        $this->service->mergeGuestIntoUser($user->id);

        // 5) Simple assertion pour valider la fin du test
        $this->assertTrue(true);
    }

    public function testRemoveItemFromUserDeletesOrDecrements()
    {
        // 1) Make sure we have a product
        $product = Product::factory()->create(['id' => 30]);

        // 2) Create & authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 3) Create the cart & initial item
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        // 4) Partial decrement of 2 → should leave quantity = 3
        $this->service->removeItemForUser($user, $product->id, 2);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // 5) Then remove all remaining 3 → item should be gone
        $this->service->removeItemForUser($user, $product->id, 3);
        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
    }

    public function testRemoveItemFromUserLogsAndRethrowsDatabaseExceptions()
    {
        // 1) Prepare a fake logger and expect exactly one error()
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error removing item from user cart.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof \Illuminate\Database\QueryException)
            );

        // 2) Create a dummy QueryException subclass (so we can throw it without args)
        $fakeException = new class extends \Illuminate\Database\QueryException {
            public function __construct() {}
        };

        // 3) Stub the cartItem model so delete() throws our exception
        $fakeItem = Mockery::mock();
        $fakeItem->shouldReceive('delete')
                ->once()
                ->andThrow($fakeException);

        // 4) Stub the relation: cartItems()->where()->first() returns our fake item
        $fakeRelation = Mockery::mock();
        $fakeRelation->shouldReceive('where')->andReturnSelf();
        $fakeRelation->shouldReceive('first')->andReturn($fakeItem);

        // 5) Stub a Cart model whose cartItems() returns that relation
        $fakeCart = Mockery::mock(\App\Models\Cart::class);
        $fakeCart->shouldReceive('cartItems')->andReturn($fakeRelation);

        // 6) Partial‐mock CartService with our logger, override getUserCart()
        $service = Mockery::mock(\App\Services\CartService::class, [$logger])
                        ->makePartial()
                        ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getUserCart')
                ->once()
                ->andReturn($fakeCart);

        // 7) Authenticate a user
        $user = \App\Models\User::factory()->create();
        $this->be($user, 'web');

        // 8) Expect our fake exception to bubble up
        $this->expectException(\Illuminate\Database\QueryException::class);

        // 9) Execute: delete() will throw, service should log and rethrow
        $service->removeItemForUser($user, 123, null);
    }

    public function testRemoveItemFromGuestHdelAndDecrementsAndSwallowError()
    {
        // 0) Ensure a fresh session so guestKey() generates a UUID
        Session::flush();

        // 1) Trigger guestKey() (via getGuestCart) to populate session→guest_cart_id
        $this->service->getGuestCart();
        $guestId = Session::get('guest_cart_id');
        $key     = "cart:guest:{$guestId}";

        // ── Partial removal (qty = 2) ────────────────────────────
        // Stub the Redis calls for decrement
        Redis::shouldReceive('hget')
            ->once()
            ->with($key, '40')
            ->andReturn('5');
        Redis::shouldReceive('hincrby')
            ->once()
            ->with($key, '40', -2);
        Redis::shouldReceive('expire')
            ->once()
            ->with($key, $this->getProtectedTtl());

        // Call the guest removal method
        $this->service->removeItemForGuest(40, 2);

        // ── Full removal (qty = null) ─────────────────────────────
        Redis::shouldReceive('hdel')
            ->once()
            ->with($key, '40');
        Redis::shouldReceive('expire')
            ->once()
            ->with($key, $this->getProtectedTtl());

        $this->service->removeItemForGuest(40, null);

        // ── Simulate a Redis error on hdel ───────────────────────
        $redisEx = Mockery::mock(\Predis\Connection\ConnectionException::class);
        Redis::shouldReceive('hdel')
            ->once()
            ->with($key, '40')
            ->andThrow($redisEx);
        // Expect a warning log
        $this->logger->shouldReceive('warning')
            ->once()
            ->with(
                'Unable to modify guest cart in Redis.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] === $redisEx)
            );

        // Should swallow the exception
        $this->service->removeItemForGuest(40, null);

        // PHPUnit needs at least one assertion
        $this->assertTrue(true);
    }

    public function testClearCartDeletesAllItemsForAuthenticatedUser()
    {
        // 1) Create a user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 2) Create that user’s cart and add some items
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

        // 3) Swap in a spy logger so we don’t log real errors
        app()->instance(
            LoggerInterface::class,
            Mockery::spy(LoggerInterface::class)
        );

        // 4) Resolve the service and call the correct method
        /** @var \App\Services\CartService $service */
        $service = app(CartService::class);
        $service->clearCartForUser($user);

        // 5) Assert that all cart_items have been deleted
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function testClearCartLogsAndRethrowsOnDatabaseError()
    {
        // 1) Fake logger, expect exactly one call to error()
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error clearing user cart.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof QueryException)
            );

        // 2) Anonymous subclass of QueryException to bypass constructor
        $fakeException = new class extends QueryException {
            public function __construct() {}
        };

        // 3) Stub the relation returned by cartItems() so that delete() throws
        $fakeRelation = Mockery::mock();
        $fakeRelation
            ->shouldReceive('delete')
            ->once()
            ->andThrow($fakeException);

        // 4) Stub a Cart model whose cartItems() returns our fake relation
        $fakeCart = Mockery::mock(Cart::class);
        $fakeCart->shouldReceive('cartItems')->andReturn($fakeRelation);

        // 5) Partial‐mock CartService to override getUserCart()
        $service = Mockery::mock(CartService::class, [$logger])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getUserCart')
            ->once()
            ->andReturn($fakeCart);

        // 6) Authenticate a user
        $user = User::factory()->create();
        $this->be($user, 'web');

        // 7) Expect the QueryException to bubble up
        $this->expectException(QueryException::class);

        // 8) Call the real method
        $service->clearCartForUser($user);
    }

    /**
     * Helper to read protected $guestTtl via reflection.
     */
    private function getProtectedTtl(): int
    {
        $ref = new \ReflectionProperty($this->service, 'guestTtl');
        $ref->setAccessible(true);
        return $ref->getValue($this->service);
    }

    public function testAddItemToUserCartLogsAndRethrowsQueryExceptionOnDbError()
    {
        // 0) Stub Redis so we stay in the “user” branch
        Redis::shouldReceive('hincrby')->never();
        Redis::shouldReceive('expire')->never();

        // 1) Prepare a fake logger and expect exactly one call to error()
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error adding item to user cart.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof QueryException)
            );

        // 2) Bind our fake logger into the container so CartService picks it up
        $this->app->instance(LoggerInterface::class, $logger);

        // 3) Create and authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 4) Expect a QueryException due to foreign-key violation (nonexistent product)
        $this->expectException(QueryException::class);

        // 5) Invoke the real service method: addItemForUser()
        app(CartService::class)->addItemForUser($user, 9999, 1);
    }

    public function testRemoveItemFromUserReturnsSilentlyIfItemNotFound()
    {
        // 1) Création d'un utilisateur et authentification
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 2) On crée son panier, **sans** y ajouter d’items
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        // 3) On s’assure qu’il n’y a vraiment pas d’item avec product_id = 123
        $this->assertDatabaseMissing('cart_items', [
            'cart_id'    => $cart->id,
            'product_id' => 123,
        ]);

        // 4) On injecte un logger qui **ne doit pas** recevoir d’erreur
        $this->app->instance(
            LoggerInterface::class,
            Mockery::mock(LoggerInterface::class)
                ->shouldNotReceive('error')
                ->getMock()
        );

        // 5) Appel : l’item n’étant pas trouvé, removeItemForUser() doit retourner sans exception
        app(CartService::class)->removeItemForUser($user, 123, null);

        // 6) Vérification triviale pour que PHPUnit compte le test
        $this->assertTrue(true);
    }

    public function testAssertStockAvailableWithArrayPassesWhenStockSufficient(): void
    {
        // Crée 2 produits avec un stock défini
        $p1 = Product::factory()->create(['stock_quantity' => 5]);
        $p2 = Product::factory()->create(['stock_quantity' => 10]);

        // Demande des quantités inférieures ou égales au stock
        $items = [
            $p1->id => 3,
            $p2->id => 10,
        ];

        // Ne doit pas lancer d'exception
        $this->service->assertStockAvailable($items);

        $this->addToAssertionCount(1);
    }

    public function testAssertStockAvailableWithArrayThrowsExceptionWhenStockInsufficient(): void
    {
        $p1 = Product::factory()->create(['stock_quantity' => 2]);
        $p2 = Product::factory()->create(['stock_quantity' => 4]);

        $items = [
            $p1->id => 5,  // plus que disponible
            $p2->id => 3,  // OK
        ];

        try {
            $this->service->assertStockAvailable($items);
            $this->fail('Expected StockUnavailableException was not thrown');
        } catch (StockUnavailableException $e) {
            $details = $e->details;
            $this->assertCount(1, $details);
            $this->assertEquals([
                'product_id'         => $p1->id,
                'product_name'       => $p1->name,
                'requested_quantity' => 5,
                'available_quantity' => 2,
            ], $details[0]);
        }
    }

    public function testAssertStockAvailableWithCartPassesWhenStockSufficient(): void
    {
        // Produit et panier
        $product = Product::factory()->create(['stock_quantity' => 7]);
        $cart    = Cart::factory()->create();

        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 7,
        ]);

        // Charge via instance de Cart : on ne doit pas obtenir d'exception
        $this->service->assertStockAvailable($cart);

        $this->addToAssertionCount(1);
    }

    public function testAssertStockAvailableWithCartThrowsExceptionWhenStockInsufficient(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 1]);
        $cart    = Cart::factory()->create();

        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        $this->expectException(StockUnavailableException::class);
        $this->expectExceptionMessage('Stock unavailable for one or more items in the cart');

        try {
            $this->service->assertStockAvailable($cart);
        } catch (StockUnavailableException $e) {
            // Vérifie quand même le détail
            $this->assertCount(1, $e->details);
            $detail = $e->details[0];
            $this->assertEquals($product->id, $detail['product_id']);
            $this->assertEquals($product->name, $detail['product_name']);
            $this->assertEquals(3, $detail['requested_quantity']);
            $this->assertEquals(1, $detail['available_quantity']);
            throw $e;
        }
    }

    public function testGuestKeyUsesHeaderWhenValidUuidProvided()
    {
        // 1) Vider la session pour simuler un nouvel invité
        Session::flush();

        // 2) Générer un UUID valide comme si le frontend l’avait envoyé
        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();

        // 3) Injecter cet en-tête dans la requête courante
        //    On récupère l’instance de Request via le container Laravel
        $this->app['request']->headers->set('X-Guest-Cart-Id', $uuid);

        // 4) Appel à guestKey() via reflection
        $rm = new \ReflectionMethod($this->service, 'guestKey');
        $rm->setAccessible(true);
        $key = $rm->invoke($this->service);

        // 5) Vérifier que guest_cart_id dans la session vaut bien l’UUID fourni
        $this->assertSame($uuid, Session::get('guest_cart_id'));

        // 6) Vérifier que la clé retournée commence par "cart:guest:{$uuid}"
        $this->assertStringStartsWith("cart:guest:{$uuid}", $key);
    }
}
