<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncDomainOrganizationsJob implements ShouldQueue
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
     * The organization IDs to sync
     *
     * @var array
     */
    protected $organizationIds;

    /**
     * Create a new job instance.
     */
    public function __construct(int $syncLogId, int $domainId, array $organizationIds)
    {
        $this->syncLogId = $syncLogId;
        $this->domainId = $domainId;
        $this->organizationIds = $organizationIds;
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
            Log::info('Starting sync domain organizations job', [
                'sync_log_id' => $syncLog->id,
                'domain_id' => $this->domainId,
                'organization_ids' => $this->organizationIds
            ]);

            $domain = Domain::find($this->domainId);

            if (!$domain) {
                throw new \Exception("Domain with ID {$this->domainId} not found");
            }

            // Sync organizations to domain
            $domain->organizations()->sync($this->organizationIds);

            Log::info('Domain organizations synced', [
                'sync_log_id' => $syncLog->id,
                'domain' => $domain->domain,
                'organization_ids' => $this->organizationIds
            ]);

            // Get all users with this domain
            $users = User::where('email', 'like', '%@' . $domain->domain)->get();
            $totalUsers = $users->count();

            // Update total items
            $syncLog->update(['total_items' => $totalUsers]);

            $processed = 0;
            $successful = 0;
            $failed = 0;

            // Update each user's organizations
            foreach ($users as $user) {
                try {
                    // Sync user to the same organizations as the domain
                    $user->organizations()->sync($this->organizationIds);

                    // Update legacy organization_id to first organization
                    $user->organization_id = $this->organizationIds[0] ?? null;
                    $user->save();

                    $successful++;

                    Log::debug('User organizations synced', [
                        'sync_log_id' => $syncLog->id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'organization_ids' => $this->organizationIds
                    ]);
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to sync user organizations', [
                        'sync_log_id' => $syncLog->id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }

                $processed++;

                // Update progress every 100 users or on the last user
                if ($processed % 100 === 0 || $processed === $totalUsers) {
                    $syncLog->updateProgress($processed, $successful, $failed);
                }
            }

            // Mark as completed
            $syncLog->update([
                'metadata' => [
                    'domain_id' => $this->domainId,
                    'domain_name' => $domain->domain,
                    'organization_ids' => $this->organizationIds,
                    'total_users_updated' => $successful,
                ],
            ]);
            $syncLog->markAsCompleted();

            Log::info('Completed sync domain organizations job', [
                'sync_log_id' => $syncLog->id,
                'domain' => $domain->domain,
                'total_users' => $totalUsers,
                'successful' => $successful,
                'failed' => $failed
            ]);
        } catch (\Exception $e) {
            Log::error('Sync domain organizations job failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $syncLog->markAsFailed($e->getMessage());
        }
    }
}
