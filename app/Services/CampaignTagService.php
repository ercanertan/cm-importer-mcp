<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for managing campaign tags for complex CDP-driven campaigns
 *
 * This service uses the temp_campaign_tag field to:
 * 1. Tag users matching complex Laravel queries
 * 2. Create CM segments based on tags
 * 3. Send campaigns to tagged users
 * 4. Clear tags after campaign send
 */
class CampaignTagService
{
    protected CmNamingService $namingService;

    public function __construct(CmNamingService $namingService)
    {
        $this->namingService = $namingService;
    }
    /**
     * Generate a unique campaign tag
     *
     * @param string $campaignName
     * @return string
     */
    public function generateCampaignTag(string $campaignName): string
    {
        $slug = Str::slug($campaignName);
        $timestamp = now()->format('Ymd-His');

        return "campaign_{$slug}_{$timestamp}";
    }

    /**
     * Tag users for a campaign
     *
     * @param Collection $users
     * @param string $campaignTag
     * @return array ['tagged' => int, 'failed' => int]
     */
    public function tagUsers(Collection $users, string $campaignTag): array
    {
        $tagged = 0;
        $failed = 0;

        DB::beginTransaction();
        try {
            foreach ($users as $user) {
                $result = $user->update(['temp_campaign_tag' => $campaignTag]);
                $result ? $tagged++ : $failed++;
            }

            DB::commit();

            Log::info('Campaign tag applied to users', [
                'campaign_tag' => $campaignTag,
                'tagged' => $tagged,
                'failed' => $failed,
            ]);

            return [
                'tagged' => $tagged,
                'failed' => $failed,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to tag users for campaign', [
                'campaign_tag' => $campaignTag,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Tag users using bulk update (more efficient for large sets)
     *
     * @param array $userIds
     * @param string $campaignTag
     * @return int Number of users tagged
     */
    public function tagUsersBulk(array $userIds, string $campaignTag): int
    {
        try {
            $count = User::whereIn('id', $userIds)
                ->update(['temp_campaign_tag' => $campaignTag]);

            Log::info('Bulk campaign tag applied', [
                'campaign_tag' => $campaignTag,
                'user_count' => $count,
            ]);

            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to bulk tag users', [
                'campaign_tag' => $campaignTag,
                'user_ids_count' => count($userIds),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get users with a specific campaign tag
     *
     * @param string $campaignTag
     * @return Collection
     */
    public function getUsersByTag(string $campaignTag): Collection
    {
        return User::where('temp_campaign_tag', $campaignTag)
            ->where('cm_status', 'active')
            ->get();
    }

    /**
     * Count users with a specific campaign tag
     *
     * @param string $campaignTag
     * @return int
     */
    public function countUsersByTag(string $campaignTag): int
    {
        return User::where('temp_campaign_tag', $campaignTag)
            ->where('cm_status', 'active')
            ->count();
    }

    /**
     * Clear campaign tag from users
     *
     * @param string $campaignTag
     * @return int Number of users cleared
     */
    public function clearTag(string $campaignTag): int
    {
        try {
            $count = User::where('temp_campaign_tag', $campaignTag)
                ->update(['temp_campaign_tag' => null]);

            Log::info('Campaign tag cleared from users', [
                'campaign_tag' => $campaignTag,
                'users_cleared' => $count,
            ]);

            return $count;
        } catch (\Exception $e) {
            Log::error('Failed to clear campaign tag', [
                'campaign_tag' => $campaignTag,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Clear all campaign tags (cleanup utility)
     *
     * @return int Number of users cleared
     */
    public function clearAllTags(): int
    {
        return User::whereNotNull('temp_campaign_tag')
            ->update(['temp_campaign_tag' => null]);
    }

    /**
     * Tag users based on complex query builder
     * This is the main method for CDP-driven campaigns
     *
     * Example:
     * $query = User::where('tier', 'paid_premium')
     *     ->whereHas('eventAttendances', function($q) {
     *         $q->where('attended_at', '>=', now()->subYears(2));
     *     })
     *     ->where('engagement_score', '>=', 60);
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $campaignName
     * @return array ['campaign_tag' => string, 'tagged' => int]
     */
    public function tagUsersFromQuery($query, string $campaignName): array
    {
        $campaignTag = $this->generateCampaignTag($campaignName);

        // Get user IDs from query
        $userIds = $query->pluck('id')->toArray();

        if (empty($userIds)) {
            Log::warning('No users matched query for campaign', [
                'campaign_name' => $campaignName,
                'campaign_tag' => $campaignTag,
            ]);

            return [
                'campaign_tag' => $campaignTag,
                'tagged' => 0,
            ];
        }

        // Tag users in bulk
        $tagged = $this->tagUsersBulk($userIds, $campaignTag);

        return [
            'campaign_tag' => $campaignTag,
            'tagged' => $tagged,
            'user_ids' => $userIds,
        ];
    }

    /**
     * Get campaign tag statistics
     *
     * @param string $campaignTag
     * @return array
     */
    public function getTagStats(string $campaignTag): array
    {
        $users = $this->getUsersByTag($campaignTag);

        $stats = [
            'campaign_tag' => $campaignTag,
            'total_users' => $users->count(),
            'by_tier' => $users->groupBy('tier')->map->count()->toArray(),
            'by_cm_status' => $users->groupBy('cm_status')->map->count()->toArray(),
            'avg_engagement_score' => round($users->avg('engagement_score') ?? 0, 2),
            'organizations' => $users->pluck('organizations.*.name')->flatten()->unique()->count(),
        ];

        return $stats;
    }

    /**
     * Validate campaign tag format
     *
     * @param string $tag
     * @return bool
     */
    public function isValidTag(string $tag): bool
    {
        // Tag should follow pattern: campaign_{slug}_{timestamp}
        return preg_match('/^campaign_[\w-]+_\d{8}-\d{6}$/', $tag) === 1;
    }

    /**
     * Extract campaign name from tag
     *
     * @param string $tag
     * @return string|null
     */
    public function extractCampaignName(string $tag): ?string
    {
        if (!$this->isValidTag($tag)) {
            return null;
        }

        // Extract name between 'campaign_' and timestamp
        preg_match('/^campaign_([\w-]+)_\d{8}-\d{6}$/', $tag, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Get all active campaign tags
     *
     * @return Collection
     */
    public function getActiveTags(): Collection
    {
        return User::whereNotNull('temp_campaign_tag')
            ->select('temp_campaign_tag')
            ->distinct()
            ->get()
            ->pluck('temp_campaign_tag')
            ->filter(); // Remove nulls
    }

    /**
     * Get statistics for all active campaign tags
     *
     * @return array
     */
    public function getAllTagsStats(): array
    {
        $tags = $this->getActiveTags();

        $stats = [];
        foreach ($tags as $tag) {
            $stats[$tag] = [
                'user_count' => $this->countUsersByTag($tag),
                'campaign_name' => $this->extractCampaignName($tag),
            ];
        }

        return $stats;
    }

    /**
     * Create a descriptive segment name for Campaign Monitor
     *
     * @param string $campaignName
     * @param string $description Optional description
     * @param int|null $userCount Optional user count
     * @return string
     */
    public function generateSegmentName(string $campaignName, ?string $description = null, ?int $userCount = null): string
    {
        // Use CmNamingService for human-readable segment names
        return $this->namingService->generateOneOffSegmentName(
            $campaignName,
            $description ?? $campaignName,
            $userCount
        );
    }

    /**
     * Tag highly engaged users from a specific tier
     * Common use case helper method
     *
     * @param string $tier
     * @param float $minEngagementScore
     * @param string $campaignName
     * @return array
     */
    public function tagHighlyEngagedByTier(string $tier, float $minEngagementScore, string $campaignName): array
    {
        $query = User::where('tier', $tier)
            ->where('engagement_score', '>=', $minEngagementScore)
            ->where('cm_status', 'active');

        return $this->tagUsersFromQuery($query, $campaignName);
    }

    /**
     * Tag users who attended specific event and are email active
     * Common use case helper method
     *
     * @param int $eventId
     * @param int $minDaysSinceActivity
     * @param string $campaignName
     * @return array
     */
    public function tagEventAttendeesActive(int $eventId, int $minDaysSinceActivity, string $campaignName): array
    {
        $query = User::whereHas('eventAttendances', function ($q) use ($eventId) {
            $q->where('event_id', $eventId)
                ->where('attendance_confirmed', true);
        })
            ->where('last_activity_at', '>=', now()->subDays($minDaysSinceActivity))
            ->where('cm_status', 'active');

        return $this->tagUsersFromQuery($query, $campaignName);
    }

    /**
     * Tag disengaged users for re-engagement campaign
     * Common use case helper method
     *
     * @param int $inactiveDays
     * @param string $campaignName
     * @return array
     */
    public function tagDisengagedUsers(int $inactiveDays, string $campaignName): array
    {
        $query = User::where('cm_status', 'active')
            ->where('engagement_score', '<', 40)
            ->where('last_activity_at', '<', now()->subDays($inactiveDays))
            ->whereNotNull('last_activity_at');

        return $this->tagUsersFromQuery($query, $campaignName);
    }
}
