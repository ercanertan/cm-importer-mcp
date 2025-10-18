<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoveUsersToDefaultOrganizationJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 1 hour

    /**
     * The sync log ID
     *
     * @var int
     */
    protected $syncLogId;

    /**
     * The organization ID being deleted
     *
     * @var int
     */
    protected $organizationId;

    /**
     * The default organization ID
     *
     * @var int
     */
    protected $defaultOrganizationId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $syncLogId, int $organizationId, int $defaultOrganizationId)
    {
        $this->syncLogId = $syncLogId;
        $this->organizationId = $organizationId;
        $this->defaultOrganizationId = $defaultOrganizationId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = SyncLog::find($this->syncLogId);

        if (!$syncLog) {
            Log::error('Sync log not found', ['sync_log_id' => $this->syncLogId]);
            return;
        }

        try {
            // Mark as started
            $syncLog->markAsStarted();
            Log::info('Starting move users to default organization job', [
                'sync_log_id' => $syncLog->id,
                'organization_id' => $this->organizationId,
                'default_organization_id' => $this->defaultOrganizationId
            ]);

            // Count users to move
            $totalUsers = DB::table('users')
                ->where('organization_id', $this->organizationId)
                ->count();

            $syncLog->update(['total_items' => $totalUsers]);

            // BULK UPDATE: Move all users to default organization (FAST!)
            $movedCount = DB::table('users')
                ->where('organization_id', $this->organizationId)
                ->update([
                    'organization_id' => $this->defaultOrganizationId,
                    'updated_at' => now()
                ]);

            Log::info('Users moved to default organization', [
                'sync_log_id' => $syncLog->id,
                'moved_count' => $movedCount,
                'total_users' => $totalUsers
            ]);

            // Mark as completed
            $syncLog->update([
                'total_items' => $totalUsers,
                'processed_items' => $totalUsers,
                'successful_items' => $movedCount,
                'failed_items' => 0,
                'metadata' => [
                    'organization_id' => $this->organizationId,
                    'default_organization_id' => $this->defaultOrganizationId,
                    'moved_count' => $movedCount,
                ],
            ]);
            $syncLog->markAsCompleted();

            Log::info('Completed move users to default organization job', [
                'sync_log_id' => $syncLog->id,
                'moved_count' => $movedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Move users to default organization job failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $syncLog->markAsFailed($e->getMessage());
        }
    }
}
