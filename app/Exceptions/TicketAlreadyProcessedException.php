<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Ticket;

/**
 * Thrown when an operation is attempted on a ticket
 * that has already been processed (e.g., used, refunded, or cancelled).
 *
 * Results in a 409 Conflict HTTP response.
 */
class TicketAlreadyProcessedException extends HttpException
{
    /**
     * The ticket instance that triggered this exception.
     *
     * @var Ticket
     */
    public Ticket $ticket;

    /**
     * Create a new TicketAlreadyProcessedException instance.
     *
     * @param  Ticket  $ticket  The Ticket model that was already processed.
     */
    public function __construct(Ticket $ticket)
    {
        // Message générique, le handler l’agrémentera
        parent::__construct(409, 'Ticket already processed');
        $this->ticket = $ticket;
    }
}
