<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Ticket;
use Illuminate\Support\Facades\Storage;

class TicketsGenerated extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    /** @var \Illuminate\Support\Collection|Ticket[] */
    public $tickets;

    /**
     * Create a new message instance.
     *
     * @param \App\Models\User $user
     * @param \Illuminate\Support\Collection|Ticket[] $tickets
     */
    public function __construct(User $user, $tickets)
    {
        $this->user    = $user;
        $this->tickets = collect($tickets);
    }

    public function build()
    {
        $mail = $this
            ->subject(__('mail.tickets_generated_subject', ['app_name'=> env('APP_NAME')]))
            ->view('emails.tickets.generated')      // on passe en view()
            ->with([
                'user'    => $this->user,
                'tickets' => $this->tickets,
                'clientUrl' => rtrim(config('app.frontend_url'), '/') . '/client/tickets',
            ]);

        // Attacher chaque PDF
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
