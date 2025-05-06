<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Factory as HttpClient;
use App\Services\Auth\CaptchaService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CaptchaService::class, function ($app) {
            $recaptcha = config('services.recaptcha', []);

            return new CaptchaService(
                $recaptcha['secret']            ?? '',
                $recaptcha['site_verify_url']   ?? '',
                $app->make(HttpClient::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
