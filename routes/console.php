<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Campaign Monitor Scheduled Tasks
|--------------------------------------------------------------------------
|
| Schedule Campaign Monitor tag synchronization to avoid API call storms.
| This command runs every 10 minutes to batch sync tags for users who
| need updates (tier, engagement, status changes).
|
*/

// Sync Campaign Monitor tags every 10 minutes
// Processes users marked with cm_tags_need_sync = true
Schedule::command('cm:sync-tags --limit=1000')
    ->everyTenMinutes()
    ->withoutOverlapping() // Prevent concurrent runs
    ->runInBackground()
    ->onSuccess(function () {
        \Illuminate\Support\Facades\Log::info('CM tag sync: Scheduled run completed successfully');
    })
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('CM tag sync: Scheduled run failed');
    });
