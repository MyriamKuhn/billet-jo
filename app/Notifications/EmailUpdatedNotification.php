<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Class EmailUpdatedNotification
 *
 * This notification is sent to users when they request an email update.
 * It includes a link to cancel the email update request.
 */
class EmailUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * The new email address requested by the user.
     *
     * @var string
     */
    protected $newEmail;
    /**
     * The user’s previous email address.
     *
     * @var string
     */
    protected $oldEmail;
    /**
     * The raw token used to identify and cancel the email update.
     *
     * @var string
     */
    protected $rawToken;

    /**
     * Create a new notification instance.
     *
     * @param  string  $newEmail   The requested new email address.
     * @param  string  $oldEmail   The previous email address.
     * @param  string  $rawToken   The cancellation token.
     */
    public function __construct(string $newEmail, string $oldEmail, string $rawToken)
    {
        $this->newEmail = $newEmail;
        $this->oldEmail = $oldEmail;
        $this->rawToken = $rawToken;
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
     * Build the mail message to notify the user about the email update and provide a cancel link.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
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
     * Generate a temporary signed URL for cancelling the email update.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function getCancelLink($notifiable)
    {
        return URL::temporarySignedRoute(
            'auth.email.change.cancel', // The named route for cancelling
            now()->addHours(48), // Link valid for 48 hours
            [
                'token' => $this->rawToken  // Pass the cancellation token
                ],
        );
    }

    /**
     * Get the array representation of the notification (not used for mail).
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
