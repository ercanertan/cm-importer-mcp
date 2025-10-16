<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Domain extends Model
{
    protected $fillable = [
        'domain',
        'user_count',
    ];

    protected $casts = [
        'user_count' => 'integer',
    ];

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
            ['domain' => $domainName],
            ['user_count' => 0]
        );
    }

    /**
     * Increment user count
     */
    public function incrementUserCount(): void
    {
        $this->increment('user_count');
    }

    /**
     * Decrement user count
     */
    public function decrementUserCount(): void
    {
        $this->decrement('user_count');
    }

    /**
     * Scan database and assign all users with this domain to it
     * and to the associated organizations
     */
    public function assignUsersFromDomain(): array
    {
        // Find all users with emails matching this domain
        $users = \App\Models\User::where('email', 'like', '%@' . $this->domain)
            ->get();

        $assignedCount = 0;
        $updatedOrganizations = [];

        foreach ($users as $user) {
            $updated = false;

            // Update user's domain_id if not set or different
            if ($user->domain_id !== $this->id) {
                $user->domain_id = $this->id;
                $updated = true;
            }

            // Get the first organization associated with this domain (if any)
            $organization = $this->organizations()->first();

            // If this domain has an associated organization and user doesn't have one
            if ($organization && (!$user->organization_id || $user->organization_id !== $organization->id)) {
                $user->organization_id = $organization->id;
                $updated = true;

                if (!in_array($organization->id, $updatedOrganizations)) {
                    $updatedOrganizations[] = $organization->id;
                }
            }

            if ($updated) {
                $user->save();
                $assignedCount++;
            }
        }

        // Update user count for this domain
        $this->update(['user_count' => $users->count()]);

        return [
            'assigned_count' => $assignedCount,
            'total_users' => $users->count(),
            'organizations' => $updatedOrganizations
        ];
    }

    /**
     * Assign users to a specific organization for this domain
     */
    public function assignUsersToOrganization(Organization $organization): int
    {
        // Ensure this domain is associated with the organization
        $this->organizations()->syncWithoutDetaching([$organization->id]);

        // Find all users with this domain
        $users = \App\Models\User::where('email', 'like', '%@' . $this->domain)
            ->get();

        $assignedCount = 0;

        foreach ($users as $user) {
            $updated = false;

            // Update domain_id
            if ($user->domain_id !== $this->id) {
                $user->domain_id = $this->id;
                $updated = true;
            }

            // Update organization_id
            if ($user->organization_id !== $organization->id) {
                $user->organization_id = $organization->id;
                $updated = true;
            }

            if ($updated) {
                $user->save();
                $assignedCount++;
            }
        }

        // Update user count
        $this->update(['user_count' => $users->count()]);

        return $assignedCount;
    }
}
