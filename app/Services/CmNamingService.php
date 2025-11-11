<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Campaign Monitor Naming Service
 *
 * Provides human-readable naming conventions for all Campaign Monitor elements.
 * CM admins should be able to understand everything without Laravel training.
 */
class CmNamingService
{
    /**
     * Format tier as human-readable tag
     *
     * @param string $tier - free, paid_pro, paid_premium, enterprise
     * @return string - e.g., "[Tier] Paid Premium"
     */
    public function formatTierTag(string $tier): string
    {
        $category = config('campaign-monitor.tag_categories.tier', '[Tier]');
        $tierName = config("campaign-monitor.tier_names.{$tier}", Str::title($tier));

        return "{$category} {$tierName}";
    }

    /**
     * Format engagement score as human-readable tag
     *
     * @param int $score - 0-100 engagement score
     * @return string - e.g., "[Engagement] High (70-100)"
     */
    public function formatEngagementTag(int $score): string
    {
        $category = config('campaign-monitor.tag_categories.engagement', '[Engagement]');

        if ($score >= 70) {
            $level = config('campaign-monitor.engagement_levels.high', 'High (70-100)');
        } elseif ($score >= 40) {
            $level = config('campaign-monitor.engagement_levels.medium', 'Medium (40-69)');
        } else {
            $level = config('campaign-monitor.engagement_levels.low', 'Low (0-39)');
        }

        return "{$category} {$level}";
    }

    /**
     * Format status as human-readable tag
     *
     * @param string $status - active, inactive, bounced, unsubscribed, spam_complaint
     * @return string - e.g., "[Status] Active"
     */
    public function formatStatusTag(string $status): string
    {
        $category = config('campaign-monitor.tag_categories.status', '[Status]');
        $statusName = config("campaign-monitor.status_names.{$status}", Str::title($status));

        return "{$category} {$statusName}";
    }

    /**
     * Format product subscription as human-readable tag
     *
     * @param string $productSlug - e.g., "premium-webinar-series"
     * @param string|null $productName - Optional human-readable product name
     * @return string - e.g., "[Product] Premium Webinar Series"
     */
    public function formatProductTag(string $productSlug, ?string $productName = null): string
    {
        $category = config('campaign-monitor.tag_categories.product', '[Product]');
        $name = $productName ?? Str::title(str_replace(['-', '_'], ' ', $productSlug));

        return "{$category} {$name}";
    }

    /**
     * Format event attendance as human-readable tag
     *
     * @param string $eventSlug - e.g., "annual-conference-2025"
     * @param string|null $eventName - Optional human-readable event name
     * @return string - e.g., "[Event] Annual Conference 2025"
     */
    public function formatEventTag(string $eventSlug, ?string $eventName = null): string
    {
        $category = config('campaign-monitor.tag_categories.event', '[Event]');
        $name = $eventName ?? Str::title(str_replace(['-', '_'], ' ', $eventSlug));

        return "{$category} {$name}";
    }

    /**
     * Format behavioral flag as human-readable tag
     *
     * @param string $behavior - e.g., "frequent-attendee", "never-opened"
     * @param string|null $behaviorName - Optional human-readable name
     * @return string - e.g., "[Behavior] Frequent Attendee"
     */
    public function formatBehaviorTag(string $behavior, ?string $behaviorName = null): string
    {
        $category = config('campaign-monitor.tag_categories.behavior', '[Behavior]');
        $name = $behaviorName ?? Str::title(str_replace(['-', '_'], ' ', $behavior));

        return "{$category} {$name}";
    }

    /**
     * Format organization type as human-readable tag
     *
     * @param string $orgType - e.g., "startup", "enterprise", "nonprofit"
     * @param string|null $orgTypeName - Optional human-readable name
     * @return string - e.g., "[Organization] Enterprise"
     */
    public function formatOrganizationTag(string $orgType, ?string $orgTypeName = null): string
    {
        $category = config('campaign-monitor.tag_categories.organization', '[Organization]');
        $name = $orgTypeName ?? Str::title(str_replace(['-', '_'], ' ', $orgType));

        return "{$category} {$name}";
    }

