<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Paypal = 'paypal';
    case Stripe = 'stripe';
}
