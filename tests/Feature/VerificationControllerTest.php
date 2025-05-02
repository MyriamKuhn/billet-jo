<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use App\Models\EmailUpdate;
use App\Helpers\EmailHelper;
use Mockery;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testVerificationFailsIfUserNotFound()
    {
        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ValidateSignature::class,
        ]);

        $response = $this->getJson('/api/auth/email/verify/999/invalidhash');
        $response->assertStatus(404);
    }

    public function testVerificationFailsIfHashDoesNotMatch()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $wrongHash = sha1('wrong@example.com');

        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ValidateSignature::class,
        ]);

        $response = $this->getJson("/api/auth/email/verify/{$user->id}/{$wrongHash}");
        $response->assertStatus(400);
    }

    public function testVerificationFailsIfEmailAlreadyVerified()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->getJson($url);

        $response->assertStatus(409);
    }

    public function testVerificationFailsWithException()
    {
        Log::shouldReceive('error')->once();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->mock(CartService::class)
            ->shouldReceive('createCartForUser')
            ->andThrow(new Exception('Simulated failure'));

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->getJson($url);
        $response->assertStatus(500);
    }

    public function testResendFailsIfUserNotAuthenticated()
    {
        $response = $this->postJson('/api/auth/email/resend-verification');
        $response->assertStatus(401)
                ->assertJsonFragment(['message' => 'Unauthenticated.']);
    }

    public function testResendFailsIfEmailAlreadyVerified()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend-verification');
        $response->assertStatus(409);
    }

    public function testResendSucceedsForUnverifiedUser()
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user)->postJson('/api/auth/email/resend-verification');
        $response->assertStatus(200)
                ->assertJsonFragment(['message' => __('validation.email_verification_resend')]);

        Notification::assertSentTo($user, \App\Notifications\VerifyEmailNotification::class);
    }

    public function testResendVerificationEmailUnauthorized()
    {
        // Simule un utilisateur non authentifié
        $this->withoutMiddleware();  // Ignore le middleware d'authentification pour ce test

        // Effectue la requête de l'API sans être authentifié
        $response = $this->postJson('/api/auth/email/resend-verification');

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unauthorized'),
                ]);
    }

    public function testInvalidUserIdRedirectsAwayInProduction()
    {
        $this->withoutMiddleware();

        $this->app['env'] = 'production';

        $response = $this->get('/api/auth/email/verify/999/invalidhash');

        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/invalid');
    }

    public function testInvalidHashRedirectsAwayInProduction()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'production';

        // Créer un utilisateur
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
            'email_verified_at' => null, // Assure-toi que l'email n'est pas vérifié
        ]);

        $response = $this->get("/api/auth/email/verify/{$user->id}/invalidhash");

        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/invalid');
    }

    public function testAlreadyVerifiedEmailRedirectsAwayInProduction()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'production';

        // Créer un utilisateur avec un email déjà vérifié
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
            'email_verified_at' => now(), // L'email est vérifié
        ]);

        $response = $this->get("/api/auth/email/verify/{$user->id}/" . sha1($user->getEmailForVerification()));

        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/already-verified');
    }

    public function testSuccessfulEmailVerificationRedirectsAwayInProduction()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'production';

        // Créer un utilisateur sans email vérifié
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->get("/api/auth/email/verify/{$user->id}/" . sha1($user->getEmailForVerification()));

        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/success');
    }

    public function testSuccessfulEmailVerificationReturnsJsonInDevelopment()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'local'; // ou 'testing'

        // Créer un utilisateur sans email vérifié
        $user = User::factory()->create([
            'email' => 'oldemail@example.com',
            'email_verified_at' => null,
        ]);

        $response = $this->get("/api/auth/email/verify/{$user->id}/" . sha1($user->getEmailForVerification()));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => __('validation.email_verification_success'),
            'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/success',
        ]);
    }

    public function testInvalidUserIdReturnsJsonInDevelopment()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'local';

        $response = $this->get('/api/auth/email/verify/999/invalidhash');

        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
            'error' => __('validation.error_user_not_found'),
            'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid',
        ]);
    }

    public function testVerifyNewEmailRedirectsIfNoToken()
    {
        // Simuler l'environnement de production
        $this->app['env'] = 'production';

        // Désactiver les middlewares pour ce test
        $this->withoutMiddleware();

        // Créer un utilisateur pour le test
        $user = User::factory()->create();

        // Effectuer la requête sans passer de token
        $response = $this->get('/api/auth/email/verify-new-mail');

        // Vérifier la redirection
        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/invalid');
    }

    public function testVerifyNewEmailReturnsErrorIfEmailUpdateNotFound()
    {
        // Fake environment non-production
        app()->detectEnvironment(fn () => 'local');
        // Désactiver les middlewares pour ce test uniquement
        $this->withoutMiddleware();

        // Simuler un token
        $token = 'fake-token';
        $hashedToken = EmailHelper::hashToken($token);

        // Assurer que le token n'existe pas en base
        EmailUpdate::where('token', $hashedToken)->delete();

        // Appeler la route avec le token
        $response = $this->getJson(route('verification.verify.new.email', ['token' => $token]));

        // Assertions
        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
            'error' => __('validation.error_email_verification_used'),
            'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid',
        ]);
    }

    public function testVerifyNewEmailReturnsErrorIfEmailUpdateNotFoundInProduction()
    {
        // Fake environment non-production
        app()->detectEnvironment(fn () => 'production');
        // Désactiver les middlewares pour ce test uniquement
        $this->withoutMiddleware();

        // Simuler un token
        $token = 'fake-token';
        $hashedToken = EmailHelper::hashToken($token);

        // Assurer que le token n'existe pas en base
        EmailUpdate::where('token', $hashedToken)->delete();

        // Appeler la route avec le token
        $response = $this->get(route('verification.verify.new.email', ['token' => $token]));

        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/invalid');
    }

    public function testCancelEmailUpdateRedirectsInProduction()
    {
        // Fake environment non-production
        app()->detectEnvironment(fn () => 'production');
        // Désactiver les middlewares pour ce test uniquement
        $this->withoutMiddleware();

        // Créer un EmailUpdate valide
        $user = User::factory()->create();
        $emailUpdate = EmailUpdate::factory()->create([
            'token' => EmailHelper::hashToken('test-token'),
            'old_email' => 'old@example.com',
            'user_id' => $user->id,
        ]);

        // Simuler la requête de l'utilisateur avec un token et un ancien email
        $response = $this->get(route('email.update.cancel', [
            'token' => 'test-token',
            'old_email' => 'old@example.com'
        ]));

        // Vérification de la redirection dans l'environnement de production
        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/success');
    }

    public function testCancelEmailUpdateReturnsJsonInNonProduction()
    {
        // Simuler un environnement non-production
        app()->detectEnvironment(fn() => 'local');
        // Désactiver les middlewares pour ce test uniquement
        $this->withoutMiddleware();

        // Créer un EmailUpdate valide
        $user = User::factory()->create();
        $emailUpdate = EmailUpdate::factory()->create([
            'token' => EmailHelper::hashToken('test-token'),
            'old_email' => 'old@example.com',
            'user_id' => $user->id,
        ]);

        // Simuler la requête de l'utilisateur avec un token et un ancien email
        $response = $this->getJson(route('email.update.cancel', [
            'token' => 'test-token',
            'old_email' => 'old@example.com'
        ]));

        // Vérification de la réponse JSON
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => __('validation.email_update_canceled'),
            'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/success'
        ]);
    }

    public function testCancelEmailUpdateNotFoundRedirectInProduction()
    {
        // Simuler un environnement de production
        app()->detectEnvironment(fn() => 'production');
        // Désactiver les middlewares pour ce test uniquement
        $this->withoutMiddleware();

        // Cas où l'EmailUpdate n'existe pas
        $response = $this->getJson(route('email.update.cancel', [
            'token' => 'invalid-token',
            'old_email' => 'old@example.com'
        ]));

        // Vérification de la redirection vers l'URL "invalid" en production
        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/invalid');
    }
}

