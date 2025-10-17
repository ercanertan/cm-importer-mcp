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
        // Find all users with emails matching this domain
        $users = \App\Models\User::where('email', 'like', '%@' . $this->domain)
            ->get();

        $assignedCount = 0;
        $updatedOrganizations = [];

        // Get all organizations associated with this domain
        $domainOrganizations = $this->organizations()->pluck('organizations.id')->toArray();

        foreach ($users as $user) {
            $updated = false;

            // Update user's domain_id if not set or different
            if ($user->domain_id !== $this->id) {
                $user->domain_id = $this->id;
                $user->save();
                $updated = true;
            }

            // Assign user to ALL organizations associated with this domain
            if (!empty($domainOrganizations)) {
                // Get current user's organization IDs
                $currentOrgIds = $user->organizations()->pluck('organizations.id')->toArray();

                // Find organizations to add (domain orgs that user doesn't have)
                $orgsToAdd = array_diff($domainOrganizations, $currentOrgIds);

                if (!empty($orgsToAdd)) {
                    // Attach new organizations to user
                    $user->organizations()->attach($orgsToAdd);
                    $updated = true;

                    $updatedOrganizations = array_unique(array_merge($updatedOrganizations, $orgsToAdd));
                }

                // Update legacy organization_id field to first organization if not set
                if (!$user->organization_id && !empty($domainOrganizations)) {
                    $user->organization_id = $domainOrganizations[0];
                    $user->save();
                }
            }

            if ($updated) {
                $assignedCount++;
            }
        }

        return [
            'assigned_count' => $assignedCount,
            'total_users' => $users->count(),
            'organizations' => $updatedOrganizations
        ];
    }

    /**
     * Assign users to a specific organization for this domain (many-to-many)
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

        // Find all users with this domain
        $users = \App\Models\User::where('email', 'like', '%@' . $this->domain)
            ->get();

        $assignedCount = 0;

        foreach ($users as $user) {
            $updated = false;

            // Update domain_id
            if ($user->domain_id !== $this->id) {
                $user->domain_id = $this->id;
                $user->save();
                $updated = true;
            }

            // Add organization to user's organizations if not already present
            $userOrgIds = $user->organizations()->pluck('organizations.id')->toArray();
            if (!in_array($organization->id, $userOrgIds)) {
                $user->organizations()->attach($organization->id);
                $updated = true;
            }

            // Update legacy organization_id field to this organization
            if ($user->organization_id !== $organization->id) {
                $user->organization_id = $organization->id;
                $user->save();
            }

            if ($updated) {
                $assignedCount++;
            }
        }

        return $assignedCount;
    }
}
