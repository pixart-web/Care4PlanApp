<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Europe/Lisbon'));
        App::setLocale(config('app.locale', 'pt'));
        Carbon::setLocale('pt_PT');
    }
}
