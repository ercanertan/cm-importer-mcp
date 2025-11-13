<?php

use App\Http\Controllers\CampaignMonitorImportController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Smart dashboard redirect based on user role
Route::get('/dashboard', DashboardController::class)
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
});

// Campaign Monitor Import Routes
Route::prefix('campaign-monitor')->name('campaign-monitor.')->group(function () {
    Route::get('/import', [CampaignMonitorImportController::class, 'index'])->name('import');
    Route::post('/upload', [CampaignMonitorImportController::class, 'upload'])->name('upload');
    Route::post('/import', [CampaignMonitorImportController::class, 'import'])->name('import.process');
    Route::get('/import/{id}', [CampaignMonitorImportController::class, 'show'])->name('show');
    Route::post('/preview', [CampaignMonitorImportController::class, 'preview'])->name('preview');
    Route::get('/status/{id}', [CampaignMonitorImportController::class, 'status'])->name('status');
    Route::get('/stream-import', [CampaignMonitorImportController::class, 'streamImport'])->name('stream-import');
});

require __DIR__.'/auth.php';

// Role-based route files
require __DIR__.'/super-admin.php';
require __DIR__.'/org-admin.php';
require __DIR__.'/user.php';
