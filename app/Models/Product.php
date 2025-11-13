<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'is_active',
        'default_opt_in',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_opt_in' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the tiers this product is available in
     */
    public function tiers()
    {
        return $this->belongsToMany(Tier::class, 'product_tier')
                    ->withTimestamps();
    }

    /**
     * Get all user subscriptions for this product
     */
    public function subscriptions()
    {
        return $this->hasMany(UserProductSubscription::class);
    }

    /**
     * Get users subscribed to this product (active only)
     */
    public function subscribers()
    {
        return $this->belongsToMany(User::class, 'user_product_subscriptions')
                    ->withPivot(['is_active', 'subscribed_at', 'unsubscribed_at', 'source', 'notes'])
                    ->withTimestamps()
                    ->wherePivot('is_active', true);
    }

    /**
     * Get all users (including inactive subscriptions)
     */
    public function allSubscribers()
    {
        return $this->belongsToMany(User::class, 'user_product_subscriptions')
                    ->withPivot(['is_active', 'subscribed_at', 'unsubscribed_at', 'source', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Scope: Only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Products with default opt-in
     */
    public function scopeDefaultOptIn($query)
    {
        return $query->where('default_opt_in', true);
    }

    /**
     * Check if product is available in a specific tier
     */
    public function isAvailableForTier($tierId): bool
    {
        return $this->tiers()->where('tier_id', $tierId)->exists();
    }

    /**
     * Get count of active subscribers
     */
    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->subscriptions()->where('is_active', true)->count();
    }

    /**
     * Get count of total subscribers (including inactive)
     */
    public function getTotalSubscribersCountAttribute(): int
    {
        return $this->subscriptions()->count();
    }

    /**
     * Check if a user is subscribed to this product
     */
    public function isUserSubscribed($userId): bool
    {
        return $this->subscriptions()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->exists();
    }
}
