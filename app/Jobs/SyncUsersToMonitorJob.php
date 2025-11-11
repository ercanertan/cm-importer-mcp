<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\CmSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUsersToMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour
    public int $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(CmSyncService $cmSyncService): void
    {
        Log::info('Starting bulk user sync to Campaign Monitor');

        // Get all unsynced users
        $unsyncedUsers = User::where(function ($query) {
            $query->whereNull('cm_subscriber_id')
                  ->orWhere('cm_status', '!=', 'active');
        })->get();

        if ($unsyncedUsers->isEmpty()) {
            Log::info('No users need syncing to Campaign Monitor');
            return;
        }

        Log::info('Found users to sync', ['count' => $unsyncedUsers->count()]);

        $synced = 0;
        $errors = 0;

        foreach ($unsyncedUsers as $user) {
            try {
                // Use the existing sync service to create or update user in CM
                $cmSyncService->syncUserToMonitor($user);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Error syncing user to Campaign Monitor', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk user sync to Campaign Monitor completed', [
            'synced' => $synced,
            'errors' => $errors,
            'total' => $unsyncedUsers->count(),
        ]);
    }
}
