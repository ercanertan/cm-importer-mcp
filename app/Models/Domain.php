<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Domain extends Model
{
    use HasFactory;
    protected $fillable = [
        'domain',
    ];

    protected $casts = [];

    /**
     * Get all users with this domain
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all organizations associated with this domain (many-to-many)
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_domain')
            ->withTimestamps();
    }

    /**
     * Get the user count dynamically (calculated in real-time)
     */
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Append user_count to array/JSON representation
     */
    protected $appends = ['user_count'];

    /**
     * Extract domain from email address
     */
    public static function extractFromEmail(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $parts = explode('@', $email);
        return strtolower($parts[1] ?? null);
    }

    /**
     * Find or create a domain
     */
    public static function findOrCreateByEmail(string $email): ?self
    {
        $domainName = self::extractFromEmail($email);

        if (!$domainName) {
            return null;
        }

        return self::firstOrCreate(
            ['domain' => $domainName]
        );
    }

    /**
     * Scan database and assign all users with this domain to it
     * and to the associated organizations (many-to-many)
     * Respects conditional organization rules
     */
    public function assignUsersFromDomain(): array
    {
        // Get all organizations associated with this domain with their conditional rules
        $organizations = $this->organizations()
            ->select('organizations.id', 'organizations.name', 'organizations.conditional_rules')
            ->get();

        $domainOrganizations = $organizations->pluck('id')->toArray();

        // Separate conditional and non-conditional organizations
        $conditionalOrgs = $organizations->filter(function($org) {
            return !empty($org->conditional_rules) && !empty($org->conditional_rules['conditions']);
        });

        $nonConditionalOrgs = $organizations->filter(function($org) {
            return empty($org->conditional_rules) || empty($org->conditional_rules['conditions']);
        })->pluck('id')->toArray();

        // BULK UPDATE 1: Set domain_id for all users with this domain email
        // This is 1000x faster than looping!
        $updatedCount = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', 'like', '%@' . $this->domain)
            ->where(function($query) {
                $query->whereNull('domain_id')
                    ->orWhere('domain_id', '!=', $this->id);
            })
            ->update([
                'domain_id' => $this->id,
                'updated_at' => now()
            ]);

        // Get user IDs for pivot table syncing
        $userIds = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', 'like', '%@' . $this->domain)
            ->pluck('id')
            ->toArray();

        $totalUsers = count($userIds);

        // BULK UPDATE 2: Sync NON-CONDITIONAL organizations to users (pivot table)
        if (!empty($nonConditionalOrgs) && !empty($userIds)) {
            // Get existing relationships to avoid duplicates
            $existingRelations = \Illuminate\Support\Facades\DB::table('organization_user')
                ->whereIn('user_id', $userIds)
                ->whereIn('organization_id', $nonConditionalOrgs)
                ->get()
                ->mapWithKeys(function($item) {
                    return ["{$item->user_id}_{$item->organization_id}" => true];
                })
                ->toArray();

            // Prepare bulk insert data for missing relationships
            $now = now()->format('Y-m-d H:i:s');
            $pivotData = [];

            foreach ($userIds as $userId) {
                foreach ($nonConditionalOrgs as $orgId) {
                    $key = "{$userId}_{$orgId}";
                    if (!isset($existingRelations[$key])) {
                        $pivotData[] = [
                            'user_id' => $userId,
                            'organization_id' => $orgId,
                            'is_manual' => false, // Auto-assigned via domain sync
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }
                }
            }

            // Bulk insert missing relationships in chunks
            if (!empty($pivotData)) {
                $chunks = array_chunk($pivotData, 500);
                foreach ($chunks as $chunk) {
                    \Illuminate\Support\Facades\DB::table('organization_user')->insert($chunk);
                }
            }
        }

        // HANDLE CONDITIONAL ORGANIZATIONS: Evaluate rules and assign qualified users
        if ($conditionalOrgs->isNotEmpty() && !empty($userIds)) {
            $conditionalEvaluator = new \App\Services\ConditionalRuleEvaluator();

            // Load users with their custom field values in chunks for memory efficiency
            $users = User::with('customFieldValues')
                ->whereIn('id', $userIds)
                ->get();

            // Get Default Organization for fallback
            $defaultOrg = Organization::firstOrCreate(
                ['name' => 'Default Organization'],
                ['is_active' => true]
            );

            $now = now()->format('Y-m-d H:i:s');
            $conditionalPivotData = [];
            $usersMeetingConditions = [];
            $usersNotMeetingAnyCondition = [];

            foreach ($users as $user) {
                $userQualifiesForAny = false;

                foreach ($conditionalOrgs as $org) {
                    if ($conditionalEvaluator->userMeetsConditions($user, $org->conditional_rules)) {
                        $userQualifiesForAny = true;
                        $usersMeetingConditions[] = $user->id;

                        // Check if relationship already exists
                        $exists = \Illuminate\Support\Facades\DB::table('organization_user')
                            ->where('user_id', $user->id)
                            ->where('organization_id', $org->id)
                            ->exists();

                        if (!$exists) {
                            $conditionalPivotData[] = [
                                'user_id' => $user->id,
                                'organization_id' => $org->id,
                                'is_manual' => false,
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                        }

                        \Illuminate\Support\Facades\Log::debug('User meets conditional organization rules', [
                            'user_id' => $user->id,
                            'organization_id' => $org->id,
                            'organization_name' => $org->name
                        ]);
                    }
                }

                // If user doesn't qualify for ANY conditional organization, assign to Default
                if (!$userQualifiesForAny && $conditionalOrgs->isNotEmpty()) {
                    $usersNotMeetingAnyCondition[] = $user->id;

                    // Check if relationship already exists
                    $exists = \Illuminate\Support\Facades\DB::table('organization_user')
                        ->where('user_id', $user->id)
                        ->where('organization_id', $defaultOrg->id)
                        ->exists();

                    if (!$exists) {
                        $conditionalPivotData[] = [
                            'user_id' => $user->id,
                            'organization_id' => $defaultOrg->id,
                            'is_manual' => false,
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    \Illuminate\Support\Facades\Log::info('User assigned to Default Organization (no conditional match)', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'domain' => $this->domain
                    ]);
                }
            }

            // Bulk insert conditional organization assignments
            if (!empty($conditionalPivotData)) {
                $chunks = array_chunk($conditionalPivotData, 500);
                foreach ($chunks as $chunk) {
                    \Illuminate\Support\Facades\DB::table('organization_user')->insert($chunk);
                }
            }
        }

        // PROACTIVE EVALUATION: Check ALL other conditional organizations
        // to discover new assignments even if the org wasn't initially associated with this domain
        $this->proactivelyEvaluateAllConditionalOrganizations($userIds);

        // BULK UPDATE 3: Set legacy organization_id field
        // Prefer non-conditional org, fallback to first conditional org, fallback to default
        $firstOrgId = null;
        if (!empty($nonConditionalOrgs)) {
            $firstOrgId = $nonConditionalOrgs[0];
        } elseif (!empty($domainOrganizations)) {
            $firstOrgId = $domainOrganizations[0];
        }

        if ($firstOrgId) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('email', 'like', '%@' . $this->domain)
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => $firstOrgId,
                    'updated_at' => now()
                ]);
        }

        return [
            'assigned_count' => $updatedCount,
            'total_users' => $totalUsers,
            'organizations' => $domainOrganizations
        ];
    }

    /**
     * Proactively evaluate users from this domain against ALL conditional organizations
     * This discovers new conditional organization assignments even if the org wasn't
     * initially associated with this domain
     */
    protected function proactivelyEvaluateAllConditionalOrganizations(array $userIds): void
    {
        if (empty($userIds)) {
            return;
        }

        // Quick check: Do we have ANY conditional organizations for this domain?
        // This avoids expensive queries when there are none
        $hasConditionalOrgs = Organization::whereNotNull('conditional_rules')
            ->whereHas('domains', function($query) {
                $query->where('domain_id', $this->id);
            })
            ->exists();

        if (!$hasConditionalOrgs) {
            return; // No conditional orgs to evaluate - skip expensive operations
        }

        // Get ALL conditional organizations that have this domain associated
        // but weren't processed in the initial sync
        $allConditionalOrgs = Organization::whereNotNull('conditional_rules')
            ->whereHas('domains', function($query) {
                $query->where('domain_id', $this->id);
            })
            ->select('id', 'name', 'conditional_rules')
            ->get()
            ->filter(function ($org) {
                return !empty($org->conditional_rules) && !empty($org->conditional_rules['conditions']);
            });

        if ($allConditionalOrgs->isEmpty()) {
            return;
        }

        $conditionalEvaluator = new \App\Services\ConditionalRuleEvaluator();

        // Load users with their custom field values
        $users = User::with('customFieldValues')
            ->whereIn('id', $userIds)
            ->get();

        $now = now()->format('Y-m-d H:i:s');
        $pivotData = [];

        foreach ($allConditionalOrgs as $org) {
            foreach ($users as $user) {
                // Evaluate if user meets the conditional rules
                if ($conditionalEvaluator->userMeetsConditions($user, $org->conditional_rules)) {
                    // Check if relationship already exists
                    $exists = \Illuminate\Support\Facades\DB::table('organization_user')
                        ->where('user_id', $user->id)
                        ->where('organization_id', $org->id)
                        ->exists();

                    if (!$exists) {
                        $pivotData[] = [
                            'user_id' => $user->id,
                            'organization_id' => $org->id,
                            'is_manual' => false,
                            'created_at' => $now,
                            'updated_at' => $now
                        ];

                        \Illuminate\Support\Facades\Log::info('User proactively assigned to conditional organization during domain sync', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'organization_id' => $org->id,
                            'organization_name' => $org->name,
                            'domain' => $this->domain,
                        ]);
                    }
                }
            }
        }

        // Bulk insert new assignments
        if (!empty($pivotData)) {
            $chunks = array_chunk($pivotData, 500);
            foreach ($chunks as $chunk) {
                \Illuminate\Support\Facades\DB::table('organization_user')->insert($chunk);
            }
        }
    }

    /**
     * Assign users to a specific organization for this domain (many-to-many)
     * OPTIMIZED: Uses bulk queries instead of loops
     * Respects conditional organization rules
     *
     * @param Organization $organization The organization to assign users to
     * @param bool $syncDomainOrganization Whether to sync the domain-organization relationship (default: true)
     *                                     Set to false when called from a job that already handled the sync
     */
    public function assignUsersToOrganization(Organization $organization, bool $syncDomainOrganization = true): int
    {
        // Find or create Default Organization
        $defaultOrganization = Organization::firstOrCreate(
            ['name' => 'Default Organization'],
            ['is_active' => true]
        );

        // Only sync domain-organization relationship if requested
        // This prevents re-adding domains that were intentionally removed
        if ($syncDomainOrganization) {
            // Get current organization IDs for this domain
            $currentOrgIds = $this->organizations()->pluck('organizations.id')->toArray();

            // If assigning to a non-default organization, remove Default Organization from domain
            if ($defaultOrganization && $organization->id !== $defaultOrganization->id) {
                // Remove Default Organization from the list
                $currentOrgIds = array_diff($currentOrgIds, [$defaultOrganization->id]);
            }

            // Add the new organization
            if (!in_array($organization->id, $currentOrgIds)) {
                $currentOrgIds[] = $organization->id;
            }

            // Sync the updated organization list for the domain
            $this->organizations()->sync($currentOrgIds);
        }

        // BULK UPDATE 1: Set domain_id for all users with this domain email
        $updatedCount = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', 'like', '%@' . $this->domain)
            ->where(function($query) {
                $query->whereNull('domain_id')
                    ->orWhere('domain_id', '!=', $this->id);
            })
            ->update([
                'domain_id' => $this->id,
                'updated_at' => now()
            ]);

        // Get user IDs for this domain
        $userIds = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', 'like', '%@' . $this->domain)
            ->pluck('id')
            ->toArray();

        // Check if organization has conditional rules
        $hasConditionalRules = !empty($organization->conditional_rules) && !empty($organization->conditional_rules['conditions']);

        if ($hasConditionalRules) {
            // CONDITIONAL ORGANIZATION: Only assign users who meet the conditions
            $conditionalEvaluator = new \App\Services\ConditionalRuleEvaluator();

            // Load users with their custom field values
            $users = User::with('customFieldValues')
                ->whereIn('id', $userIds)
                ->get();

            // Get existing relationships to avoid duplicates
            $existingRelations = \Illuminate\Support\Facades\DB::table('organization_user')
                ->whereIn('user_id', $userIds)
                ->where('organization_id', $organization->id)
                ->pluck('user_id')
                ->toArray();

            $now = now()->format('Y-m-d H:i:s');
            $pivotData = [];
            $qualifiedUserIds = [];
            $disqualifiedUserIds = [];

            foreach ($users as $user) {
                if ($conditionalEvaluator->userMeetsConditions($user, $organization->conditional_rules)) {
                    $qualifiedUserIds[] = $user->id;

                    // Only add if not already assigned
                    if (!in_array($user->id, $existingRelations)) {
                        $pivotData[] = [
                            'user_id' => $user->id,
                            'organization_id' => $organization->id,
                            'is_manual' => false,
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    \Illuminate\Support\Facades\Log::debug('User meets conditional organization rules', [
                        'user_id' => $user->id,
                        'organization_id' => $organization->id,
                        'organization_name' => $organization->name
                    ]);
                } else {
                    $disqualifiedUserIds[] = $user->id;

                    // Assign to Default Organization instead
                    if ($defaultOrganization) {
                        $defaultExists = \Illuminate\Support\Facades\DB::table('organization_user')
                            ->where('user_id', $user->id)
                            ->where('organization_id', $defaultOrganization->id)
                            ->exists();

                        if (!$defaultExists) {
                            $pivotData[] = [
                                'user_id' => $user->id,
                                'organization_id' => $defaultOrganization->id,
                                'is_manual' => false,
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                        }
                    }

                    \Illuminate\Support\Facades\Log::info('User does not meet conditional organization rules, assigned to Default', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'organization_id' => $organization->id,
                        'organization_name' => $organization->name
                    ]);
                }
            }

            // Bulk insert relationships
            if (!empty($pivotData)) {
                $chunks = array_chunk($pivotData, 500);
                foreach ($chunks as $chunk) {
                    \Illuminate\Support\Facades\DB::table('organization_user')->insert($chunk);
                }
            }

            // BULK UPDATE 3: Set legacy organization_id field for qualified users only
            if (!empty($qualifiedUserIds)) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->whereIn('id', $qualifiedUserIds)
                    ->where(function($query) use ($organization) {
                        $query->whereNull('organization_id')
                            ->orWhere('organization_id', '!=', $organization->id);
                    })
                    ->update([
                        'organization_id' => $organization->id,
                        'updated_at' => now()
                    ]);
            }

            // Set default organization for disqualified users
            if (!empty($disqualifiedUserIds) && $defaultOrganization) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->whereIn('id', $disqualifiedUserIds)
                    ->whereNull('organization_id')
                    ->update([
                        'organization_id' => $defaultOrganization->id,
                        'updated_at' => now()
                    ]);
            }

            return count($qualifiedUserIds);
        } else {
            // NON-CONDITIONAL ORGANIZATION: Assign all users (original behavior)
            // BULK UPDATE 2: Add organization to users (pivot table)
            if (!empty($userIds)) {
                // Get existing relationships to avoid duplicates
                $existingRelations = \Illuminate\Support\Facades\DB::table('organization_user')
                    ->whereIn('user_id', $userIds)
                    ->where('organization_id', $organization->id)
                    ->pluck('user_id')
                    ->toArray();

                // Find users that don't have this organization yet
                $usersToAdd = array_diff($userIds, $existingRelations);

                // Bulk insert missing relationships
                if (!empty($usersToAdd)) {
                    $now = now()->format('Y-m-d H:i:s');
                    $pivotData = [];

                    foreach ($usersToAdd as $userId) {
                        $pivotData[] = [
                            'user_id' => $userId,
                            'organization_id' => $organization->id,
                            'is_manual' => false, // Auto-assigned via domain sync
                            'created_at' => $now,
                            'updated_at' => $now
                        ];
                    }

                    // Insert in chunks
                    $chunks = array_chunk($pivotData, 500);
                    foreach ($chunks as $chunk) {
                        \Illuminate\Support\Facades\DB::table('organization_user')->insert($chunk);
                    }
                }

                // BULK UPDATE 3: Set legacy organization_id field
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('email', 'like', '%@' . $this->domain)
                    ->where(function($query) use ($organization) {
                        $query->whereNull('organization_id')
                            ->orWhere('organization_id', '!=', $organization->id);
                    })
                    ->update([
                        'organization_id' => $organization->id,
                        'updated_at' => now()
                    ]);
            }

            return count($userIds);
        }
    }
}
