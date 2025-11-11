<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'cm_campaign_id',
        'cm_list_id',
        'name',
        'subject',
        'from_name',
        'from_email',
        'reply_to',
        'sent_at',
        'status',
        'web_version_url',
        'web_version_text_url',
        'total_recipients',
        'total_opens',
        'unique_opens',
        'total_clicks',
        'unique_clicks',
        'total_bounces',
        'total_unsubscribes',
        'total_spam_complaints',
        'forwards',
        'likes',
        'mentions',
        'open_rate',
        'click_rate',
        'bounce_rate',
        'unsubscribe_rate',
        'stats_last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'stats_last_synced_at' => 'datetime',
        'is_active' => 'boolean',
        'total_recipients' => 'integer',
        'total_opens' => 'integer',
        'unique_opens' => 'integer',
        'total_clicks' => 'integer',
        'unique_clicks' => 'integer',
        'total_bounces' => 'integer',
        'total_unsubscribes' => 'integer',
        'total_spam_complaints' => 'integer',
        'forwards' => 'integer',
        'likes' => 'integer',
        'mentions' => 'integer',
        'open_rate' => 'decimal:2',
        'click_rate' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'unsubscribe_rate' => 'decimal:2',
    ];

    /**
     * Scope to get only active campaigns
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get campaigns sent after a certain date
     */
    public function scopeSentAfter($query, $date)
    {
        return $query->where('sent_at', '>=', $date);
    }

    /**
     * Scope to get campaigns by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Calculate and update all rate metrics
     */
    public function calculateRates(): void
    {
        if ($this->total_recipients > 0) {
            $this->open_rate = round(($this->unique_opens / $this->total_recipients) * 100, 2);
            $this->click_rate = round(($this->unique_clicks / $this->total_recipients) * 100, 2);
            $this->bounce_rate = round(($this->total_bounces / $this->total_recipients) * 100, 2);
            $this->unsubscribe_rate = round(($this->total_unsubscribes / $this->total_recipients) * 100, 2);
        }
    }

    /**
     * Get engagement level based on open and click rates
     */
    public function getEngagementLevelAttribute(): string
    {
        $avgRate = ($this->open_rate + $this->click_rate) / 2;

        if ($avgRate >= 30) {
            return 'high';
        } elseif ($avgRate >= 15) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
