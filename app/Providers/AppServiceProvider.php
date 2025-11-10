<?php

namespace App\Providers;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use App\Observers\DomainObserver;
use App\Observers\OrganizationObserver;
use App\Observers\UserObserver;
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
        // Register Domain observer for automatic user assignment
        Domain::observe(DomainObserver::class);

        // Register User observer for Campaign Monitor sync
        User::observe(UserObserver::class);

        // Register Organization observer for tier changes affecting multiple users
        Organization::observe(OrganizationObserver::class);
    }
}
