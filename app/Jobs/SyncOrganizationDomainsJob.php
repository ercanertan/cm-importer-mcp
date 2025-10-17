<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncOrganizationDomainsJob implements ShouldQueue
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
     * The organization ID
     *
     * @var int
     */
    protected $organizationId;

    /**
     * Domain names to process
     *
     * @var array
     */
    protected $domainNames;

    /**
     * Whether to sync users from domains
     *
     * @var bool
     */
    protected $syncUsers;

    /**
     * Create a new job instance.
     */
    public function __construct(int $syncLogId, int $organizationId, array $domainNames, bool $syncUsers = true)
    {
        $this->syncLogId = $syncLogId;
        $this->organizationId = $organizationId;
        $this->domainNames = $domainNames;
        $this->syncUsers = $syncUsers;
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
            Log::info('Starting sync organization domains job', [
                'sync_log_id' => $syncLog->id,
                'organization_id' => $this->organizationId
            ]);

            $organization = Organization::find($this->organizationId);
            if (!$organization) {
                throw new \Exception('Organization not found');
            }

            $totalDomains = count($this->domainNames);
            $syncLog->update(['total_items' => $totalDomains]);

            $processed = 0;
            $successful = 0;
            $failed = 0;
            $totalUsersAssigned = 0;

            $defaultOrganization = Organization::where('name', 'Default Organization')->first();

            foreach ($this->domainNames as $domainName) {
                try {
                    $domainName = strtolower(trim($domainName));

                    // Find or create domain
                    $domain = \App\Models\Domain::firstOrCreate(['domain' => $domainName]);

                    // Remove Default Organization from domain if this is a specific organization
                    if ($defaultOrganization && $organization->id !== $defaultOrganization->id) {
                        $domainOrgIds = $domain->organizations()->pluck('organizations.id')->toArray();
                        $domainOrgIds = array_diff($domainOrgIds, [$defaultOrganization->id]);

                        // Add this organization
                        if (!in_array($organization->id, $domainOrgIds)) {
                            $domainOrgIds[] = $organization->id;
                        }

                        // Sync the updated organization list for this domain
                        $domain->organizations()->sync($domainOrgIds);

                        // Move users from Default Organization to this organization
                        $defaultUsers = \App\Models\User::where('email', 'like', '%@' . $domain->domain)
                            ->where('organization_id', $defaultOrganization->id)
                            ->get();

                        foreach ($defaultUsers as $user) {
                            $user->domain_id = $domain->id;
                            $user->organization_id = $organization->id;

                            // Also add to many-to-many if not already present
                            $userOrgIds = $user->organizations()->pluck('organizations.id')->toArray();
                            if (!in_array($organization->id, $userOrgIds)) {
                                $user->organizations()->attach($organization->id);
                            }

                            $user->save();
                            $totalUsersAssigned++;
                        }
                    } else {
                        // For Default Organization, just add without removing anything
                        $organization->domains()->syncWithoutDetaching([$domain->id]);
                    }

                    // Sync users from other organizations if enabled
                    if ($this->syncUsers && $domain->user_count > 0) {
                        $synced = $domain->assignUsersToOrganization($organization);
                        $totalUsersAssigned += $synced;
                    }

                    $successful++;

                    Log::debug('Domain synced to organization', [
                        'sync_log_id' => $syncLog->id,
                        'domain' => $domainName,
                        'organization' => $organization->name,
                        'users_assigned' => $totalUsersAssigned
                    ]);
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to sync domain to organization', [
                        'sync_log_id' => $syncLog->id,
                        'domain' => $domainName,
                        'organization_id' => $organization->id,
                        'error' => $e->getMessage()
                    ]);
                }

                $processed++;

                // Update progress every domain or on the last domain
                $syncLog->updateProgress($processed, $successful, $failed);
            }

            // Mark as completed
            $syncLog->update([
                'metadata' => [
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'total_users_assigned' => $totalUsersAssigned,
                    'sync_users_enabled' => $this->syncUsers,
                ],
            ]);
            $syncLog->markAsCompleted();

            Log::info('Completed sync organization domains job', [
                'sync_log_id' => $syncLog->id,
                'organization' => $organization->name,
                'total_domains' => $totalDomains,
                'successful' => $successful,
                'failed' => $failed,
                'total_users_assigned' => $totalUsersAssigned
            ]);
        } catch (\Exception $e) {
            Log::error('Sync organization domains job failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $syncLog->markAsFailed($e->getMessage());
        }
    }
}
