<?php

namespace App\Jobs;

use App\Models\CmCustomField;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncOrganizationConditionalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncLogId,
        public int $organizationId,
        public array $domainList,
        public array $conditions,
        public string $conditionLogic = 'AND'
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = SyncLog::findOrFail($this->syncLogId);
        $organization = Organization::findOrFail($this->organizationId);

        try {
            $syncLog->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Step 1: Associate domains with organization
            $domainIds = $this->associateDomains($organization);

            // Step 1.5: Remove auto-assigned users whose domains are no longer associated
            $this->removeUsersFromDetachedDomains($organization, $domainIds);

            // Step 2: Find users matching conditions
            $matchingUserIds = $this->findMatchingUsers($domainIds);

            $syncLog->update(['total_items' => count($matchingUserIds)]);

            // Step 3: Assign users to organization
            $this->assignUsers($organization, $matchingUserIds, $syncLog);

            // Step 4: Remove users who no longer meet conditions (auto-assigned only)
            $removedCount = $this->removeDisqualifiedUsers($organization, $domainIds);

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => [
                    'users_added' => $syncLog->successful_items,
                    'users_removed' => $removedCount,
                ],
            ]);

            Log::info('Conditional organization sync completed', [
                'sync_log_id' => $this->syncLogId,
                'organization_id' => $this->organizationId,
                'total_users_assigned' => $syncLog->successful_items,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Conditional organization sync failed', [
                'sync_log_id' => $this->syncLogId,
                'organization_id' => $this->organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Associate domains with organization
     * Uses sync() to ensure ONLY the specified domains are associated
     */
    protected function associateDomains(Organization $organization): array
    {
        $domainIds = [];

        foreach ($this->domainList as $domainName) {
            $domain = Domain::firstOrCreate(
                ['domain' => strtolower(trim($domainName))]
            );

            $domainIds[] = $domain->id;
        }

        // Sync domains - this will:
        // 1. Attach domains that aren't already attached
        // 2. Detach domains that were attached but are no longer in the list
        // 3. Keep domains that are already attached and still in the list
        $organization->domains()->sync($domainIds);

        Log::info('Organization domains synced in conditional job', [
            'sync_log_id' => $this->syncLogId,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'domain_ids' => $domainIds,
            'domain_count' => count($domainIds)
        ]);

        return $domainIds;
    }

    /**
     * Remove auto-assigned users whose domains are no longer associated with this organization
     */
    protected function removeUsersFromDetachedDomains(Organization $organization, array $currentDomainIds): int
    {
        // Find users auto-assigned to this organization whose domains are NOT in the current list
        $usersToRemove = DB::table('organization_user')
            ->join('users', 'organization_user.user_id', '=', 'users.id')
            ->where('organization_user.organization_id', $organization->id)
            ->where('organization_user.is_manual', false) // Only auto-assigned users
            ->whereNotNull('users.domain_id')
            ->whereNotIn('users.domain_id', $currentDomainIds)
            ->pluck('organization_user.user_id')
            ->toArray();

        if (empty($usersToRemove)) {
            return 0;
        }

        // Remove these users from the organization
        DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->whereIn('user_id', $usersToRemove)
            ->where('is_manual', false)
            ->delete();

        Log::info('Removed auto-assigned users from conditional organization after domain detachment', [
            'sync_log_id' => $this->syncLogId,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'users_removed_count' => count($usersToRemove),
            'current_domain_ids' => $currentDomainIds
        ]);

        return count($usersToRemove);
    }

    /**
     * Find users matching the conditional rules
     */
    protected function findMatchingUsers(array $domainIds): array
    {
        // Start with users from specified domains
        $query = User::whereIn('domain_id', $domainIds);

        if ($this->conditionLogic === 'AND') {
            // ALL conditions must match
            $query->where(function ($q) {
                foreach ($this->conditions as $condition) {
                    $q->where(function ($subQuery) use ($condition) {
                        $this->applyCondition($subQuery, $condition);
                    });
                }
            });
        } else {
            // ANY condition can match (OR)
            $query->where(function ($q) {
                foreach ($this->conditions as $index => $condition) {
                    if ($index === 0) {
                        $q->where(function ($subQuery) use ($condition) {
                            $this->applyCondition($subQuery, $condition);
                        });
                    } else {
                        $q->orWhere(function ($subQuery) use ($condition) {
                            $this->applyCondition($subQuery, $condition);
                        });
                    }
                }
            });
        }

        return $query->pluck('id')->toArray();
    }

    /**
     * Apply a single condition to the query
     */
    protected function applyCondition($query, array $condition): void
    {
        $fieldId = $condition['field_id'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? '';

        // Join with custom field values table
        $query->whereHas('customFieldValues', function ($q) use ($fieldId, $operator, $value) {
            $q->where('cm_custom_field_id', $fieldId);

            switch ($operator) {
                case 'equals':
                    $q->where('value', '=', $value);
                    break;
                case 'not_equals':
                    $q->where('value', '!=', $value);
                    break;
                case 'contains':
                    $q->where('value', 'like', '%' . $value . '%');
                    break;
                case 'not_contains':
                    $q->where('value', 'not like', '%' . $value . '%');
                    break;
                case 'starts_with':
                    $q->where('value', 'like', $value . '%');
                    break;
                case 'ends_with':
                    $q->where('value', 'like', '%' . $value);
                    break;
                case 'is_empty':
                    $q->where(function ($subQ) {
                        $subQ->whereNull('value')
                            ->orWhere('value', '=', '');
                    });
                    break;
                case 'is_not_empty':
                    $q->whereNotNull('value')
                        ->where('value', '!=', '');
                    break;
            }
        });
    }

    /**
     * Assign users to organization
     */
    protected function assignUsers(Organization $organization, array $userIds, SyncLog $syncLog): void
    {
        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($userIds as $userId) {
            try {
                // Check if already assigned
                $exists = DB::table('organization_user')
                    ->where('organization_id', $organization->id)
                    ->where('user_id', $userId)
                    ->exists();

                if (!$exists) {
                    // Assign with is_manual = false (conditional assignment)
                    $organization->usersMany()->attach($userId, ['is_manual' => false]);
                    $successful++;
                } else {
                    $successful++; // Already assigned, count as success
                }

                $processed++;

                // Update progress every 100 users
                if ($processed % 100 === 0) {
                    $syncLog->update([
                        'processed_items' => $processed,
                        'successful_items' => $successful,
                        'failed_items' => $failed,
                    ]);
                }
            } catch (\Exception $e) {
                $failed++;
                Log::warning('Failed to assign user to conditional organization', [
                    'user_id' => $userId,
                    'organization_id' => $organization->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final update
        $syncLog->update([
            'processed_items' => $processed,
            'successful_items' => $successful,
            'failed_items' => $failed,
        ]);
    }

    /**
     * Remove users who no longer meet the conditional rules
     * CRITICAL: Only removes AUTO-ASSIGNED users (is_manual = false)
     * NEVER touches manual assignments (is_manual = true)
     */
    protected function removeDisqualifiedUsers(Organization $organization, array $domainIds): int
    {
        // Get all AUTO-ASSIGNED users in this organization from the specified domains
        $autoAssignedUsers = DB::table('organization_user')
            ->join('users', 'users.id', '=', 'organization_user.user_id')
            ->where('organization_user.organization_id', $organization->id)
            ->where('organization_user.is_manual', false) // CRITICAL: Only auto-assigned
            ->whereIn('users.domain_id', $domainIds)
            ->select('users.id as user_id')
            ->pluck('user_id')
            ->toArray();

        if (empty($autoAssignedUsers)) {
            return 0;
        }

        // Load users with their custom field values
        $users = User::with('customFieldValues')
            ->whereIn('id', $autoAssignedUsers)
            ->get();

        $usersToRemove = [];

        foreach ($users as $user) {
            // Check if user still meets conditions
            $meetsConditions = $this->userMeetsConditions($user);

            if (!$meetsConditions) {
                $usersToRemove[] = $user->id;

                Log::info('Removing user from conditional organization (no longer qualifies)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'is_manual' => false,
                ]);
            }
        }

        if (empty($usersToRemove)) {
            return 0;
        }

        // Remove disqualified users - CRITICAL: Only if is_manual = false
        DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->whereIn('user_id', $usersToRemove)
            ->where('is_manual', false) // CRITICAL SAFETY CHECK
            ->delete();

        // Assign removed users to Default Organization
        $defaultOrg = Organization::firstOrCreate(
            ['name' => 'Default Organization'],
            ['is_active' => true]
        );

        $now = now()->format('Y-m-d H:i:s');
        foreach ($usersToRemove as $userId) {
            $existsInDefault = DB::table('organization_user')
                ->where('user_id', $userId)
                ->where('organization_id', $defaultOrg->id)
                ->exists();

            if (!$existsInDefault) {
                DB::table('organization_user')->insert([
                    'user_id' => $userId,
                    'organization_id' => $defaultOrg->id,
                    'is_manual' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                Log::info('User reassigned to Default Organization', [
                    'user_id' => $userId,
                    'from_organization_id' => $organization->id,
                ]);
            }
        }

        return count($usersToRemove);
    }

    /**
     * Check if user meets the conditional rules
     */
    protected function userMeetsConditions(User $user): bool
    {
        $userCustomFields = $user->customFieldValues()
            ->pluck('value', 'cm_custom_field_id')
            ->toArray();

        if ($this->conditionLogic === 'AND') {
            foreach ($this->conditions as $condition) {
                if (!$this->evaluateCondition($userCustomFields, $condition)) {
                    return false;
                }
            }
            return true;
        } else {
            foreach ($this->conditions as $condition) {
                if ($this->evaluateCondition($userCustomFields, $condition)) {
                    return true;
                }
            }
            return false;
        }
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $userCustomFields, array $condition): bool
    {
        $fieldId = $condition['field_id'] ?? null;
        $operator = $condition['operator'] ?? 'equals';
        $expectedValue = $condition['value'] ?? '';

        if (!$fieldId) {
            return false;
        }

        $actualValue = $userCustomFields[$fieldId] ?? null;

        switch ($operator) {
            case 'equals':
                return $actualValue === $expectedValue;
            case 'not_equals':
                return $actualValue !== $expectedValue;
            case 'contains':
                return $actualValue !== null && str_contains(strtolower($actualValue), strtolower($expectedValue));
            case 'not_contains':
                return $actualValue === null || !str_contains(strtolower($actualValue), strtolower($expectedValue));
            case 'starts_with':
                return $actualValue !== null && str_starts_with(strtolower($actualValue), strtolower($expectedValue));
            case 'ends_with':
                return $actualValue !== null && str_ends_with(strtolower($actualValue), strtolower($expectedValue));
            case 'is_empty':
                return empty($actualValue);
            case 'is_not_empty':
                return !empty($actualValue);
            default:
                return false;
        }
    }
}
