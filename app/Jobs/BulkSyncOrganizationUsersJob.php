<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\CmSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkSyncOrganizationUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600; // 10 minutes
    public $backoff = [60, 180, 600]; // Retry delays

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $organizationId,
        public string $reason = 'manual',
        public array $fieldsToSync = []
    ) {
        // Default to tier and organization_name if not specified
        if (empty($this->fieldsToSync)) {
            $this->fieldsToSync = ['user_id', 'tier', 'organization_name'];
        }
    }

    /**
     * Execute the job.
     */
    public function handle(CmSyncService $cmSyncService): void
    {
        $organization = Organization::find($this->organizationId);

        if (!$organization) {
            Log::warning('BulkSyncOrganizationUsersJob: Organization not found', [
                'organization_id' => $this->organizationId,
            ]);
            return;
        }

        Log::info('Starting bulk sync for organization users', [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'reason' => $this->reason,
            'fields_to_sync' => $this->fieldsToSync,
        ]);

        $users = $organization->users()->where('cm_status', 'active')->get();
        $totalUsers = $users->count();

        if ($totalUsers === 0) {
            Log::info('No active users to sync for organization', [
                'organization_id' => $organization->id,
            ]);
            return;
        }

        // Process in chunks to respect API rate limits
        $chunkSize = config('campaign-monitor.bulk_import_batch_size', 1000);
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        $users->chunk($chunkSize, function ($chunk) use ($cmSyncService, &$processed, &$succeeded, &$failed) {
            // Use bulk import mode to leverage CM's bulk API if available
            foreach ($chunk as $user) {
                try {
                    $result = $cmSyncService->syncUser($user, $this->fieldsToSync);

                    if ($result) {
                        $succeeded++;
                    } else {
                        $failed++;
                    }

                    $processed++;

                    // Small delay to respect rate limits
                    if ($processed % 100 === 0) {
                        usleep(100000); // 0.1 second pause every 100 users
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $processed++;

                    Log::error('Failed to sync user in bulk operation', [
                        'user_id' => $user->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Completed bulk sync for organization users', [
            'organization_id' => $this->organizationId,
            'total_users' => $totalUsers,
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'reason' => $this->reason,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BulkSyncOrganizationUsersJob failed', [
            'organization_id' => $this->organizationId,
            'reason' => $this->reason,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
