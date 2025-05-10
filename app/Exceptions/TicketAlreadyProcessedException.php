<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Ticket;

class TicketAlreadyProcessedException extends HttpException
{
    public Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        // Message générique, le handler l’agrémentera
        parent::__construct(409, 'Ticket already processed');
        $this->ticket = $ticket;
    }
}
