<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\VerifyNewEmailNotification;
use App\Models\User;
use Mockery;

class VerifyNewEmailNotificationTest extends TestCase
{
    public function testViaReturnsMailChannel()
    {
        $notification = new VerifyNewEmailNotification('dummy-token');
        $channels = $notification->via(new class {});

        $this->assertSame(['mail'], $channels);
    }

    public function testToMailBuildsCorrectMailMessage()
    {
        // Arrange: stub the signed URL generation
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with(
                'auth.email.change.verify',
                Mockery::on(fn($expires) => $expires instanceof \DateTime),
                ['token' => 'dummy-token']
            )
            ->andReturn('http://example.com/verify?token=dummy-token');

        // Create a notifiable user (without persisting)
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'firstname' => 'Alice',
            'lastname'  => 'Smith',
        ]);

        $notification = new VerifyNewEmailNotification('dummy-token');

        // Act
        $mailMessage = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals(
            __('mail.subject_email_update', ['app_name' => env('APP_NAME')]),
            $mailMessage->subject
        );
        $this->assertEquals('emails.newverify', $mailMessage->view);
        $this->assertArrayHasKey('user', $mailMessage->viewData);
        $this->assertSame($user, $mailMessage->viewData['user']);
        $this->assertArrayHasKey('url', $mailMessage->viewData);
        $this->assertSame('http://example.com/verify?token=dummy-token', $mailMessage->viewData['url']);
    }

    public function testToArrayReturnsEmptyArray()
    {
        $notification = new VerifyNewEmailNotification('dummy-token');
        $array = $notification->toArray(new class {});

        $this->assertSame([], $array);
    }
}

