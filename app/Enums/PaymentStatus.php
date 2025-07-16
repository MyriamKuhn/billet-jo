<?php

namespace App\Enums;

/**
 * Represents the lifecycle states of a payment.
 */
enum PaymentStatus: string
{
    /**
     * The payment has been created but not yet completed.
     */
    case Pending = 'pending';
    /**
     * The payment was successfully processed.
     */
    case Paid = 'paid';
    /**
     * The payment attempt failed.
     */
    case Failed = 'failed';
    /**
     * A previously successful payment has been reversed.
     */
    case Refunded = 'refunded';
}
