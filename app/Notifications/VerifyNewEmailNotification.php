<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\User;

class VerifyNewEmailNotification extends Notification
{
    use Queueable;

    /**
     * The token for resetting the password.
     *
     * @var string
     */
    protected $user;
    protected $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject(__('mail.subject_email_update', ['app_name'=> config('app.name')]))
            ->view('emails.newverify', [
                'user' => $this->user,
                'url' => $url,
            ]);
    }

    /**
     * Get the URL for the email verification.
     *
     * @param  \App\Models\User  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute( //Generate a temporary signed URL
            'auth.email.change.verify',
            now()->addMinutes(60), //The URL will be valid for 60 minutes
            ['token' => $this->token]
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
