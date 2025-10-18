<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Domain extends Model
{
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
     */
    public function assignUsersFromDomain(): array
    {
        // Get all organizations associated with this domain
        $domainOrganizations = $this->organizations()->pluck('organizations.id')->toArray();

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

        // BULK UPDATE 2: Sync organizations to users (pivot table)
        if (!empty($domainOrganizations) && !empty($userIds)) {
            // Get existing relationships to avoid duplicates
            $existingRelations = \Illuminate\Support\Facades\DB::table('organization_user')
                ->whereIn('user_id', $userIds)
                ->whereIn('organization_id', $domainOrganizations)
                ->get()
                ->mapWithKeys(function($item) {
                    return ["{$item->user_id}_{$item->organization_id}" => true];
                })
                ->toArray();

            // Prepare bulk insert data for missing relationships
            $now = now()->format('Y-m-d H:i:s');
            $pivotData = [];

            foreach ($userIds as $userId) {
                foreach ($domainOrganizations as $orgId) {
                    $key = "{$userId}_{$orgId}";
                    if (!isset($existingRelations[$key])) {
                        $pivotData[] = [
                            'user_id' => $userId,
                            'organization_id' => $orgId,
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

            // BULK UPDATE 3: Set legacy organization_id field
            \Illuminate\Support\Facades\DB::table('users')
                ->where('email', 'like', '%@' . $this->domain)
                ->whereNull('organization_id')
                ->update([
                    'organization_id' => $domainOrganizations[0] ?? null,
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
     * Assign users to a specific organization for this domain (many-to-many)
     * OPTIMIZED: Uses bulk queries instead of loops
     */
    public function assignUsersToOrganization(Organization $organization): int
    {
        // Find Default Organization
        $defaultOrganization = Organization::where('name', 'Default Organization')->first();

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
