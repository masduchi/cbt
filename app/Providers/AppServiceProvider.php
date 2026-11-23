<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->guardProductionSettings();
    }

    /**
     * Pastikan lingkungan produksi tidak pernah membocorkan detail internal.
     */
    private function guardProductionSettings(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        if (config('app.debug')) {
            config(['app.debug' => false]);
            Log::critical('APP_DEBUG aktif pada lingkungan produksi; dimatikan otomatis.');
        }

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        if (! config('session.secure')) {
            Log::warning('SESSION_SECURE_COOKIE belum aktif pada lingkungan produksi.');
        }

        if (str_starts_with((string) config('app.url'), 'http://')) {
            Log::warning('APP_URL masih memakai http:// pada lingkungan produksi.');
        }

        if (config('mail.default') === 'log') {
            Log::warning('MAIL_MAILER=log: tautan reset password tidak dikirim ke email pengguna.');
        }
    }
}
