<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use App\Exceptions\Auth\UserNotFoundException;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Exceptions\Auth\AlreadyVerifiedException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Mockery;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmailVerificationService::class);
    }

    public function testVerifyThrowsUserNotFoundException()
    {
        $this->expectException(UserNotFoundException::class);
        $this->service->verify(999, 'anyhash');
    }

    public function testVerifyThrowsInvalidVerificationLinkException()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->expectException(InvalidVerificationLinkException::class);
        $this->service->verify($user->id, 'wrong-hash');
    }

    public function testVerifyThrowsAlreadyVerifiedException()
    {
        $user = User::factory()->create();
        // Marquer déjà vérifié
        $user->markEmailAsVerified();
        $user->save();

        $hash = sha1($user->getEmailForVerification());
        $this->expectException(AlreadyVerifiedException::class);
        $this->service->verify($user->id, $hash);
    }

    public function testVerifyMarksEmailAsVerifiedAndDispatchesEvent()
    {
        Event::fake();

        $user = User::factory()->create(['email_verified_at' => null]);
        $hash = sha1($user->getEmailForVerification());

        $returned = $this->service->verify($user->id, $hash);

        // Retourne bien une instance User
        $this->assertInstanceOf(User::class, $returned);
        // Email désormais vérifié
        $this->assertTrue($returned->hasVerifiedEmail());
        $this->assertNotNull($returned->email_verified_at);

        // Un (ou plusieurs) event Registered a été dispatché
        Event::assertDispatched(Verified::class, fn($e) => $e->user->id === $user->id);
    }

    public function testResendThrowsAlreadyVerifiedExceptionWhenAlreadyVerified()
    {
        // 1) Create a real user and mark as verified
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // 2) Expect the AlreadyVerifiedException
        $this->expectException(AlreadyVerifiedException::class);

        // 3) Call with the email string
        $this->service->resend($user->email);
    }

    public function testResendSendsNotificationAndReturnsMessageWhenNotVerified()
    {
        // 1) Create a *real* user who is still un-verified.
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 2) Fake notifications now (so nothing actually goes out).
        Notification::fake();

        // 3) Call your service by passing in the user’s email address.
        $result = $this->service->resend($user->email);

        // 4) It still returns the “message” array.
        $this->assertSame(['message' => 'Verification email resent'], $result);

        // 5) And *this* time, Laravel’s VerifyEmail notification was queued/sent.
        Notification::assertSentTo(
            $user,
            \App\Notifications\VerifyEmailNotification::class,
            function ($notification, $channels) {
                // Optional: you can inspect $notification->toMail($user) or $channels.
                return true;
            }
        );
    }

    public function testResendThrowsUserNotFoundExceptionWhenEmailDoesNotExist()
    {
        Notification::fake(); // on intercepte tout envoi
        $this->expectException(UserNotFoundException::class);

        $this->service->resend('does-not-exist@example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
