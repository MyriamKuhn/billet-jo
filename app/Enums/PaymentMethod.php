<?php

namespace App\Enums;

/**
 * The set of payment methods supported by our application.
 */
enum PaymentMethod: string
{
    /**
     * Process payment via PayPal.
     */
    case Paypal = 'paypal';
    /**
     * Process payment via Stripe.
     */
    case Stripe = 'stripe';
    /**
     * No payment required (free).
     */
    case Free = 'free';
}
