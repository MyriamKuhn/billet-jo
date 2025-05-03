<?php

namespace Tests\Unit;

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

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the users table has the expected columns.
     *
     * @return void
     */
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

    /**
     * Test if the users table has one Cart.
     *
     * @return void
     */
    public function testUserHasOneCart(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->assertNotNull($user->cart);
        $this->assertTrue($user->cart->is($cart));
    }

    /**
     * Test if the user table has many payments.
     *
     * @return void
     */
    public function testUserHasManyPayments(): void
    {
        $user = User::factory()->create();
        $payment1 = Payment::factory()->create(['user_id' => $user->id]);
        $payment2 = Payment::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->payments);
        $this->assertTrue($user->payments->contains($payment1));
        $this->assertTrue($user->payments->contains($payment2));
    }

    /**
     * Test if the user table has many tickets.
     *
     * @return void
     */
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

    /**
     * Test if mail is unique in the users table.
     *
     * @return void
     */
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
}
