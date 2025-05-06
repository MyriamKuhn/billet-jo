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
use Mockery;

class EmailVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailVerificationService();
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

    public function testResendThrowsHttpResponseExceptionWhenAlreadyVerified()
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasVerifiedEmail')->once()->andReturnTrue();
        $user->shouldNotReceive('sendEmailVerificationNotification');

        $this->expectException(HttpResponseException::class);

        try {
            $this->service->resend($user);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertSame(409, $response->getStatusCode());

            $payload = $response->getData(true);
            $this->assertSame('Email already verified', $payload['message']);
            $this->assertSame('already_verified',       $payload['code']);

            throw $e;
        }
    }

    public function testResendSendsNotificationAndReturnsMessageWhenNotVerified()
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasVerifiedEmail')->once()->andReturnFalse();
        $user->shouldReceive('sendEmailVerificationNotification')->once();

        $result = $this->service->resend($user);

        $this->assertSame(
            ['message' => 'Verification email resent'],
            $result
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
