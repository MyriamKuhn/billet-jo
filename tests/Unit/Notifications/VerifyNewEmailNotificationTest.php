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
        // Crée un faux utilisateur (en mémoire) :
        $user = User::factory()->make();

        // Instancie ta notif avec l'user ET le token :
        $notification = new VerifyNewEmailNotification($user, 'dummy-token');

        // via() attend un "notifiable", here c'est bien $user
        $channels = $notification->via($user);

        $this->assertSame(['mail'], $channels);
    }

    public function testToMailBuildsCorrectMailMessage()
    {
        // Arrange: stub du generation de l’URL signée
        URL::shouldReceive('temporarySignedRoute')
            ->once()
            ->with(
                'auth.email.change.verify',
                Mockery::on(fn($expires) => $expires instanceof \DateTime),
                ['token' => 'dummy-token']
            )
            ->andReturn('http://example.com/verify?token=dummy-token');

        // Création d’un "notifiable" user en mémoire
        $user = User::factory()->make([
            'email'     => 'user@example.com',
            'firstname' => 'Alice',
            'lastname'  => 'Smith',
        ]);

        // On passe bien l’utilisateur ET le token
        $notification = new VerifyNewEmailNotification($user, 'dummy-token');

        // Act
        $mailMessage = $notification->toMail($user);

        // Assert
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals(
            __('mail.subject_email_update', ['app_name' => config('app.name')]),
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
        // Crée un "notifiable" user en mémoire
        $user = User::factory()->make();

        // On passe bien l’utilisateur ET le token
        $notification = new VerifyNewEmailNotification($user, 'dummy-token');

        // toArray() ne se sert que du notifiable, on lui passe donc $user
        $array = $notification->toArray($user);

        $this->assertSame([], $array);
    }
}

