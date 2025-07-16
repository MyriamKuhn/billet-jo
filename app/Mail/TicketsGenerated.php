<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

/**
 * Class TicketsGenerated
 *
 * This Mailable class is responsible for sending an email to the user
 * with the generated tickets attached as PDFs.
 */
class TicketsGenerated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The user who will receive the tickets.
     *
     * @var \App\Models\User
     */
    public User $user;

    /**
     * A collection of generated tickets.
     *
     * @var \Illuminate\Support\Collection|\App\Models\Ticket[]
     */
    public $tickets;

    /**
     * Initialize the mailable with the user and their tickets.
     *
     * @param  \App\Models\User  $user
     * @param  \Illuminate\Support\Collection|\App\Models\Ticket[]  $tickets
     */
    public function __construct(User $user, $tickets)
    {
        $this->user    = $user;
        $this->tickets = collect($tickets);
    }

    /**
     * Build the email message.
     *
     * Attaches each ticket PDF and passes the necessary data to the view.
     *
     * @return $this
     */
    public function build()
    {
        // Frontend URL for direct access to the user's tickets page
        $clientUrl = rtrim(config('app.frontend_url'), '/') . '/user/tickets';

        $mail = $this
            ->subject(__('mail.tickets_generated_subject', ['app_name'=> config('app.name')]))
            ->view('emails.tickets.generated')
            ->with([
                'user'    => $this->user,
                'tickets' => $this->tickets,
                'clientUrl'     => $clientUrl,
            ]);

        // Attach each ticket PDF file to the email
        foreach ($this->tickets as $ticket) {
            $path = Storage::disk('tickets')->path($ticket->pdf_filename);
            $mail->attach($path, [
                'as'   => $ticket->pdf_filename,
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
