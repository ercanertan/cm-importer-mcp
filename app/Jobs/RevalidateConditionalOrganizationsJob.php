<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\ConditionalRuleEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevalidateConditionalOrganizationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $syncLogId,
        public ?int $organizationId = null, // null = all conditional orgs
        public bool $dryRun = false
    ) {
    }

    /**
     * Execute the job.
     * CRITICAL: Only processes AUTO-ASSIGNED users (is_manual = false)
     * NEVER touches manual assignments (is_manual = true)
     */
    public function handle(): void
    {
        $syncLog = SyncLog::findOrFail($this->syncLogId);

        try {
            $syncLog->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            // Get conditional organizations to revalidate
            $organizations = $this->getConditionalOrganizations();

            if ($organizations->isEmpty()) {
                Log::info('No conditional organizations found to revalidate');
                $syncLog->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'metadata' => ['message' => 'No conditional organizations found'],
                ]);
                return;
            }

            $syncLog->update(['total_items' => $organizations->count()]);

            $conditionalEvaluator = new ConditionalRuleEvaluator();
            $defaultOrg = Organization::firstOrCreate(
                ['name' => 'Default Organization'],
                ['is_active' => true]
            );

            $totalRemoved = 0;
            $totalPreserved = 0;
            $totalReassigned = 0;
            $processed = 0;

            foreach ($organizations as $organization) {
                try {
                    $result = $this->revalidateOrganization(
                        $organization,
                        $conditionalEvaluator,
                        $defaultOrg
                    );

                    $totalRemoved += $result['removed'];
                    $totalPreserved += $result['preserved'];
                    $totalReassigned += $result['reassigned'];
                    $processed++;

                    $syncLog->update([
                        'processed_items' => $processed,
                        'successful_items' => $processed,
                    ]);

                    Log::info('Revalidated conditional organization', [
                        'organization_id' => $organization->id,
                        'organization_name' => $organization->name,
                        'removed' => $result['removed'],
                        'preserved' => $result['preserved'],
                        'reassigned' => $result['reassigned'],
                        'dry_run' => $this->dryRun,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to revalidate organization', [
                        'organization_id' => $organization->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $syncLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => [
                    'total_organizations' => $organizations->count(),
                    'total_removed' => $totalRemoved,
                    'total_preserved' => $totalPreserved,
                    'total_reassigned' => $totalReassigned,
                    'dry_run' => $this->dryRun,
                ],
            ]);

            Log::info('Conditional organizations revalidation completed', [
                'sync_log_id' => $syncLog->id,
                'total_organizations' => $organizations->count(),
                'total_removed' => $totalRemoved,
                'total_preserved' => $totalPreserved,
                'total_reassigned' => $totalReassigned,
                'dry_run' => $this->dryRun,
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Conditional organizations revalidation failed', [
                'sync_log_id' => $syncLog->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get conditional organizations to revalidate
     */
    protected function getConditionalOrganizations()
    {
        $query = Organization::whereNotNull('conditional_rules')
            ->select('id', 'name', 'conditional_rules');

        if ($this->organizationId) {
            $query->where('id', $this->organizationId);
        }

        // Filter in PHP for database compatibility (SQLite doesn't support JSON_LENGTH)
        // Supports both old format (conditions) and new format (items)
        return $query->get()->filter(function ($org) {
            if (empty($org->conditional_rules)) {
                return false;
            }

            // New format with nested items
            if (isset($org->conditional_rules['items'])) {
                return !empty($org->conditional_rules['items']);
            }

            // Old format with conditions
            return !empty($org->conditional_rules['conditions']);
        });
    }

    /**
     * Revalidate a single organization
     * CRITICAL: Only processes is_manual = false
     */
    protected function revalidateOrganization(
        Organization $organization,
        ConditionalRuleEvaluator $evaluator,
        Organization $defaultOrg
    ): array {
        // Get ALL users in this organization with their manual flag
        // We need both auto and manual to count them properly
        $allUsers = DB::table('organization_user')
            ->where('organization_id', $organization->id)
            ->select('user_id', 'is_manual')
            ->get();

        $autoAssignedUserIds = $allUsers->where('is_manual', false)->pluck('user_id')->toArray();
        $manuallyAssignedUserIds = $allUsers->where('is_manual', true)->pluck('user_id')->toArray();

        $removed = 0;
        $preserved = count($manuallyAssignedUserIds); // Manual assignments are always preserved
        $reassigned = 0;

        if (empty($autoAssignedUserIds)) {
            Log::debug('No auto-assigned users to revalidate', [
                'organization_id' => $organization->id,
                'manually_assigned_count' => $preserved,
            ]);
            return ['removed' => 0, 'preserved' => $preserved, 'reassigned' => 0];
        }

        // Load auto-assigned users with their custom field values
        $users = User::with('customFieldValues')
            ->whereIn('id', $autoAssignedUserIds)
            ->get();

        $usersToRemove = [];

        foreach ($users as $user) {
            // Re-evaluate against current conditional rules
            $meetsConditions = $evaluator->userMeetsConditions($user, $organization->conditional_rules);

            if (!$meetsConditions) {
                // User no longer qualifies - mark for removal
                $usersToRemove[] = $user->id;

                Log::info('User no longer meets conditional organization rules', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'organization_id' => $organization->id,
                    'organization_name' => $organization->name,
                    'is_manual' => false, // We only process auto-assigned
                    'dry_run' => $this->dryRun,
                ]);
            }
        }

        // Remove disqualified users (if not dry run)
        if (!empty($usersToRemove) && !$this->dryRun) {
            // CRITICAL: Only remove if is_manual = false
            DB::table('organization_user')
                ->where('organization_id', $organization->id)
                ->whereIn('user_id', $usersToRemove)
                ->where('is_manual', false) // CRITICAL SAFETY CHECK
                ->delete();

            $removed = count($usersToRemove);

            // Assign removed users to Default Organization
            $now = now()->format('Y-m-d H:i:s');
            foreach ($usersToRemove as $userId) {
                // Check if already in Default Organization
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

                    $reassigned++;

                    Log::info('User reassigned to Default Organization', [
                        'user_id' => $userId,
                        'from_organization_id' => $organization->id,
                        'to_organization_id' => $defaultOrg->id,
                    ]);
                }
            }
        } elseif (!empty($usersToRemove) && $this->dryRun) {
            $removed = count($usersToRemove);
            Log::info('DRY RUN: Would remove users', [
                'organization_id' => $organization->id,
                'count' => $removed,
            ]);
        }

        return [
            'removed' => $removed,
            'preserved' => $preserved,
            'reassigned' => $reassigned,
        ];
    }
}
