<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'tier_id',
        'description',
        'is_active',
        'max_users',
        'max_products',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_users' => 'integer',
            'max_products' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }

    /**
     * Get the tier that the organization belongs to.
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    /**
     * Get all users in the organization.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
                    ->withPivot(['role', 'is_active', 'joined_at', 'left_at'])
                    ->withTimestamps();
    }

    /**
     * Get active users in the organization.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    /**
     * Get organization admins.
     */
    public function admins(): BelongsToMany
    {
        return $this->users()
                    ->wherePivot('role', 'admin')
                    ->wherePivot('is_active', true);
    }

    /**
     * Get organization members (non-admins).
     */
    public function members(): BelongsToMany
    {
        return $this->users()
                    ->wherePivot('role', 'member')
                    ->wherePivot('is_active', true);
    }

    /**
     * Get the primary users for this organization.
     */
    public function primaryUsers(): HasMany
    {
        return $this->hasMany(User::class, 'organization_id');
    }

    /**
     * Scope to filter active organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by tier.
     */
    public function scopeByTier($query, $tierId)
    {
        return $query->where('tier_id', $tierId);
    }

    /**
     * Check if organization has reached user limit.
     */
    public function hasReachedUserLimit(): bool
    {
        if ($this->max_users === null || $this->max_users === -1) {
            return false; // Unlimited
        }

        return $this->activeUsers()->count() >= $this->max_users;
    }

    /**
     * Check if organization has reached product limit.
     */
    public function hasReachedProductLimit(): bool
    {
        if ($this->max_products === null || $this->max_products === -1) {
            return false; // Unlimited
        }

        // This will be implemented when we add organization-product relationships
        return false;
    }

    /**
     * Add a user to the organization.
     */
    public function addUser(User $user, string $role = 'member'): void
    {
        if ($this->hasReachedUserLimit()) {
            throw new \Exception('Organization has reached maximum user limit');
        }

        $this->users()->attach($user->id, [
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a user from the organization.
     */
    public function removeUser(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at' => now(),
        ]);
    }

    /**
     * Check if user is a member of this organization.
     */
    public function hasMember(User $user): bool
    {
        return $this->activeUsers()->where('users.id', $user->id)->exists();
    }

    /**
     * Check if user is an admin of this organization.
     */
    public function hasAdmin(User $user): bool
    {
        return $this->admins()->where('users.id', $user->id)->exists();
    }

    /**
     * Get the organization's active users count.
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->activeUsers()->count();
    }

    /**
     * Get the organization's admin users count.
     */
    public function getAdminsCountAttribute(): int
    {
        return $this->admins()->count();
    }
}
