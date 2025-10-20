<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\ConditionalRuleEvaluator;
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

            // Load organizations with conditional rules
            $organizations = Organization::whereIn('id', $this->organizationIds)
                ->select('id', 'name', 'conditional_rules', 'is_active')
                ->get()
                ->keyBy('id');

            // Get Default Organization for fallback
            $defaultOrg = Organization::firstOrCreate(
                ['name' => 'Default Organization'],
                ['is_active' => true]
            );

            // Get all users with this domain
            $users = User::with('customFieldValues')->where('email', 'like', '%@' . $domain->domain)->get();
            $totalUsers = $users->count();

            // Update total items
            $syncLog->update(['total_items' => $totalUsers]);

            $processed = 0;
            $successful = 0;
            $failed = 0;

            $conditionalEvaluator = new ConditionalRuleEvaluator();

            // Update each user's organizations
            foreach ($users as $user) {
                try {
                    // Get manually assigned organization IDs (preserve them)
                    $manualOrgIds = $user->organizations()
                        ->wherePivot('is_manual', true)
                        ->pluck('organizations.id')
                        ->toArray();

                    // Evaluate which domain-based organizations the user qualifies for
                    $qualifiedOrgIds = [];
                    $hasConditionalOrg = false;

                    foreach ($this->organizationIds as $orgId) {
                        $org = $organizations->get($orgId);

                        if (!$org) {
                            continue;
                        }

                        // Check if organization has conditional rules
                        if (!empty($org->conditional_rules) && !empty($org->conditional_rules['conditions'])) {
                            $hasConditionalOrg = true;

                            // Evaluate if user meets conditions
                            if ($conditionalEvaluator->userMeetsConditions($user, $org->conditional_rules)) {
                                $qualifiedOrgIds[] = $orgId;
                                Log::debug('User meets conditional organization rules', [
                                    'user_id' => $user->id,
                                    'organization_id' => $orgId,
                                    'organization_name' => $org->name
                                ]);
                            } else {
                                Log::debug('User does not meet conditional organization rules', [
                                    'user_id' => $user->id,
                                    'organization_id' => $orgId,
                                    'organization_name' => $org->name
                                ]);
                            }
                        } else {
                            // No conditional rules, user qualifies
                            $qualifiedOrgIds[] = $orgId;
                        }
                    }

                    // If user doesn't qualify for any domain organization, assign to Default
                    if (empty($qualifiedOrgIds) && $hasConditionalOrg) {
                        $qualifiedOrgIds[] = $defaultOrg->id;
                        Log::info('User assigned to Default Organization (no conditional match)', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'domain' => $domain->domain
                        ]);
                    }

                    // Merge manual assignments with qualified domain-based assignments
                    $allOrgIds = array_unique(array_merge($qualifiedOrgIds, $manualOrgIds));

                    // Prepare sync data: mark domain-based as is_manual=false
                    $syncData = [];
                    foreach ($allOrgIds as $orgId) {
                        $syncData[$orgId] = ['is_manual' => in_array($orgId, $manualOrgIds)];
                    }

                    // Sync organizations (preserves manual assignments)
                    $user->organizations()->sync($syncData);

                    // Update legacy organization_id to first qualified organization
                    $user->organization_id = $qualifiedOrgIds[0] ?? $defaultOrg->id;
                    $user->save();

                    $successful++;

                    Log::debug('User organizations synced', [
                        'sync_log_id' => $syncLog->id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'qualified_organization_ids' => $qualifiedOrgIds
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
