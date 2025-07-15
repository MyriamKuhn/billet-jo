<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\EmailUpdatedNotification;
use Mockery;

class EmailUpdatedNotificationTest extends TestCase
{
    public function testViaReturnsMailChannel()
    {
        $notification = new EmailUpdatedNotification('new@example.com', 'old@example.com', 'raw-token-123');
        $channels = $notification->via((object) ['email' => 'user@example.com']);

        $this->assertSame(['mail'], $channels);
    }

    public function testToMailBuildsCorrectMailMessage()
    {
        // Arrange: prepare inputs
        $newEmail = 'new@example.com';
        $oldEmail = 'old@example.com';
        $rawToken = 'raw-token-123';
        $user = (object) ['email' => 'user@example.com', 'id' => 77];

        // Stub URL generation
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with(
                'auth.email.change.cancel',
                Mockery::on(fn($expires) => $expires instanceof \DateTime),
                ['token' => $rawToken]
            )
            ->andReturn('http://example.com/cancel?token=' . $rawToken);

        $notification = new EmailUpdatedNotification($newEmail, $oldEmail, $rawToken);

        // Act
        $mailMessage = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $expectedSubject = __('mail.subject_email_update_request_cancel', ['app_name' => env('APP_NAME')]);
        $this->assertEquals($expectedSubject, $mailMessage->subject);
        $this->assertEquals('emails.updaterevoke', $mailMessage->view);

        $viewData = $mailMessage->viewData;
        $this->assertArrayHasKey('user', $viewData);
        $this->assertSame($user, $viewData['user']);
        $this->assertArrayHasKey('newEmail', $viewData);
        $this->assertSame($newEmail, $viewData['newEmail']);
        $this->assertArrayHasKey('url', $viewData);
        $this->assertSame(
            'http://example.com/cancel?token=' . $rawToken,
            $viewData['url']
        );
    }

    public function testToArrayReturnsEmptyArray()
    {
        $notification = new EmailUpdatedNotification('n', 'o', 't');
        $array = $notification->toArray(new class {});

        $this->assertSame([], $array);
    }
}
