<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;
use App\Services\CartService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Http;
use App\Services\CaptchaService;

class UserRegisterTest extends TestCase
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
        $response = $this->post('/api/auth/register', [
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
        $response = $this->post('/api/auth/register', [
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
        $response = $this->post('/api/auth/register', [
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

        $response = $this->post('/api/auth/register', [
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
        $response = $this->post('/api/auth/register', [
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
        $cartService->getUserCart($user);

        // Vérifier que le panier a bien été créé
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
        ]);
    }

    public function testRegisterFailsWhenFirstnameIsMissing(): void
    {
        $payload = [
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'ValidPassword123!',
            'captcha_token' => 'valid-captcha-token',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'errors' => [
                        'firstname' => ['The firstname field is required.'],
                    ]
                ]);
    }

    public function testRegisterFailsWhenPasswordIsTooShort(): void
    {
        $payload = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'Short1!',
            'password_confirmation' => 'Short1!',
            'captcha_token' => 'valid-captcha-token',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'errors' => [
                        'password' => ['The password field must be at least 15 characters.'],
                    ]
                ]);
    }

    public function testRegisterFailsWhenPasswordConfirmationDoesNotMatch(): void
    {
        $payload = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'ValidPassword123!',
            'password_confirmation' => 'InvalidPassword123!',
            'captcha_token' => 'valid-captcha-token',
        ];

        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'errors' => [
                        'password' => ['The password field confirmation does not match.'],
                    ]
                ]);
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'firstname' => 'Myriam',
            'lastname' => 'Kühn',
            'email' => 'myriam@example.com',
            'password' => 'StrongPassw0rd!',
            'password_confirmation' => 'StrongPassw0rd!',
            'captcha_token' => Str::random(32),
        ], $overrides);
    }

    public function testItRegistersUserSuccessfullyInNonProductionEnvironment()
    {
        Event::fake();

        $this->assertFalse(app()->environment('production'));

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(201)
                ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('users', [
            'email' => 'myriam@example.com',
        ]);

        Event::assertDispatched(Registered::class);
    }

    public function testItFailsIfCaptchaIsInvalidInProductionEnvironment()
    {
        // Force environment to 'production'
        $this->app['env'] = 'production';

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false], 200),
        ]);

        Log::shouldReceive('warning')->atLeast()->once();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(422)
                ->assertJson(['status' => 'error']);

        $this->assertDatabaseMissing('users', [
            'email' => 'myriam@example.com',
        ]);
    }

    public function testItHandlesExceptionDuringRegistration()
    {
        // Simuler une exception pendant la création du compte
        $this->partialMock(AuthController::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('register')->andThrow(new \Exception('Fake DB error'));
        });

        Log::shouldReceive('error')->once();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(500);
    }

    public function testRegisterHandlesExceptionGracefully()
    {
        $this->app['env'] = 'production';

        // On force une exception dans le bloc try en mockant captchaService
        $this->mock(CaptchaService::class, function ($mock) {
            $mock->shouldReceive('verify')->andThrow(new \Exception('Simulated captcha failure'));
        });

        // Données valides
        $data = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecureP@ssw0rd123!',
            'password_confirmation' => 'SecureP@ssw0rd123!',
            'captcha_token' => 'dummy-token',
        ];

        // Exécution de la requête
        $response = $this->postJson('/api/auth/register', $data);

        // On vérifie que l'erreur 500 a bien été renvoyée avec le bon message
        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'error' => __('validation.error_unknown'),
        ]);
    }

    public function testEnableTwoFactorHandlesExceptionGracefully()
    {
        // Création d'un utilisateur connecté
        $user = User::factory()->create();

        // Authentification de l'utilisateur
        $this->actingAs($user);

        // Mock de Google2FA qui lance une exception
        $this->mock(\PragmaRX\Google2FA\Google2FA::class, function ($mock) {
            $mock->shouldReceive('generateSecretKey')
                ->andThrow(new \Exception('Simulated Google2FA failure'));
        });

        // Appel de la route d’activation 2FA
        $response = $this->postJson('/api/auth/enable2FA');

        // Vérification de la réponse d'erreur
        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'message' => __('validation.error_unknown'),
        ]);
    }
}