    /**
     * Generate human-readable segment name for one-off campaigns
     *
     * @param string $category - e.g., "Event Alumni", "Win-Back Campaign"
     * @param string $description - Campaign description
     * @param int|null $userCount - Optional user count
     * @param Carbon|null $date - Optional date (defaults to today)
     * @return string - e.g., "[One-off] Event Alumni - High Engagement (247 users) [2024-11-10]"
     */
    public function generateOneOffSegmentName(
        string $category,
        string $description,
        ?int $userCount = null,
        ?Carbon $date = null
    ): string {
        $prefix = config('campaign-monitor.segment_prefixes.one_off', '[One-off]');
        $formattedDate = ($date ?? now())->format('Y-m-d');

        $name = "{$prefix} {$category} - {$description}";

        if ($userCount) {
            $name .= " ({$userCount} users)";
        }

        $name .= " [{$formattedDate}]";

        return $name;
    }

    /**
     * Generate human-readable segment name for recurring campaigns
     *
     * @param string $tier - User tier (free, paid_pro, paid_premium, enterprise)
     * @param string $frequency - daily, weekly, monthly
     * @param string $campaignType - e.g., "Digest", "Newsletter", "Report"
     * @return string - e.g., "[Recurring] Paid Pro - Daily Digest"
     */
    public function generateRecurringSegmentName(
        string $tier,
        string $frequency,
        string $campaignType
    ): string {
        $prefix = config('campaign-monitor.segment_prefixes.recurring', '[Recurring]');
        $tierName = config("campaign-monitor.tier_names.{$tier}", Str::title($tier));
        $frequencyName = Str::title($frequency);

        return "{$prefix} {$tierName} - {$frequencyName} {$campaignType}";
    }

    /**
     * Generate human-readable segment name for automated campaigns
     *
     * @param string $trigger - e.g., "Welcome", "Onboarding", "Abandoned Cart"
     * @param string|null $description - Optional description
     * @return string - e.g., "[Automated] Welcome Series - New User"
     */
    public function generateAutomatedSegmentName(string $trigger, ?string $description = null): string
    {
        $prefix = config('campaign-monitor.segment_prefixes.automated', '[Automated]');
        $name = "{$prefix} {$trigger}";

        if ($description) {
            $name .= " - {$description}";
        }

        return $name;
    }

    /**
     * Generate human-readable campaign name for one-off campaigns
     *
     * @param string $category - e.g., "Event Alumni", "Product Launch"
     * @param string $description - Campaign description
     * @param Carbon|null $date - Optional date (defaults to today)
     * @return string - e.g., "[Event Alumni] We Miss You - Nov 10, 2024"
     */
    public function generateOneOffCampaignName(
        string $category,
        string $description,
        ?Carbon $date = null
    ): string {
        $formattedDate = ($date ?? now())->format('M j, Y');

        return "[{$category}] {$description} - {$formattedDate}";
    }

    /**
     * Generate human-readable campaign name for recurring campaigns
     *
     * @param string $tier - User tier
     * @param string $frequency - daily, weekly, monthly
     * @param Carbon|null $date - Optional date for this send
     * @return string - e.g., "[Recurring] Paid Pro Daily Digest - Nov 10, 2024"
     */
    public function generateRecurringCampaignName(
        string $tier,
        string $frequency,
        ?Carbon $date = null
    ): string {
        $tierName = config("campaign-monitor.tier_names.{$tier}", Str::title($tier));
        $frequencyName = Str::title($frequency);
        $formattedDate = ($date ?? now())->format('M j, Y');

        return "[Recurring] {$tierName} {$frequencyName} - {$formattedDate}";
    }

    /**
     * Generate human-readable campaign name for event campaigns
     *
     * @param string $eventName - Name of the event
     * @param string $description - Campaign description
     * @param Carbon|null $date - Optional date
     * @return string - e.g., "[Event] Annual Conference 2025 - Registration Open - Nov 10, 2024"
     */
    public function generateEventCampaignName(
        string $eventName,
        string $description,
        ?Carbon $date = null
    ): string {
        $formattedDate = ($date ?? now())->format('M j, Y');

        return "[Event] {$eventName} - {$description} - {$formattedDate}";
    }

