<?php

namespace App\Enums;

/**
 * Represents the various lifecycle states of a ticket.
 */
enum TicketStatus: string
{
    /**
     * The ticket has been generated and is ready for use.
     */
    case Issued = 'issued';
    /**
     * The ticket has been redeemed for entry or service.
     */
    case Used = 'used';
    /**
     * The ticket has been returned and the payment refunded.
     */
    case Refunded = 'refunded';
    /**
     * The ticket has been voided before it was used.
     */
    case Cancelled = 'cancelled';
}
