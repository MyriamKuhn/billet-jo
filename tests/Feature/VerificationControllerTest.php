<?php

namespace Tests\Feature;

use App\Events\Verified;
use App\Models\User;
use App\Services\CartService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testVerificationFailsIfUserNotFound()
    {
        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ValidateSignature::class,
        ]);

        $response = $this->getJson('/api/auth/email/verify/999/invalidhash');
        $response->assertStatus(404)
                ->assertJsonFragment(['message' => __('validation.user_not_found')]);
    }

    public function testVerificationFailsIfHashDoesNotMatch()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $wrongHash = sha1('wrong@example.com');

        $this->withoutMiddleware([
            \Illuminate\Routing\Middleware\ValidateSignature::class,
        ]);

        $response = $this->getJson("/api/auth/email/verify/{$user->id}/{$wrongHash}");
        $response->assertStatus(400)
                ->assertJsonFragment(['message' => __('validation.email_verification_invalid')]);
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

        $response->assertStatus(409)
                ->assertJsonFragment(['message' => __('validation.email_already_verified')]);
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
        $response->assertStatus(500)
                ->assertJsonFragment(['message' => __('validation.unknown_error')]);
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
        $response->assertStatus(409)
                ->assertJsonFragment(['message' => __('validation.email_already_verified')]);
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
}

