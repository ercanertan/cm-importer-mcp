<?php

use App\Livewire\OrgAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organization Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for Organization Admin users. They can manage their
| organization's team members, product subscriptions, and view reports.
| Super Admins also have access to these routes.
|
*/

Route::middleware(['auth', 'verified', 'role.org_admin'])
    ->prefix('org')
    ->name('org.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', OrgAdmin\Dashboard::class)->name('dashboard');

        // Team Management
        Route::prefix('team')->name('team.')->group(function () {
            Route::get('/members', OrgAdmin\Team\TeamMembers::class)->name('members');
            Route::get('/invite', OrgAdmin\Team\InviteUser::class)->name('invite');
        });

        // Product Management
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/subscriptions', OrgAdmin\Products\ProductSubscriptions::class)->name('subscriptions');
            Route::get('/bulk-subscribe', OrgAdmin\Products\BulkSubscribe::class)->name('bulk-subscribe');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/activity', OrgAdmin\Reports\ActivityReport::class)->name('activity');
            Route::get('/engagement', OrgAdmin\Reports\EngagementMetrics::class)->name('engagement');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', OrgAdmin\Settings\OrganizationSettings::class)->name('index');
        });
    });
