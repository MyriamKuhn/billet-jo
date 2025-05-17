<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Arr;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\AuthService;
use App\Services\Auth\TwoFactorService;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(RegistrationService::class);
        $this->mock(AuthService::class);
        $this->mock(TwoFactorService::class);
    }

    public function testRegisterReturns201()
    {
        $payload = [
            'firstname' => 'Alice',
            'lastname' => 'Durand',
            'email' => 'alice@example.com',
            'password' => 'Str0ngP@ssword2025!',
            'password_confirmation' => 'Str0ngP@ssword2025!',
            'captcha_token' => 'token',
            'accept_terms' => true,
        ];

        $validated = Arr::except($payload, ['password_confirmation']);

        $this->mock(RegistrationService::class)
            ->shouldReceive('register')
            ->with($validated)
            ->once();

        $this->postJson('/api/auth/register', $payload)
            ->assertStatus(201)
            ->assertJson([ 'status' => 'success' ]);
    }

    public function testLoginReturns200()
    {
        $payload = ['email' => 'user@test.com', 'password' => 'secret', 'remember' => true, 'twofa_code' => null];
        $result = [
            'message' => 'Logged in successfully',
            'token' => 'jwt.token',
            'user' => [
                'id' => 1,
                'firstname' => 'John',
                'lastname' => 'Doe',
                'email' => 'user@test.com',
                'role' => 'user',
                'twofa_enabled' => false,
            ],
        ];

        $this->mock(AuthService::class)
            ->shouldReceive('login')
            ->with($payload)
            ->once()
            ->andReturn($result);

        $this->postJson('/api/auth/login', $payload)
            ->assertOk()
            ->assertJson($result);
    }

    public function testEnableTwoFactorReturns200()
    {
        $user = User::factory()->create();
        /** @var string $response */
        $response = ['secret' => 'ABC123', 'qrCodeUrl' => 'otpauth://...'];

        $this->mock(TwoFactorService::class)
            ->shouldReceive('enable')
            ->withArgs(fn($u) => $u->id === $user->id)
            ->once()
            ->andReturn($response);

        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/auth/2fa/enable')
            ->assertOk()
            ->assertJson($response);
    }

    public function testLogoutReturns200()
    {
        $user = User::factory()->create();
        /** @var string $response */
        $response = ['message' => 'Logged out successfully'];

        $this->mock(AuthService::class)
            ->shouldReceive('logout')
            ->withArgs(fn($u) => $u->id === $user->id)
            ->once()
            ->andReturn($response);

        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson($response);
    }

    public function testForgotPasswordReturns200()
    {
        // Créer un utilisateur existant pour valider la règle exists
        $existingUser = User::factory()->create(['email' => 'user@example.com']);

        $payload = ['email' => 'user@example.com'];
        /** @var string $response */
        $response = ['message' => 'Password reset link sent'];

        $this->mock(AuthService::class)
            ->shouldReceive('sendResetLink')
            ->with($payload['email'])
            ->once()
            ->andReturn($response);

        $this->postJson('/api/auth/password/forgot', $payload)
            ->assertOk()
            ->assertJson($response);
    }

    public function testResetPasswordReturns200()
    {
        // Créer un utilisateur existant pour valider la règle exists
        $existingUser = User::factory()->create(['email' => 'user@example.com']);

        $payload = [
            'token' => 'abcdef123456',
            'email' => 'user@example.com',
            'password' => 'StrongP@ssword2025!',
            'password_confirmation' => 'StrongP@ssword2025!',
        ];
        /** @var string $response */
        $response = ['message' => 'Your password has been reset successfully.'];

        $this->mock(AuthService::class)
            ->shouldReceive('resetPassword')
            ->with($payload)
            ->once()
            ->andReturn($response);

        $this->postJson('/api/auth/password/reset', $payload)
            ->assertOk()
            ->assertJson($response);
    }

    public function testUpdatePasswordReturns200()
    {
        $user = User::factory()->create();
        $payload = [
            'current_password' => 'OldP@ssword2025!',
            'password' => 'NewP@ssword2025!',
            'password_confirmation' => 'NewP@ssword2025!',
        ];
        /** @var string $response */
        $response = ['message' => 'Password changed successfully'];

        $this->mock(AuthService::class)
            ->shouldReceive('updatePassword')
            ->withArgs(fn($u, $data) => $u->id === $user->id && Arr::except($data, ['password_confirmation']) == Arr::except($payload, ['password_confirmation']))
            ->once()
            ->andReturn($response);

        Sanctum::actingAs($user, ['*']);
        $this->patchJson('/api/auth/password', $payload)
            ->assertOk()
            ->assertJson($response);
    }

    public function testUpdateEmailReturns200()
    {
        $user = User::factory()->create();
        $payload = ['email' => 'new.email@example.com'];
        /** @var string $response */
        $response = ['message' => 'Verification email sent to the new address'];

        $this->mock(AuthService::class)
            ->shouldReceive('updateEmail')
            ->withArgs(fn($u, $email) => $u->id === $user->id && $email === $payload['email'])
            ->once()
            ->andReturn($response);

        Sanctum::actingAs($user, ['*']);
        $this->patchJson('/api/auth/email', $payload)
            ->assertOk()
            ->assertJson($response);
    }

    public function testDisableTwoFactorReturns204()
    {
        $user = User::factory()->create();
        $payload = ['twofa_code' => '123456'];

        $this->mock(AuthService::class)
            ->shouldReceive('disableTwoFactor')
            ->withArgs(fn($u, $code) => $u->id === $user->id && $code === $payload['twofa_code'])
            ->once()
            ->andReturnNull();

        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/auth/2fa/disable', $payload)
            ->assertNoContent();
    }
}

