<?php

use App\Http\Controllers\CampaignMonitorImportController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Admin Organization Management
    Route::get('/admin/organizations', \App\Livewire\Admin\Organizations\Index::class)
        ->name('admin.organizations.index');

    // Admin Domain Management
    Route::get('/admin/domains', \App\Livewire\Admin\Domains\Index::class)
        ->name('admin.domains.index');

    // Admin Sync Logs
    Route::get('/admin/sync-logs', \App\Livewire\Admin\SyncLogs\Index::class)
        ->name('admin.sync-logs.index');

    // Admin User Management
    Route::get('/admin/users', \App\Livewire\Admin\Users\Index::class)
        ->name('admin.users.index');
});

// Campaign Monitor Import Routes
// TODO: Add authorization middleware when user roles are implemented
// Example: ->middleware(['auth', 'can:import-campaign-monitor']) or ->middleware(['auth', 'role:super-admin'])
Route::prefix('campaign-monitor')->name('campaign-monitor.')->group(function () {
    // Import pages - should be restricted to super-admin only
    Route::get('/import', [CampaignMonitorImportController::class, 'index'])->name('import');
    Route::post('/upload', [CampaignMonitorImportController::class, 'upload'])->name('upload');
    Route::post('/import', [CampaignMonitorImportController::class, 'import'])->name('import.process');

    // View import details - can be accessible to more roles if needed
    Route::get('/import/{id}', [CampaignMonitorImportController::class, 'show'])->name('show');
    Route::post('/preview', [CampaignMonitorImportController::class, 'preview'])->name('preview');
    Route::get('/status/{id}', [CampaignMonitorImportController::class, 'status'])->name('status');

    // Import progress route (uses controller to bypass layout)
    Route::get('/import-progress/{importId}', [CampaignMonitorImportController::class, 'progress'])
        ->name('import-progress');

    // Start import in background (returns immediately)
    Route::post('/start-import/{importId}', [CampaignMonitorImportController::class, 'startImport'])
        ->name('start-import');
});

// Admin Domain Management Routes (Old API - Replaced by Livewire UI)
// These routes are commented out as all functionality is now handled by Livewire components
// If you need API endpoints, create them under a different prefix like /api/admin/domains
/*
Route::prefix('admin/domains')->name('admin.domains.')->group(function () {
    Route::get('/', [DomainController::class, 'index'])->name('index');
    Route::post('/', [DomainController::class, 'store'])->name('store');
    Route::get('/{id}', [DomainController::class, 'show'])->name('show');
    Route::post('/{id}/sync', [DomainController::class, 'sync'])->name('sync');
    Route::post('/{id}/associate-organization', [DomainController::class, 'associateOrganization'])->name('associate-organization');
    Route::post('/{id}/dissociate-organization', [DomainController::class, 'dissociateOrganization'])->name('dissociate-organization');
    Route::post('/sync-all', [DomainController::class, 'syncAll'])->name('sync-all');
    Route::delete('/{id}', [DomainController::class, 'destroy'])->name('destroy');
});
*/

require __DIR__.'/auth.php';
