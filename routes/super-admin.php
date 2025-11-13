<?php

use App\Livewire\SuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for Super Admin users only. They have access to
| platform-wide administration including CDP management, organizations,
| and global settings.
|
*/

Route::middleware(['auth', 'verified', 'role.super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', SuperAdmin\Dashboard::class)->name('dashboard');

        // CDP Management
        Route::prefix('cdp')->name('cdp.')->group(function () {
            Route::get('/tiers', SuperAdmin\Cdp\TierManager::class)->name('tiers.index');
            Route::get('/products', SuperAdmin\Cdp\ProductManager::class)->name('products.index');
            Route::get('/segments', SuperAdmin\Cdp\SegmentBuilder::class)->name('segments.index');
            Route::get('/campaigns', SuperAdmin\Cdp\CampaignManager::class)->name('campaigns.index');
            Route::get('/templates', SuperAdmin\Cdp\TemplateManager::class)->name('templates.index');
            Route::get('/sync-monitor', SuperAdmin\Cdp\SyncMonitor::class)->name('sync-monitor');
        });

        // Organization Management
        Route::prefix('organizations')->name('organizations.')->group(function () {
            Route::get('/', SuperAdmin\Organizations\OrganizationManager::class)->name('index');
            Route::get('/{organization}', SuperAdmin\Organizations\OrganizationDetails::class)->name('show');
            Route::get('/{organization}/tier', SuperAdmin\Organizations\OrganizationTierAssignment::class)->name('tier');
        });

        // Domain Management
        Route::prefix('domains')->name('domains.')->group(function () {
            Route::get('/', SuperAdmin\Domains\DomainManager::class)->name('index');
        });

        // User Management
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', SuperAdmin\Users\UserManager::class)->name('index');
            Route::get('/{user}/roles', SuperAdmin\Users\UserRoleAssignment::class)->name('roles');
        });

        // Settings
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/custom-fields', SuperAdmin\Settings\CustomFieldsManager::class)->name('custom-fields');
            Route::get('/sync-logs', SuperAdmin\Settings\SyncLogs::class)->name('sync-logs');
        });
    });
