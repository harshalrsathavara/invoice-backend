<?php

namespace App\Providers;

use App\Support\BusinessScope;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: the middleware sets which business the panel is
        // open on, and controllers resolved later read the same object.
        $this->app->scoped(BusinessScope::class);
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
