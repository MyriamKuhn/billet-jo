<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Auth\CaptchaService::class, function ($app) {
            $recaptcha = config('services.recaptcha');
            return new \App\Services\Auth\CaptchaService(
                $recaptcha['secret'] ?? '',
                $recaptcha['site_verify_url']
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
