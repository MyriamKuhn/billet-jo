<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Factory as HttpClient;
use App\Services\Auth\CaptchaService;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Storage;

/**
 * The application service provider.
 *
 * This class is responsible for registering application services and bootstrapping
 * any necessary functionality during the application's lifecycle.
 *
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the CaptchaService as a singleton.
        $this->app->singleton(CaptchaService::class, function ($app) {
            $recaptcha = config('services.recaptcha', []);

            return new CaptchaService(
                $recaptcha['secret']            ?? '',
                $recaptcha['site_verify_url']   ?? '',
                $app->make(HttpClient::class)
            );
        });

        // Bind the Stripe client as a singleton.
        $this->app->singleton(StripeClient::class, fn() =>
            new StripeClient(config('services.stripe.secret'))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure storage directories exist for generated assets.
        Storage::disk('invoices')->makeDirectory('');
        Storage::disk('qrcodes')->makeDirectory('');
        Storage::disk('tickets')->makeDirectory('');
        Storage::disk('images')->makeDirectory('');
    }
}
