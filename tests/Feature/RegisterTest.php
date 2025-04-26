<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Services\CartService;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function testEmailIsUnique()
    {
        // Crée un utilisateur avec un email spécifique
        User::create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'user@example.com',
            'password_hash' => bcrypt('ValidPassword123!'),
        ]);

        // Essaie de créer un autre utilisateur avec le même email
        $response = $this->post('/api/user/register', [
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'user@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'captcha_token' => 'valid_token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function testValidationFails()
    {
        $response = $this->post('/api/user/register', [
            'firstname' => '',
            'lastname' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
            'captcha_token' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['firstname', 'lastname', 'email', 'password', 'captcha_token']);
    }

    public function testUserCreation()
    {
        $response = $this->post('/api/user/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'user@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'captcha_token' => 'valid_token',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
        ]);
    }

    public function testVerificationEmailSent()
    {
        Notification::fake();

        $response = $this->post('/api/user/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'user@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'captcha_token' => 'valid_token',
        ]);

        $response->assertStatus(201);

        // Vérifie que la notification de vérification a bien été envoyée
        $user = User::where('email', 'user@example.com')->first();
        Notification::assertSentTo(
            [$user], VerifyEmailNotification::class
        );
    }

    public function testCartAddedAfterEmailVerification()
    {
        Notification::fake();

        // Enregistrer un utilisateur
        $response = $this->post('/api/user/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'user@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'captcha_token' => 'valid_token',
        ]);


        $response->assertStatus(201);

        // Récupérer l'utilisateur
        $user = User::where('email', 'user@example.com')->first();

        // Vérifier que l'email de vérification a bien été envoyé
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Simuler la vérification de l'email via la route de vérification
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $this->get($verificationUrl)->assertStatus(200);

        // Utiliser le CartService pour créer un panier pour l'utilisateur
        $cartService = new CartService();
        $cartService->createCartForUser($user);

        // Vérifier que le panier a bien été créé
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
        ]);
    }
}
