<?php

namespace App\Services;

use App\Models\EmailEngagement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EngagementMetricsService
{
    /**
     * Calculate engagement score for a user based on their email activity
     *
     * Score is 0-100 based on:
     * - Open rate (40 points max)
     * - Click-through rate (40 points max)
     * - Recency of engagement (20 points max)
     *
     * @param User $user
     * @param int $days Number of days to look back (default: 90)
     * @return float Score from 0-100
     */
    public function calculateEngagementScore(User $user, int $days = 90): float
    {
        $engagements = $user->emailEngagements()
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get();

        if ($engagements->isEmpty()) {
            return 0.0;
        }

        // Count events
        $totalSent = $engagements->where('event_type', 'sent')->count();
        $totalOpens = $engagements->where('event_type', 'open')->count();
        $totalClicks = $engagements->where('event_type', 'click')->count();

        // If no emails sent in period, use user's aggregate counts
        if ($totalSent === 0) {
            $totalSent = $user->total_emails_sent;
        }

        // Prevent division by zero
        if ($totalSent === 0) {
            return 0.0;
        }

        // Calculate rates
        $openRate = min(1.0, $totalOpens / $totalSent);
        $clickRate = min(1.0, $totalClicks / $totalSent);

        // Open rate score (40 points max)
        $openScore = $openRate * 40;

        // Click rate score (40 points max)
        $clickScore = $clickRate * 40;

        // Recency score (20 points max)
        $recencyScore = $this->calculateRecencyScore($user);

        $totalScore = $openScore + $clickScore + $recencyScore;

        return round(min(100, $totalScore), 2);
    }

    /**
     * Calculate recency score based on last activity
     *
     * @param User $user
     * @return float Score from 0-20
     */
    protected function calculateRecencyScore(User $user): float
    {
        $lastActivity = $user->last_activity_at;

        if (!$lastActivity) {
            return 0.0;
        }

        $daysSinceActivity = now()->diffInDays($lastActivity);

        // Scoring:
        // 0-7 days: 20 points
        // 8-30 days: 15 points
        // 31-60 days: 10 points
        // 61-90 days: 5 points
        // 90+ days: 0 points

        if ($daysSinceActivity <= 7) {
            return 20.0;
        } elseif ($daysSinceActivity <= 30) {
            return 15.0;
        } elseif ($daysSinceActivity <= 60) {
            return 10.0;
        } elseif ($daysSinceActivity <= 90) {
            return 5.0;
        }

        return 0.0;
    }

    /**
     * Update engagement score for a user
     *
     * @param User $user
     * @param int $days
     * @return float The calculated score
     */
    public function updateEngagementScore(User $user, int $days = 90): float
    {
        $score = $this->calculateEngagementScore($user, $days);

        $user->update(['engagement_score' => $score]);

        return $score;
    }

    /**
     * Update engagement scores for multiple users
     *
     * @param Collection|array $users
     * @param int $days
     * @return array ['updated' => int, 'scores' => array]
     */
    public function updateEngagementScoresForUsers($users, int $days = 90): array
    {
        $updated = 0;
        $scores = [];

        foreach ($users as $user) {
            $score = $this->updateEngagementScore($user, $days);
            $scores[$user->id] = $score;
            $updated++;
        }

        return [
            'updated' => $updated,
            'scores' => $scores,
        ];
    }

    /**
     * Recalculate aggregate engagement metrics for a user from EmailEngagement records
     *
     * @param User $user
     * @return array Updated metrics
     */
    public function recalculateAggregateMetrics(User $user): array
    {
        $metrics = DB::table('email_engagements')
            ->where('user_id', $user->id)
            ->select([
                DB::raw('SUM(CASE WHEN event_type = "sent" THEN 1 ELSE 0 END) as total_sent'),
                DB::raw('SUM(CASE WHEN event_type = "open" THEN 1 ELSE 0 END) as total_opens'),
                DB::raw('SUM(CASE WHEN event_type = "click" THEN 1 ELSE 0 END) as total_clicks'),
                DB::raw('SUM(CASE WHEN event_type = "bounce" THEN 1 ELSE 0 END) as total_bounces'),
                DB::raw('MAX(CASE WHEN event_type = "open" THEN occurred_at END) as last_opened'),
                DB::raw('MAX(CASE WHEN event_type = "click" THEN occurred_at END) as last_clicked'),
            ])
            ->first();

        $lastActivity = max(
            strtotime($metrics->last_opened ?? '1970-01-01'),
            strtotime($metrics->last_clicked ?? '1970-01-01')
        );

        $updates = [
            'total_emails_sent' => $metrics->total_sent ?? 0,
            'total_opens' => $metrics->total_opens ?? 0,
            'total_clicks' => $metrics->total_clicks ?? 0,
            'total_bounces' => $metrics->total_bounces ?? 0,
            'last_email_opened_at' => $metrics->last_opened,
            'last_email_clicked_at' => $metrics->last_clicked,
            'last_activity_at' => $lastActivity > 0 ? date('Y-m-d H:i:s', $lastActivity) : null,
        ];

        $user->update($updates);

        return $updates;
    }

    /**
     * Get engagement statistics for a user
     *
     * @param User $user
     * @param int $days
     * @return array
     */
    public function getEngagementStats(User $user, int $days = 90): array
    {
        $engagements = $user->emailEngagements()
            ->where('occurred_at', '>=', now()->subDays($days))
            ->get();

        $stats = [
            'period_days' => $days,
            'total_sent' => $engagements->where('event_type', 'sent')->count(),
            'total_opens' => $engagements->where('event_type', 'open')->count(),
            'total_clicks' => $engagements->where('event_type', 'click')->count(),
            'total_bounces' => $engagements->where('event_type', 'bounce')->count(),
            'unique_campaigns' => $engagements->pluck('campaign_id')->unique()->count(),
            'open_rate' => 0,
            'click_rate' => 0,
            'click_to_open_rate' => 0,
        ];

        // Calculate rates
        if ($stats['total_sent'] > 0) {
            $stats['open_rate'] = round(($stats['total_opens'] / $stats['total_sent']) * 100, 2);
            $stats['click_rate'] = round(($stats['total_clicks'] / $stats['total_sent']) * 100, 2);
        }

        if ($stats['total_opens'] > 0) {
            $stats['click_to_open_rate'] = round(($stats['total_clicks'] / $stats['total_opens']) * 100, 2);
        }

        // Add engagement score
        $stats['engagement_score'] = $this->calculateEngagementScore($user, $days);

        // Add engagement level
        $stats['engagement_level'] = $this->getEngagementLevel($stats['engagement_score']);

        return $stats;
    }

    /**
     * Get engagement level label based on score
     *
     * @param float $score
     * @return string
     */
    public function getEngagementLevel(float $score): string
    {
        if ($score >= 80) {
            return 'highly_engaged';
        } elseif ($score >= 60) {
            return 'engaged';
        } elseif ($score >= 40) {
            return 'moderately_engaged';
        } elseif ($score >= 20) {
            return 'slightly_engaged';
        }

        return 'not_engaged';
    }

    /**
     * Get users by engagement level
     *
     * @param string $level
     * @param int $days
     * @return Collection
     */
    public function getUsersByEngagementLevel(string $level, int $days = 90): Collection
    {
        $ranges = [
            'highly_engaged' => [80, 100],
            'engaged' => [60, 79.99],
            'moderately_engaged' => [40, 59.99],
            'slightly_engaged' => [20, 39.99],
            'not_engaged' => [0, 19.99],
        ];

        if (!isset($ranges[$level])) {
            return collect();
        }

        [$min, $max] = $ranges[$level];

        return User::whereBetween('engagement_score', [$min, $max])
            ->where('cm_status', 'active')
            ->get();
    }

    /**
     * Identify disengaged users who were previously active
     *
     * @param int $inactiveDays Days of inactivity
     * @param float $previousScoreThreshold Minimum previous engagement score
     * @return Collection
     */
    public function getDisengagedUsers(int $inactiveDays = 60, float $previousScoreThreshold = 60.0): Collection
    {
        return User::where('cm_status', 'active')
            ->where('engagement_score', '<', 40) // Currently low engagement
            ->where('last_activity_at', '<', now()->subDays($inactiveDays))
            ->whereNotNull('last_activity_at')
            ->get();
    }

    /**
     * Get top engaged users
     *
     * @param int $limit
     * @return Collection
     */
    public function getTopEngagedUsers(int $limit = 100): Collection
    {
        return User::where('cm_status', 'active')
            ->where('engagement_score', '>', 0)
            ->orderBy('engagement_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate aggregate statistics for all users
     *
     * @return array
     */
    public function getOverallStats(): array
    {
        $users = User::where('cm_status', 'active')->get();

        $avgScore = $users->avg('engagement_score') ?? 0;

        $byLevel = [
            'highly_engaged' => $users->where('engagement_score', '>=', 80)->count(),
            'engaged' => $users->whereBetween('engagement_score', [60, 79.99])->count(),
            'moderately_engaged' => $users->whereBetween('engagement_score', [40, 59.99])->count(),
            'slightly_engaged' => $users->whereBetween('engagement_score', [20, 39.99])->count(),
            'not_engaged' => $users->where('engagement_score', '<', 20)->count(),
        ];

        return [
            'total_active_users' => $users->count(),
            'average_engagement_score' => round($avgScore, 2),
            'engagement_distribution' => $byLevel,
            'percentage_engaged' => $users->count() > 0
                ? round((($byLevel['highly_engaged'] + $byLevel['engaged']) / $users->count()) * 100, 2)
                : 0,
        ];
    }
}
