<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a user requests a password reset.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    protected $token;

    /**
     * Create a new notification instance.
     *
     * @param  string  $token  The password reset token.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Determine which channels to send the notification through.
     *
     * @param  mixed  $notifiable
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail message for the password reset.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Choose front-end URL based on the environment
        $frontendUrl = match (app()->environment()) {
            'production' => 'https://jo2024.mkcodecreations.dev',
            default => 'http://localhost:3000',
        };

        // Construct the password reset link with token, email and locale
        $url = $frontendUrl . '/password-reset?token=' . $this->token . '&email=' . urlencode($notifiable->email) . '&locale=' . app()->getLocale();

        return (new MailMessage)
            ->subject(__('mail.subject_password', ['app_name'=> config('app.name')]))
            ->view('emails.password', [
                'user' => $notifiable,
                'url' => $url,
            ],
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
