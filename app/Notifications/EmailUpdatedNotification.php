<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class EmailUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * The token for resetting the password.
     *
     * @var string
     */
    protected $newEmail;
    protected $oldEmail;
    protected $rawToken;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $newEmail, string $oldEmail, string $rawToken)
    {
        $this->newEmail = $newEmail;
        $this->oldEmail = $oldEmail;
        $this->rawToken = $rawToken;
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
        $url = $this->getCancelLink($notifiable);

        return (new MailMessage)
            ->subject(__('mail.subject_email_update_request_cancel', ['app_name'=> config('app.name')]))
            ->view('emails.updaterevoke', [
                'user' => $notifiable,
                'newEmail'=> $this->newEmail,
                'url' => $url,
            ]);
    }

    /**
     * Get the URL to cancel the email update.
     *
     * @param  \App\Models\User  $notifiable
     * @return string
     */
    protected function getCancelLink($notifiable)
    {
        return URL::temporarySignedRoute(
            'auth.email.change.cancel', // Route to cancel the update
            now()->addHours(48), // URL will be valid for 48 hours
            [
                'token' => $this->rawToken,
                'old_email' => $this->oldEmail
                ],
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