    /**
     * Generate human-readable campaign name for test campaigns
     *
     * @param string $description - Test description
     * @param Carbon|null $date - Optional date
     * @return string - e.g., "[Test] Subject Line A/B Test - Nov 10, 2024"
     */
    public function generateTestCampaignName(string $description, ?Carbon $date = null): string
    {
        $prefix = config('campaign-monitor.segment_prefixes.test', '[Test]');
        $formattedDate = ($date ?? now())->format('M j, Y');

        return "{$prefix} {$description} - {$formattedDate}";
    }

    /**
     * Get human-readable custom field name by key
     *
     * @param string $key - Field key (e.g., 'engagement_score')
     * @return string - Human-readable name (e.g., 'Engagement Score (0-100)')
     */
    public function getCustomFieldName(string $key): string
    {
        return config("campaign-monitor.core_fields.{$key}.name", Str::title($key));
    }

    /**
     * Get human-readable tier name
     *
     * @param string $tier - Tier key (e.g., 'paid_pro')
     * @return string - Human-readable name (e.g., 'Paid Pro')
     */
    public function getTierName(string $tier): string
    {
        return config("campaign-monitor.tier_names.{$tier}", Str::title($tier));
    }

    /**
     * Get human-readable status name
     *
     * @param string $status - Status key (e.g., 'spam_complaint')
     * @return string - Human-readable name (e.g., 'Spam Complaint')
     */
    public function getStatusName(string $status): string
    {
        return config("campaign-monitor.status_names.{$status}", Str::title($status));
    }

    /**
     * Parse campaign tag to extract category, description, and date
     * Useful for displaying campaign information in admin UI
     *
     * @param string $campaignTag - e.g., "campaign_event-alumni-reengagement_20251110-143522"
     * @return array - ['slug' => 'event-alumni-reengagement', 'datetime' => Carbon]
     */
    public function parseCampaignTag(string $campaignTag): array
    {
        // Format: campaign_{slug}_{YmdHis}
        if (preg_match('/^campaign_(.+?)_(\d{8})-(\d{6})$/', $campaignTag, $matches)) {
            return [
                'slug' => $matches[1],
                'name' => Str::title(str_replace(['-', '_'], ' ', $matches[1])),
                'datetime' => Carbon::createFromFormat('Ymd-His', $matches[2] . '-' . $matches[3]),
            ];
        }

        return [
            'slug' => $campaignTag,
            'name' => $campaignTag,
            'datetime' => null,
        ];
    }

    /**
     * Get all permanent tags that should be synced for a user
     * These are tags for recurring campaigns (tier, engagement, status)
     *
     * @param \App\Models\User $user
     * @return array - Array of tag strings
     */
    public function getUserPermanentTags($user): array
    {
        $tags = [];

        // Tier tag
        if ($user->tier) {
            $tags[] = $this->formatTierTag($user->tier);
        }

        // Engagement tag
        if ($user->engagement_score !== null) {
            $tags[] = $this->formatEngagementTag($user->engagement_score);
        }

        // Status tag
        if ($user->cm_status) {
            $tags[] = $this->formatStatusTag($user->cm_status);
        }

        return $tags;
    }

    /**
     * Get expected tags for a user based on their current state
     * Used by tag sync command to calculate what tags should be added/removed
     *
     * @param \App\Models\User $user
     * @return array - ['add' => [...], 'remove' => [...]]
     */
    public function calculateTagChanges($user): array
    {
        $expectedTags = $this->getUserPermanentTags($user);

        // Get current tags from CM (would need to fetch from CM API)
        // For now, return expected tags for adding
        // Real implementation would compare with actual CM tags

        return [
            'expected' => $expectedTags,
            // These would be populated by comparing with actual CM tags:
            // 'add' => array_diff($expectedTags, $currentTags),
            // 'remove' => array_diff($currentTags, $expectedTags),
        ];
    }
}
