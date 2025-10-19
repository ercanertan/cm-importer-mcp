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

            // Step 2: Find users matching conditions
            $matchingUserIds = $this->findMatchingUsers($domainIds);

            $syncLog->update(['total_items' => count($matchingUserIds)]);

            // Step 3: Assign users to organization
            $this->assignUsers($organization, $matchingUserIds, $syncLog);

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
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
     */
    protected function associateDomains(Organization $organization): array
    {
        $domainIds = [];

        foreach ($this->domainList as $domainName) {
            $domain = Domain::firstOrCreate(
                ['domain' => strtolower(trim($domainName))]
            );

            $domainIds[] = $domain->id;

            // Attach domain if not already attached
            if (!$organization->domains()->where('domain_id', $domain->id)->exists()) {
                $organization->domains()->attach($domain->id);
            }
        }

        return $domainIds;
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
}
