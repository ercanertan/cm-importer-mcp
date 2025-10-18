<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\SyncLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncSingleDomainJob implements ShouldQueue
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
     * The domain ID
     *
     * @var int
     */
    protected $domainId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $syncLogId, int $domainId)
    {
        $this->syncLogId = $syncLogId;
        $this->domainId = $domainId;
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
            Log::info('Starting sync single domain job', [
                'sync_log_id' => $syncLog->id,
                'domain_id' => $this->domainId
            ]);

            $domain = Domain::find($this->domainId);

            if (!$domain) {
                throw new \Exception("Domain with ID {$this->domainId} not found");
            }

            // Sync domain users
            $result = $domain->assignUsersFromDomain();

            Log::info('Domain synced successfully', [
                'sync_log_id' => $syncLog->id,
                'domain' => $domain->domain,
                'assigned_count' => $result['assigned_count'],
                'total_users' => $result['total_users']
            ]);

            // Mark as completed
            $syncLog->update([
                'total_items' => $result['total_users'],
                'processed_items' => $result['total_users'],
                'successful_items' => $result['assigned_count'],
                'failed_items' => 0,
                'metadata' => [
                    'domain_id' => $this->domainId,
                    'domain_name' => $domain->domain,
                    'assigned_count' => $result['assigned_count'],
                    'total_users' => $result['total_users'],
                ],
            ]);
            $syncLog->markAsCompleted();

            Log::info('Completed sync single domain job', [
                'sync_log_id' => $syncLog->id,
                'domain' => $domain->domain,
                'assigned_count' => $result['assigned_count'],
                'total_users' => $result['total_users']
            ]);
        } catch (\Exception $e) {
            Log::error('Sync single domain job failed', [
                'sync_log_id' => $syncLog->id,
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $syncLog->markAsFailed($e->getMessage());
        }
    }
}
