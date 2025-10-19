<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'conditional_rules',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conditional_rules' => 'array',
    ];

    /**
     * Get all users belonging to this organization (legacy single organization)
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get all users belonging to this organization (many-to-many)
     */
    public function usersMany(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('is_manual')
            ->withTimestamps();
    }

    /**
     * Get all domains associated with this organization (many-to-many)
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'organization_domain')
            ->withTimestamps();
    }

    /**
     * Get the number of users in this organization
     */
    public function getUserCountAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Scope to get only active organizations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this organization has conditional rules
     */
    public function hasConditionalRules(): bool
    {
        return !empty($this->conditional_rules);
    }

    /**
     * Get the condition logic (AND/OR)
     */
    public function getConditionLogic(): ?string
    {
        return $this->conditional_rules['logic'] ?? null;
    }

    /**
     * Get the conditions array
     */
    public function getConditions(): array
    {
        return $this->conditional_rules['conditions'] ?? [];
    }
}
