<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // The admin stylesheet writes its own pagination rules against this
        // markup, so there is no Tailwind build step to keep in step with.
        Paginator::useBootstrapFour();
    }
}
