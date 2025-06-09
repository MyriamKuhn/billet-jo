<?php

namespace App\Providers;

use App\Events\PaymentSucceeded;
use App\Listeners\GenerateTicketsForPayment;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\InvoiceRequested;
use App\Listeners\GenerateInvoicePdf;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event → listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        InvoiceRequested::class => [
            GenerateInvoicePdf::class,
        ],
        PaymentSucceeded::class => [
            GenerateTicketsForPayment::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // “Un-hook” the framework’s default Registered → SendEmailVerificationNotification
        $this->app['events']->forget(Registered::class, [SendEmailVerificationNotification::class, 'handle']);
    }
}
