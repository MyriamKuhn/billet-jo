<?php

namespace Tests\Unit;

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

    public function testAddItemThrowsOnInvalidQuantity()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $this->service->addItem(1, 0);
    }

    public function testAddItemToUserCartCreatesAndIncrementsInDatabase()
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
        $this->service->addItem($product->id, 3);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // 5) Deuxième appel : incrémente de 2 → total 5
        $this->service->addItem($product->id, 2);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);
    }

    public function atestAddItemToUserCartLogsAndRethrowsQueryExceptionViaFkViolation()
    {
        // 1) Stub Redis pour qu'on ne passe pas par la branche guest
        Redis::shouldReceive('hincrby')->never();
        Redis::shouldReceive('expire')->never();

        // 2) On crée et authentifie un utilisateur
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 3) On attend qu'il y ait un appel au logger->error()
        $this->logger->shouldReceive('error')->once();

        // 4) FK violation : pas de produit #999 en base → QueryException
        $this->expectException(QueryException::class);

        // 5) On appelle addItem avec un product_id invalide
        $this->service->addItem(999, 1);
    }

    public function testAddItemToGuestIncrementsRedisAndSetsTtl()
    {
        // 1) On efface toute session pour repartir à zéro
        Session::flush();

        // 2) On stubbe Redis : hincrby et expire doivent être appelés une fois
        Redis::shouldReceive('hincrby')
            ->once()
            ->with(Mockery::type('string'), 5, 4);
        Redis::shouldReceive('expire')
            ->once()
            ->with(Mockery::type('string'), $this->getProtectedTtl());

        // 3) Exécution : addItem() démarrera la session et appellera nos stubs
        $this->service->addItem(5, 4);

        // 4) On vérifie que la session contient bien le guest_cart_id
        $guestId = Session::get('guest_cart_id');
        $this->assertNotNull($guestId, 'La session doit contenir guest_cart_id');

        // 5) On reconstruit la clé attendue et on vérifie sa forme
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
        $this->service->addItem(7, 2);

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
        // 1) On créé le produit referencé
        $product = Product::factory()->create(['id' => 30]);

        // 2) Crée un utilisateur et l’authentifie
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 3) Crée le cart + l’item initial
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        // 4) Décrément partiel de 2 → reste 3
        $this->service->removeItem($product->id, 2);
        $this->assertDatabaseHas('cart_items', [
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);

        // 5) Suppression totale (reste 3 ≤ 3) → plus d’item
        $this->service->removeItem($product->id, 3);
        $this->assertDatabaseMissing('cart_items', [
            'product_id' => $product->id,
        ]);
    }

    public function testRemoveItemFromUserLogsAndRethrowsDatabaseExceptions()
    {
        // 1) Prépare un faux logger et s’attend à un appel à error()
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error removing item from user cart.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof QueryException)
            );

        // 2) Crée une sous‐classe anonyme de QueryException (sans constructeur obligatoire)
        $fakeException = new class extends QueryException {
            public function __construct() {}
        };

        // 3) Stub de l'item dont delete() jette notre exception
        $fakeItem = Mockery::mock();
        $fakeItem->shouldReceive('delete')
                ->once()
                ->andThrow($fakeException);

        // 4) Stub de la relation cartItems()->where()->first()
        $fakeRelation = Mockery::mock();
        $fakeRelation->shouldReceive('where')->andReturnSelf();
        $fakeRelation->shouldReceive('first')->andReturn($fakeItem);

        // 5) Stub d’un vrai App\Models\Cart dont cartItems() renvoie la relation
        $fakeCart = Mockery::mock(Cart::class);
        $fakeCart->shouldReceive('cartItems')->andReturn($fakeRelation);

        // 6) Partial‐mock de CartService avec notre logger, pour surcharger getUserCart()
        $service = Mockery::mock(CartService::class, [$logger])
                        ->makePartial()
                        ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getUserCart')
                ->once()
                ->andReturn($fakeCart);

        // 7) Authentifie un user
        $user = User::factory()->create();
        $this->be($user, 'web');

        // 8) On s’attend à réception d’un QueryException
        $this->expectException(QueryException::class);

        // 9) Exécution : le delete() jette, on loggue, puis on rethrow
        $service->removeItem(123, null);
    }

    public function testRemoveItemFromGuestHdelAndDecrementsAndSwallowError()
    {
        Session::flush();

        // 1) Generate guest_cart_id in session
        $this->service->getGuestCart();
        $guestId = Session::get('guest_cart_id');
        $key     = "cart:guest:{$guestId}";

        // 2) Partial removal (qty = 2)
        Redis::shouldReceive('hget')->once()->with($key, '40')->andReturn('5');
        Redis::shouldReceive('hincrby')->once()->with($key, '40', -2);
        Redis::shouldReceive('expire')->once()->with($key, $this->getProtectedTtl());

        $this->service->removeItem(40, 2);

        // 3) Full removal (qty = null)
        Redis::shouldReceive('hdel')->once()->with($key, '40');
        Redis::shouldReceive('expire')->once()->with($key, $this->getProtectedTtl());

        $this->service->removeItem(40, null);

        // 4) Error on hdel
        $redisEx = Mockery::mock(\Predis\Connection\ConnectionException::class);
        Redis::shouldReceive('hdel')->once()->with($key, '40')->andThrow($redisEx);
        $this->logger->shouldReceive('warning')
            ->once()
            ->with(
                'Unable to modify guest cart in Redis.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] === $redisEx)
            );

        $this->service->removeItem(40, null);

        // ——— PHPUnit needs at least one assertion ———
        $this->assertTrue(true);
    }

    public function testClearCartDeletesAllItemsForAuthenticatedUser()
    {
        // 1) Crée un user et l’authentifie
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 2) Crée son cart et quelques items
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->count(3)->create(['cart_id' => $cart->id]);

        // 3) Appelle clearCart() en mode “réel”
        app()->instance(LoggerInterface::class, Mockery::spy(LoggerInterface::class));
        $service = app(CartService::class);

        $service->clearCart();

        // 4) On doit avoir supprimé tous les cart_items
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function testClearCartLogsAndRethrowsOnDatabaseError()
    {
        // 1) Faux logger, on attend exactement un appel à error()
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error clearing user cart.',
                Mockery::on(fn($arg) => isset($arg['exception']) && $arg['exception'] instanceof QueryException)
            );

        // 2) Sous‐classe anonyme de QueryException pour bypasser le constructeur
        $fakeException = new class extends QueryException {
            public function __construct() {}
        };

        // 3) Stub de la relation cartItems() dont delete() jette l’exception
        $fakeRelation = Mockery::mock();
        $fakeRelation
            ->shouldReceive('delete')
            ->once()
            ->andThrow($fakeException);

        // 4) Stub d’un Cart moqué
        $fakeCart = Mockery::mock(Cart::class);
        $fakeCart->shouldReceive('cartItems')->andReturn($fakeRelation);

        // 5) Partial‐mock de CartService pour surcharger getUserCart()
        $service = Mockery::mock(CartService::class, [$logger])
                        ->makePartial()
                        ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getUserCart')
                ->once()
                ->andReturn($fakeCart);

        // 6) Authentifie un user
        $user = User::factory()->create();
        $this->be($user, 'web');

        // 7) On s’attend à la propagation de la QueryException
        $this->expectException(QueryException::class);

        // 8) Exécution : doit logguer puis rethrow
        $service->clearCart();
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
        // 0) Stub Redis pour qu'on reste en "user"
        Redis::shouldReceive('hincrby')->never();
        Redis::shouldReceive('expire')->never();

        // 1) Prépare un faux logger et attend un appel à error()
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')
            ->once()
            ->with(
                'Error adding item to user cart.',
                Mockery::on(fn($arg) => $arg['exception'] instanceof QueryException)
            );

        // 2) Binde ce logger dans le container pour que notre service l'utilise
        $this->app->instance(LoggerInterface::class, $logger);

        // 3) Crée et authentifie un utilisateur
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        // 4) On s'attend à un QueryException dû à la FK (product inexistant)
        $this->expectException(QueryException::class);

        // 5) Invocation : violation de FK → catch + log → rethrow
        app(CartService::class)->addItem(9999, 1);
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

        // 5) Appel : l’item n’étant pas trouvé, removeItem() doit retourner sans exception
        app(CartService::class)->removeItem(123, null);

        // 6) Vérification triviale pour que PHPUnit compte le test
        $this->assertTrue(true);
    }
}
