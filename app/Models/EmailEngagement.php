<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailEngagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_id',
        'campaign_name',
        'event_type',
        'url',
        'bounce_type',
        'bounce_reason',
        'ip_address',
        'user_agent',
        'event_data',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the user that engaged with the email
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to filter opens
     */
    public function scopeOpens($query)
    {
        return $query->where('event_type', 'open');
    }

    /**
     * Scope to filter clicks
     */
    public function scopeClicks($query)
    {
        return $query->where('event_type', 'click');
    }

    /**
     * Scope to filter bounces
     */
    public function scopeBounces($query)
    {
        return $query->where('event_type', 'bounce');
    }

    /**
     * Scope to filter by campaign
     */
    public function scopeForCampaign($query, string $campaignId)
    {
        return $query->where('campaign_id', $campaignId);
    }

    /**
     * Scope to get recent engagements
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }

    /**
     * Check if this is an open event
     */
    public function isOpen(): bool
    {
        return $this->event_type === 'open';
    }

    /**
     * Check if this is a click event
     */
    public function isClick(): bool
    {
        return $this->event_type === 'click';
    }

    /**
     * Check if this is a bounce event
     */
    public function isBounce(): bool
    {
        return $this->event_type === 'bounce';
    }

    /**
     * Check if this is a hard bounce
     */
    public function isHardBounce(): bool
    {
        return $this->event_type === 'bounce' && $this->bounce_type === 'hard';
    }

    /**
     * Check if this is a soft bounce
     */
    public function isSoftBounce(): bool
    {
        return $this->event_type === 'bounce' && $this->bounce_type === 'soft';
    }
}
