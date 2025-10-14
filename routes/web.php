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
});

// Campaign Monitor Import Routes
Route::prefix('campaign-monitor')->name('campaign-monitor.')->group(function () {
    Route::get('/import', [CampaignMonitorImportController::class, 'index'])->name('import');
    Route::post('/upload', [CampaignMonitorImportController::class, 'upload'])->name('upload');
    Route::post('/import', [CampaignMonitorImportController::class, 'import'])->name('import.process');
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

require __DIR__.'/auth.php';
