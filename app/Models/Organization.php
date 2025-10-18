<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
