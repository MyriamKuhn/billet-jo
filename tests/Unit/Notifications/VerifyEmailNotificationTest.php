<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\VerifyEmailNotification;
use App\Models\User;
use Mockery;

class VerifyEmailNotificationTest extends TestCase
{
    public function testViaReturnsMailChannel()
    {
        $notification = new VerifyEmailNotification();
        $channels = $notification->via(new class {
            public function getKey() { return 1; }
            public function getEmailForVerification() { return 'user@example.com'; }
        });

        $this->assertSame(['mail'], $channels);
    }

    public function testToMailBuildsCorrectMailMessage()
    {
        // Arrange: stub la génération d'URL signée
        $user = User::factory()->make([
            'id' => 42,
            'email' => 'user@example.com',
            // getEmailForVerification returns email by default
        ]);

        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with(
                'auth.email.verify',
                Mockery::on(fn($expires) => $expires instanceof \DateTime),
                [
                    'id'   => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ]
            )
            ->andReturn('http://example.com/verify?id=42&hash=' . sha1($user->getEmailForVerification()));

        Config::set('app.name', 'MyApp');

        $notification = new VerifyEmailNotification();

        // Act
        $mailMessage = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $expectedSubject = __('mail.subject', ['app_name' => env('APP_NAME')]);
        $this->assertEquals($expectedSubject, $mailMessage->subject);
        $this->assertEquals('emails.verify', $mailMessage->view);
        $this->assertArrayHasKey('user', $mailMessage->viewData);
        $this->assertSame($user, $mailMessage->viewData['user']);
        $this->assertArrayHasKey('url', $mailMessage->viewData);
        $this->assertStringStartsWith('http://example.com/verify?', $mailMessage->viewData['url']);
    }

    public function testToArrayReturnsEmptyArray()
    {
        $notification = new VerifyEmailNotification();
        $array = $notification->toArray(new class {});

        $this->assertSame([], $array);
    }
}
