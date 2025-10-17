<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncAllDomainsJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(int $syncLogId)
    {
        $this->syncLogId = $syncLogId;
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
            Log::info('Starting sync all domains job', ['sync_log_id' => $syncLog->id]);

            $domains = Domain::all();
            $totalDomains = $domains->count();

            // Update total items
            $syncLog->update(['total_items' => $totalDomains]);

            $processed = 0;
            $successful = 0;
            $failed = 0;
            $totalAssigned = 0;

            foreach ($domains as $domain) {
                try {
                    $result = $domain->assignUsersFromDomain();
                    $totalAssigned += $result['assigned_count'];
                    $successful++;

                    Log::debug('Domain synced', [
                        'sync_log_id' => $syncLog->id,
                        'domain' => $domain->domain,
                        'assigned_count' => $result['assigned_count'],
                        'total_users' => $result['total_users']
                    ]);
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to sync domain', [
                        'sync_log_id' => $syncLog->id,
                        'domain' => $domain->domain,
                        'error' => $e->getMessage()
                    ]);
                }

                $processed++;

                // Update progress every 10 domains or on the last domain
                if ($processed % 10 === 0 || $processed === $totalDomains) {
                    $syncLog->updateProgress($processed, $successful, $failed);
                }
            }

            // Mark as completed
            $syncLog->update([
                'metadata' => [
                    'total_users_assigned' => $totalAssigned,
                ],
            ]);
            $syncLog->markAsCompleted();

            Log::info('Completed sync all domains job', [
                'sync_log_id' => $syncLog->id,
                'total_domains' => $totalDomains,
                'successful' => $successful,
                'failed' => $failed,
                'total_assigned' => $totalAssigned
            ]);
        } catch (\Exception $e) {
            Log::error('Sync all domains job failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $syncLog->markAsFailed($e->getMessage());
        }
    }
}
