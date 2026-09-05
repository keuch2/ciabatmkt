<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // El enlace del correo apunta a la ruta de la SPA, no a una vista de Laravel.
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return config('app.url').'/reset-password/'.$token.'?email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
