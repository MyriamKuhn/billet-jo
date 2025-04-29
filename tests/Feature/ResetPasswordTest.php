<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function testForgotPasswordValidation()
    {
        // Test: Email manquant
        $response = $this->postJson('/api/auth/forgot-password', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');

        // Test: Email invalide
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'invalid-email']);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');

        // Test: Email non existant
        $response = $this->postJson('/api/auth/forgot-password', ['email' => 'nonexistentemail@example.com']);
        $response->assertStatus(422);
    }

    public function testForgotPasswordWithValidEmail()
    {
        // Crée un utilisateur
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
        ]);

        // Simule l'appel API avec un email valide
        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        // Vérifie que la réponse est correcte
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => __('validation.reset_link_sent'),
        ]);
    }

    public function testForgotPasswordThrottleError()
    {
        // Crée un utilisateur valide
        $user = User::factory()->create([
            'email' => 'testuser@example.com',
        ]);

        // Simule plusieurs requêtes pour déclencher le throttling
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/forgot-password', [
                'email' => $user->email,
            ]);
        }

        // La 6e requête devrait être throttled
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(429);

        $this->assertTrue($response->headers->has('Retry-After'));
    }

    public function testResetPasswordSuccessfully()
    {
        // Créer un utilisateur
        $user = User::factory()->create();

        // Générer un token simple
        $plainToken = 'test-reset-token-123456789'; // Prédictible et simple

        // Insérer manuellement en base
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($plainToken), // Hashé ici
            'created_at' => now(),
        ]);

        // Payload de la requête
        $payload = [
            'token' => $plainToken, // on envoie le clair
            'email' => $user->email,
            'password' => 'NewStrongP@ssword2025!',
            'password_confirmation' => 'NewStrongP@ssword2025!',
        ];

        // Appel à l'API
        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'message' => __('validation.password_reset_success'),
                ]);

        // Vérifier que le mot de passe est mis à jour
        $this->assertTrue(Hash::check('NewStrongP@ssword2025!', $user->fresh()->password_hash));
    }


    public function testResetPasswordWithInvalidToken()
    {
        $user = User::factory()->create();

        $payload = [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewStrongP@ssword2025!',
            'password_confirmation' => 'NewStrongP@ssword2025!',
        ];

        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 'error',
                    'message' => __('The password reset token is invalid or has expired.'),
                ]);
    }

    public function testResetPasswordWithNonExistentUser()
    {
        $payload = [
            'token' => 'any-valid-looking-token',
            'email' => 'nonexistent@example.com',
            'password' => 'NewStrongP@ssword2025!',
            'password_confirmation' => 'NewStrongP@ssword2025!',
        ];

        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(404)
                ->assertJson([
                    'status' => 'error',
                    'message' => __('No user could be found with this email address.'),
                ]);
    }

    public function testResetPasswordValidationError()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewStrongP@ssword2025!',
            'password_confirmation' => 'DifferentPassword!',
        ];

        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
    }

    public function testResetPasswordThrowsException()
    {
        // On simule Password::reset pour qu'il lance une exception
        Password::shouldReceive('reset')
            ->once()
            ->andThrow(new \Exception('Unexpected error'));

        $payload = [
            'token' => 'fake-token',
            'email' => 'user@example.com',
            'password' => 'NewStrongP@ssword2025!',
            'password_confirmation' => 'NewStrongP@ssword2025!',
        ];

        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'message' => __('validation.unknown_error'),
                ]);
    }

    public function testForgotPasswordFailsWhenEmailIsMissing(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', []);

        $response->assertStatus(422);
    }

    public function testForgotPasswordFailsWhenEmailNotFound(): void
    {
        $payload = ['email' => 'nonexistentuser@example.com'];

        $response = $this->postJson('/api/auth/forgot-password', $payload);

        $response->assertStatus(422);
    }

    public function testResetPasswordFailsWithInvalidToken(): void
    {
        $payload = [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->postJson('/api/auth/reset-password', $payload);

        $response->assertStatus(422);
    }
}
