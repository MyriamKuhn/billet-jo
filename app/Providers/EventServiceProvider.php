<?php

namespace App\Providers;

use App\Events\PaymentSucceeded;
use App\Listeners\GenerateTicketsForPayment;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\InvoiceRequested;
use App\Listeners\GenerateInvoicePdf;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;

/**
 * The event service provider.
 *
 * This class is responsible for registering event listeners and bootstrapping
 * any necessary functionality during the application's lifecycle.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // When an invoice is requested, generate its PDF
        InvoiceRequested::class => [
            GenerateInvoicePdf::class,
        ],
        // After a payment succeeds, generate tickets
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

        // Remove Laravel’s default listener for the Registered → SendEmailVerificationNotification event
        $this->app['events']->forget(Registered::class, [SendEmailVerificationNotification::class, 'handle']);
    }
}
