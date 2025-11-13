<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'description',
        'features',
        'max_users',
        'max_products',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'max_users' => 'integer',
        'max_products' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the products available in this tier
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_tier')
                    ->withTimestamps();
    }

    /**
     * Get organizations using this tier
     * Note: Will be implemented when organization-tier relationship is added
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Scope: Only active tiers
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
     * Scope: Filter by tier level (assuming Free < Pro < Enterprise)
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('slug', $level);
    }

    /**
     * Check if tier has a specific product
     */
    public function hasProduct($productId): bool
    {
        return $this->products()->where('product_id', $productId)->exists();
    }

    /**
     * Get count of users in this tier (via organizations)
     */
    public function getUserCountAttribute(): int
    {
        return $this->organizations()->withCount('users')->get()->sum('users_count');
    }

    /**
     * Check if tier is free
     */
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    /**
     * Format price for display
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->price == 0 ? 'Free' : '$' . number_format($this->price, 2);
    }
}
