<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Cart;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\Product;
use App\Models\EmailUpdate;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VerifyEmailNotification;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function testUsersTableHasExpectedColumns(): void
    {
        $columns = [
            'id', 'email', 'email_verified_at', 'password_hash', 'remember_token', 'firstname', 'lastname', 'role', 'twofa_secret', 'twofa_enabled', 'is_active', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "Colonne `{$column}` manquante dans `users`."
            );
        }
    }

    public function testUserHasOneCart(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->assertNotNull($user->cart);
        $this->assertTrue($user->cart->is($cart));
    }

    public function testUserHasManyPayments(): void
    {
        $user = User::factory()->create();
        $payment1 = Payment::factory()->create(['user_id' => $user->id]);
        $payment2 = Payment::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->payments);
        $this->assertTrue($user->payments->contains($payment1));
        $this->assertTrue($user->payments->contains($payment2));
    }

    public function testUserHasManyTickets(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create();
        $ticket1 = Ticket::factory()->create(['user_id' => $user->id, 'product_id'=> $product->id, 'payment_id' => $payment->id]);
        $ticket2 = Ticket::factory()->create(['user_id' => $user->id, 'product_id'=> $product->id, 'payment_id' => $payment->id]);

        $this->assertCount(2, $user->tickets);
        $this->assertTrue($user->tickets->contains($ticket1));
        $this->assertTrue($user->tickets->contains($ticket2));
    }

    public function testEmailIsUnique(): void
    {
        $email = 'unique@example.com';

        User::factory()->create(['email' => $email]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => $email]);
    }

    public function testUserHasOneEmailUpdate(): void
    {
        $user = User::factory()->create();
        $emailUpdate = EmailUpdate::factory()->create([
            'user_id' => $user->id,
        ]);

        // La relation emailUpdate renvoie bien l'instance créée
        $this->assertNotNull($user->emailUpdate);
        $this->assertEquals($emailUpdate->id, $user->emailUpdate->id);
    }

    public function testSendEmailVerificationNotificationSendsNotification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null, // pour simuler un user non vérifié
        ]);

        $user->sendEmailVerificationNotification();

        // On vérifie qu'on a bien envoyé une VerifyEmailNotification à cet user
        Notification::assertSentTo(
            [$user],
            VerifyEmailNotification::class
        );
    }

    public function testFillableIsCorrect()
    {
        $user = new User();

        $this->assertEquals(
            [
                'email',
                'password_hash',
                'firstname',
                'lastname',
                'role',
                'is_active',
                'email_verified_at',
            ],
            $user->getFillable()
        );
    }

    public function testHiddenIsCorrect()
    {
        $user = new User();

        $this->assertEqualsCanonicalizing(
            [
                'password_hash',
                'remember_token',
                'twofa_recovery_codes',
                'twofa_secret',
                'twofa_secret_temp',
            ],
            $user->getHidden()
        );
    }

    public function testCastsAreCorrect()
    {
        $user = new User();

        $casts = $user->getCasts();

        $this->assertArrayHasKey('role', $casts);
        $this->assertSame(UserRole::class, $casts['role']);

        $this->assertArrayHasKey('twofa_enabled', $casts);
        $this->assertSame('boolean', $casts['twofa_enabled']);

        $this->assertArrayHasKey('is_active', $casts);
        $this->assertSame('boolean', $casts['is_active']);

        $this->assertArrayHasKey('email_verified_at', $casts);
        $this->assertSame('datetime', $casts['email_verified_at']);
    }

    public function testTicketsRelation()
    {
        $user = User::factory()->create();
        Ticket::factory()->count(2)->create(['user_id' => $user->id]);

        $relation = $user->tickets();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertCount(2, $user->tickets);
    }

    public function testPaymentsRelation()
    {
        $user = User::factory()->create();
        Payment::factory()->count(3)->create(['user_id' => $user->id]);

        $relation = $user->payments();
        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertCount(3, $user->payments);
    }

    public function testCartRelation()
    {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id]);

        $relation = $user->cart();
        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertNotNull($user->cart);
        $this->assertEquals($user->id, $user->cart->user_id);
    }

    public function testEmailUpdateRelation()
    {
        $user = User::factory()->create();
        EmailUpdate::factory()->create(['user_id' => $user->id]);

        $relation = $user->emailUpdate();
        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertNotNull($user->emailUpdate);
        $this->assertEquals($user->id, $user->emailUpdate->user_id);
    }

    public function testItSendsVerifyEmailNotification()
    {
        Notification::fake();
        $user = User::factory()->create();

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class
        );
    }

    public function testItSendsPasswordResetNotification()
    {
        Notification::fake();
        $user  = User::factory()->create();
        $token = 'reset-token-123';

        // Exécution de la méthode à tester
        $user->sendPasswordResetNotification($token);

        // On vérifie qu’une notification ResetPasswordNotification a bien été envoyée
        // **et** que le token passé en argument est bien celui de la notification.
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function ($notification, $channels) use ($token) {
                // On accède par réflexion à la propriété protégée $token
                $refProp = (new \ReflectionClass($notification))
                                ->getProperty('token');
                $refProp->setAccessible(true);

                return $refProp->getValue($notification) === $token;
            }
        );
    }
}
