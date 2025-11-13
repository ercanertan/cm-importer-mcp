<?php

use App\Livewire\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Dashboard Routes
|--------------------------------------------------------------------------
|
| These routes are for regular users. They can manage their profile,
| product subscriptions, and view their activity history.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('my')
    ->name('user.')
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', User\Dashboard::class)->name('dashboard');

        // Profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/edit', User\Profile\EditProfile::class)->name('edit');
            Route::get('/consent', User\Profile\ManageConsent::class)->name('consent');
        });

        // Subscriptions
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', User\Subscriptions\ManageSubscriptions::class)->name('index');
            Route::get('/preferences', User\Subscriptions\SubscriptionPreferences::class)->name('preferences');
        });

        // Activity
        Route::prefix('activity')->name('activity.')->group(function () {
            Route::get('/history', User\Activity\ActivityHistory::class)->name('history');
        });
    });
