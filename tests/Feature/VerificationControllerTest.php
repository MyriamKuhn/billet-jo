<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CartService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\App;
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
            ->shouldReceive('getUserCart')
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
        $response->assertStatus(401);
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

    public function testResendVerificationEmailHandlesException()
    {
        $user = User::factory()->unverified()->create();

        // Se connecter avec Sanctum par exemple
        $this->actingAs($user);

        // On simule une exception au moment de l'envoi de la notification
        Notification::fake();
        Notification::shouldReceive('send')
            ->andThrow(new Exception('SMTP failure'));

        $response = $this->postJson('/api/auth/email/resend-verification');

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                ]);
    }

    public function testVerifyNewEmailHandlesException()
    {
        $signedUrl = URL::signedRoute('verification.verify.new.email', ['token' => 'fake']);

        try {
            Schema::rename('email_updates', 'email_updates_temp');

            $response = $this->getJson($signedUrl);

            $response->assertStatus(500)
                    ->assertJson([
                        'status' => 'error',
                        'error' => __('validation.error_unknown'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error',
                    ]);
        } finally {
            Schema::rename('email_updates_temp', 'email_updates');
        }
    }

    public function testCancelEmailUpdateHandlesException()
    {
        // Génère une URL signée avec un token et un email fictifs
        $signedUrl = URL::signedRoute('email.update.cancel', [
            'token' => 'faketoken',
            'old_email' => 'old@example.com',
        ]);

        try {
            // Renomme temporairement la table pour provoquer une exception
            Schema::rename('email_updates', 'email_updates_temp');

            $response = $this->getJson($signedUrl);

            $response->assertStatus(500)
                    ->assertJson([
                        'status' => 'error',
                        'error' => __('validation.error_unknown'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error'
                    ]);
        } finally {
            // Restaure la table
            Schema::rename('email_updates_temp', 'email_updates');
        }
    }

    public function testVerifyEmailHandlesExceptionInProduction()
    {
        // Simule l'environnement production
        app()->detectEnvironment(fn() => 'production');

        // Crée une URL signée correspondant à ta route
        $signedUrl = URL::signedRoute('verification.verify', [
            'id' => 999999, // ID inexistant ou provoquant une erreur
            'hash' => sha1('fake@example.com'),
        ]);

        try {
            // Provoque une exception en renommant la table 'users'
            Schema::rename('users', 'users_temp');

            $response = $this->get($signedUrl);

            $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/error');
        } finally {
            // Restaure la table
            Schema::rename('users_temp', 'users');
        }
    }

    public function testVerifyNewEmailRedirectsToSuccessInProduction()
    {
        // Simule l'environnement de production
        app()->detectEnvironment(fn() => 'production');

        // Crée un utilisateur pour le test
        $user = User::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'lastname' => 'User',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
        ]);

        // Crée un token valide pour l'exemple
        $token = 'valid-token';
        $hashedToken = EmailHelper::hashToken($token);

        // Simule une entrée en base de données pour l'email
        $emailUpdate = EmailUpdate::create([
            'token' => $hashedToken,
            'new_email' => 'new@example.com',
            'user_id' => $user->id, // Utilise l'ID de l'utilisateur créé
        ]);

        // Crée une URL avec un token valide
        $url = URL::temporarySignedRoute('verification.verify.new.email', now()->addMinutes(30), ['token' => $token]);

        // Exécute la requête
        $response = $this->get($url);

        // Vérifie que la redirection se fait vers l'URL de succès
        $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/success');
    }

    public function testVerifyNewEmailHandlesExceptionInProduction()
    {
        app()->detectEnvironment(fn() => 'production');

        $signedUrl = URL::signedRoute('verification.verify.new.email', ['token' => 'fake']);

        try {
            Schema::rename('email_updates', 'email_updates_temp');

            $response = $this->getJson($signedUrl);

            $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/error');
        } finally {
            Schema::rename('email_updates_temp', 'email_updates');
        }
    }

    public function testCancelEmailUpdateHandlesExceptionInProduction()
    {
        app()->detectEnvironment(fn() => 'production');

        // Génère une URL signée avec un token et un email fictifs
        $signedUrl = URL::signedRoute('email.update.cancel', [
            'token' => 'faketoken',
            'old_email' => 'old@example.com',
        ]);

        try {
            // Renomme temporairement la table pour provoquer une exception
            Schema::rename('email_updates', 'email_updates_temp');

            $response = $this->getJson($signedUrl);

            $response->assertRedirect('https://jo2024.mkcodecreation.dev/verification-result/error');
        } finally {
            // Restaure la table
            Schema::rename('email_updates_temp', 'email_updates');
        }
    }
}

