<?php

namespace Tests\Unit\Notifications;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\ResetPasswordNotification;
use Mockery;

class ResetPasswordNotificationTest extends TestCase
{
    public function testViaReturnsMailChannel()
    {
        $notification = new ResetPasswordNotification('token123');
        $channels = $notification->via((object) ['email' => 'user@example.com']);

        $this->assertSame(['mail'], $channels);
    }

    public function testToMailUsesLocalhostUrlByDefault()
    {
        // Ensure non-production environment
        $this->app['env'] = 'testing';

        Config::set('app.name', 'MyApp');

        $notifiable = (object) ['email' => 'user@example.com'];
        $token = 'abc123';
        $notification = new ResetPasswordNotification($token);

        /** @var MailMessage $mailMessage */
        $mailMessage = $notification->toMail($notifiable);

        // Check subject
        $expectedSubject = __('mail.subject_password', ['app_name' => env('APP_NAME')]);
        $this->assertEquals($expectedSubject, $mailMessage->subject);

        // Check view and data
        $this->assertEquals('emails.password', $mailMessage->view);
        $this->assertArrayHasKey('user', $mailMessage->viewData);
        $this->assertSame($notifiable, $mailMessage->viewData['user']);
        $expectedUrl = 'http://localhost:3000/password-reset?token=' . $token . '&email=' . urlencode($notifiable->email);
        $this->assertEquals($expectedUrl, $mailMessage->viewData['url']);
    }

    public function testToMailUsesProductionUrlWhenEnvProduction()
    {
        // Force production environment
        $this->app['env'] = 'production';

        Config::set('app.name', 'MyApp');

        $notifiable = (object) ['email' => 'test@example.com'];
        $token = 'xyz789';
        $notification = new ResetPasswordNotification($token);

        $mailMessage = $notification->toMail($notifiable);

        $expectedUrl = 'https://jo2024.mkcodecreations.dev/password-reset?token=' . $token . '&email=' . urlencode($notifiable->email);
        $this->assertEquals($expectedUrl, $mailMessage->viewData['url']);
    }

    public function testToArrayReturnsEmptyArray()
    {
        $notification = new ResetPasswordNotification('any');
        $array = $notification->toArray((object) ['email'=>'e']);

        $this->assertSame([], $array);
    }
}
