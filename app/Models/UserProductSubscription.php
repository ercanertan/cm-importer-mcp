<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProductSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'is_active',
        'subscribed_at',
        'unsubscribed_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    /**
     * Get the user for this subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product for this subscription
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope: Only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Subscriptions created through a specific source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope: Recently subscribed (within last X days)
     */
    public function scopeRecentlySubscribed($query, $days = 30)
    {
        return $query->where('subscribed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: Recently unsubscribed (within last X days)
     */
    public function scopeRecentlyUnsubscribed($query, $days = 30)
    {
        return $query->whereNotNull('unsubscribed_at')
                    ->where('unsubscribed_at', '>=', now()->subDays($days));
    }

    /**
     * Mark subscription as inactive (unsubscribe)
     */
    public function unsubscribe(?string $notes = null): void
    {
        $this->update([
            'is_active' => false,
            'unsubscribed_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Reactivate subscription (re-subscribe)
     */
    public function resubscribe(?string $notes = null): void
    {
        $this->update([
            'is_active' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Get subscription duration in days
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->subscribed_at) {
            return null;
        }

        $endDate = $this->unsubscribed_at ?? now();
        return $this->subscribed_at->diffInDays($endDate);
    }
}
