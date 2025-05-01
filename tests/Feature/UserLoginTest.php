<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use PragmaRX\Google2FA\Google2FA;
use Mockery;
use Illuminate\Support\Facades\Log;

class UserLoginTest extends TestCase
{
    use RefreshDatabase;

    public function testAllowsUserToLoginWithValidCredentialsWithoutRemember(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
        ]);

        $response = $this->postJson(route('login'), [
            'email' => 'test@example.com',
            'password' => 'MyGreatPassword@123',
            'remember' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token']);

        $token = $response->json('token');
        $this->assertNotNull($token); // Vérifie que le token existe
        $this->assertIsString($token); // Vérifie que le token est bien une chaîne de caractères
    }

    public function testAllowsUserToLoginWithInvalidCredentials(): void
    {
        $response = $this->postJson(route('login'), [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
            'remember' => false,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'status' => 'error',
            'error' => 'Invalid credentials. Please check your email address and password.',
        ]);
    }

    public function testNotAllowsLoginForUnverifiedUser(): void
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
            'email_verified_at' => null, // User is not verified
        ]);

        $response = $this->postJson(route('login'), [
            'email' => 'unverified@example.com',
            'password' => 'MyGreatPassword@123',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'status'=> 'error',
            'error' => 'Your email address has not been verified. A new verification email has been sent.',
        ]);
    }

    public function testAllowsAuthenticatedUserToAccessProtectedRoute(): void
    {
        $user = User::factory()->create([
            'email' => 'auth@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
        ]);

        Auth::attempt(['email' => $user->email, 'password' => 'MyGreatPassword@123']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson(route('enable2FA'));

        $response->assertStatus(200);
    }

    public function testRejectsUserWithInvalidToken(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token',
        ])->postJson(route('enable2FA'));

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function testAllowsUserToEnableTwoFactorAuthentication(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
        ]);

        // Activer 2FA pour cet utilisateur
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->twofa_secret = $secret;
        $user->twofa_enabled = true;
        $user->save();

        // Vérification que le secret a bien été sauvegardé
        $this->assertNotNull($user->twofa_secret);
        $this->assertTrue($user->twofa_enabled);

        // Simuler la génération du code 2FA
        $code = $google2fa->getCurrentOtp($secret);  // Code généré par l'application d'authentification

        // Tester la soumission du code pour valider l'authentification
        $response = $this->postJson(route('login'), [
            'email' => 'test@example.com',
            'password' => 'MyGreatPassword@123',
            'remember' => false,
            'twofa_code' => $code,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Login successful.']);
    }

    public function testNotAllowsInvalidTwoFactorCode(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
        ]);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->twofa_secret = $secret;
        $user->twofa_enabled = true;
        $user->save();

        // Générer un code 2FA mais en utilisant un code invalide pour l'utilisateur
        $invalidCode = '123456';

        $response = $this->postJson(route('login'), [
            'email' => 'test@example.com',
            'password' => 'MyGreatPassword@123',
            'remember' => false,
            'twofa_code' => $invalidCode,
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'The two-factor authentication code is invalid.']);
    }

    public function testNotAllowsDisabledUserToLogin(): void
    {
        // Créer un utilisateur désactivé
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password_hash' => Hash::make('MyGreatPassword@123'),
            'is_active' => false,  // Utilisateur désactivé
        ]);

        // Essayer de se connecter avec les informations de l'utilisateur désactivé
        $response = $this->postJson(route('login'), [
            'email' => $user->email,
            'password' => 'MyGreatPassword@123',
        ]);

        // Vérifier que l'utilisateur désactivé ne peut pas se connecter
        $response->assertStatus(403);  // Forbidden - compte désactivé
        $response->assertJson([
            'status' => 'error',
            'error' => 'Your account has been disabled. Please contact support.',
        ]);
    }

    public function testLoginFailsWithInvalidTwofaCode(): void
    {
        // Création d'un utilisateur avec 2FA activé
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => bcrypt('StrongPassword123!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'twofa_enabled' => true,
            'twofa_secret' => 'BASE32SECRET1234',
        ]);

        // Mock propre de Google2FA
        /** @var \Mockery\MockInterface|\PragmaRX\Google2FA\Google2FA $mock */
        $mock = Mockery::mock(Google2FA::class);
        $mock->shouldReceive('verifyKey')
            ->once()
            ->with($user->twofa_secret, 'wrong-code')
            ->andReturn(false);

        // Remplacer l'instance dans le container Laravel
        $this->app->instance('pragmarx.google2fa', $mock);

        // Requête de login avec mauvais code 2FA
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'twofa_code' => 'wrong-code',
        ]);

        // Vérifie que la réponse est bien une erreur 2FA
        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_twofa_invalid'),
                ]);
    }

    public function testLoginFailsWithNoTwofaCode(): void
    {
        // Création d'un utilisateur avec 2FA activé
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password_hash' => bcrypt('StrongPassword123!'),
            'email_verified_at' => now(),
            'is_active' => true,
            'twofa_enabled' => true,
        ]);

        // Mock propre de Google2FA
        /** @var \Mockery\MockInterface|\PragmaRX\Google2FA\Google2FA $mock */
        $mock = Mockery::mock(Google2FA::class);
        $mock->shouldReceive('verifyKey')
            ->once()
            ->with($user->twofa_secret, 'wrong-code')
            ->andReturn(false);

        // Remplacer l'instance dans le container Laravel
        $this->app->instance('pragmarx.google2fa', $mock);

        // Requête de login avec mauvais code 2FA
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'twofa_code' => 'wrong-code',
        ]);

        // Vérifie que la réponse est bien une erreur 2FA
        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                ]);
    }

    public function testLoginFailsWithInvalidData()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'invalidemail',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                ]);
    }

    public function testLoginFailsWhenTwofaIsEnabledButNoTwofaCode()
    {
        $user = User::factory()->create([
            'twofa_enabled' => true,
            'password_hash' => Hash::make('validpassword'), // Assure-toi que le mot de passe est valide
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'validpassword',
            'twofa_code' => null,
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_twofa_required'),
                ]);
    }

    public function testLoginWithRememberToken()
    {
        $user = User::factory()->create([
            'password_hash' => Hash::make('validpassword'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'validpassword',
            'remember' => true, // L'utilisateur souhaite être "souvenu"
        ]);

        $response->assertStatus(200)
                ->assertJson(['status' => 'success']);

        // Vérifie que le token est bien créé et qu'il a une durée de 1 semaine
        $token = $response->json('token');
        $sanctumPayload = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        $this->assertNotNull($sanctumPayload);  // Vérifie que le token existe
        $this->assertGreaterThanOrEqual(now()->addWeeks(1)->timestamp, $sanctumPayload->expires_at->timestamp);
    }

    public function testEnableTwoFactorUnauthorized()
    {
        // Simule un utilisateur non authentifié
        $this->withoutMiddleware();  // Ignore le middleware d'authentification pour ce test

        // Effectue la requête de l'API sans être authentifié
        $response = $this->postJson('/api/auth/enable2FA');

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unauthorized'),
                ]);
    }
}
