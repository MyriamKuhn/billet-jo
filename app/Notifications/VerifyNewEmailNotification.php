<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use App\Models\User;

/**
 * Notification sent when a user must verify their new email address.
 */
class VerifyNewEmailNotification extends Notification
{
    use Queueable;

    /**
     * The user who requested the email change.
     *
     * @var \App\Models\User
     */
    protected $user;
    /**
     * The raw verification token.
     *
     * @var string
     */
    protected $token;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\User  $user   The user changing their email
     * @param  string            $token  The verification token
     */
    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
    }

    /**
     * Determine which channels the notification will be delivered on.
     *
     * @param  mixed  $notifiable
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail message for new-email verification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
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
     * Generate a temporary signed URL for verifying the new email.
     *
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'auth.email.change.verify', // Named route for new-email verification
            now()->addMinutes(60), // URL valid for 60 minutes
            ['token' => $this->token]
        );
    }

    /**
     * Get the array representation of the notification (for database, etc.).
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
