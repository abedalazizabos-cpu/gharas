<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // The UI uses Bootstrap, not Tailwind, so page links need Bootstrap markup.
        Paginator::useBootstrapFive();

        if ($this->app->isProduction()) {
            // Links and redirects always use https once the site has a certificate.
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
            // Blocks commands that wipe the live database (migrate:fresh, db:wipe, migrate:reset).
            DB::prohibitDestructiveCommands();
        }
    }
}
