<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Factory as HttpClient;
use App\Services\Auth\CaptchaService;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Storage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CaptchaService
        $this->app->singleton(CaptchaService::class, function ($app) {
            $recaptcha = config('services.recaptcha', []);

            return new CaptchaService(
                $recaptcha['secret']            ?? '',
                $recaptcha['site_verify_url']   ?? '',
                $app->make(HttpClient::class)
            );
        });

        // StripeClient
        $this->app->singleton(StripeClient::class, fn() =>
            new StripeClient(config('services.stripe.secret'))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::disk('invoices')->makeDirectory('');
        Storage::disk('qrcodes')->makeDirectory('');
        Storage::disk('tickets')->makeDirectory('');
        Storage::disk('images')->makeDirectory('');
    }
}
