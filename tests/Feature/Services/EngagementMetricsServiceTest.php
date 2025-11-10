<?php

use App\Models\EmailEngagement;
use App\Models\User;
use App\Services\EngagementMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new EngagementMetricsService();
    $this->user = User::factory()->create([
        'total_emails_sent' => 0,
        'total_opens' => 0,
        'total_clicks' => 0,
        'total_bounces' => 0,
        'engagement_score' => 0,
    ]);
});

// === ENGAGEMENT SCORE CALCULATION TESTS ===

it('calculates engagement score with perfect open and click rates', function () {
    // Create 10 sent, 10 opens, 10 clicks
    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'sent',
        'occurred_at' => now()->subDays(10),
    ]);

    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'click',
        'occurred_at' => now()->subDays(2),
    ]);

    // Update last_activity_at for recency score
    $this->user->update(['last_activity_at' => now()->subDays(2)]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Should be close to 100: 40 (opens) + 40 (clicks) + 20 (recency)
    expect($score)->toBeGreaterThanOrEqual(95)
        ->and($score)->toBeLessThanOrEqual(100);
});

it('calculates zero score for user with no engagements', function () {
    $score = $this->service->calculateEngagementScore($this->user);

    expect($score)->toBe(0.0);
});

it('calculates score with only opens', function () {
    $this->user->update([
        'total_emails_sent' => 10,
        'last_activity_at' => now()->subDays(5),
    ]);

    EmailEngagement::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Should be around 20 (50% open rate) + 20 (recent activity) = 40
    expect($score)->toBeGreaterThan(30)
        ->and($score)->toBeLessThan(50);
});

it('respects lookback period for score calculation', function () {
    // Old engagement (outside 90 day window)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(100),
    ]);

    // Recent engagement (within window)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(10),
    ]);

    $this->user->update(['total_emails_sent' => 10]);

    $score = $this->service->calculateEngagementScore($this->user, 90);

    // Should only count the recent engagement
    expect($score)->toBeGreaterThan(0);
});

// === RECENCY SCORE TESTS ===

it('gives max recency score for activity within 7 days', function () {
    $this->user->update([
        'last_activity_at' => now()->subDays(5),
        'total_emails_sent' => 10,
    ]);

    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Recency portion should be 20 points
    expect($score)->toBeGreaterThanOrEqual(20);
});

it('gives reduced recency score for older activity', function () {
    $this->user->update([
        'last_activity_at' => now()->subDays(45),
        'total_emails_sent' => 10,
    ]);

    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(45),
    ]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Recency portion should be 10 points (31-60 days)
    // Score will be around 14 (10% open rate * 40 = 4, + 10 recency = 14)
    expect($score)->toBeGreaterThan(0)
        ->and($score)->toBeLessThan(30);
});

// === UPDATE SCORE TESTS ===

it('updates engagement score for a user', function () {
    EmailEngagement::factory()->count(5)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(10),
    ]);

    $this->user->update([
        'total_emails_sent' => 10,
        'last_activity_at' => now()->subDays(10),
    ]);

    $score = $this->service->updateEngagementScore($this->user);

    $this->user->refresh();

    expect((float)$this->user->engagement_score)->toBe($score)
        ->and($score)->toBeGreaterThan(0);
});

it('updates engagement scores for multiple users', function () {
    $users = User::factory()->count(3)->create(['total_emails_sent' => 10]);

    foreach ($users as $user) {
        EmailEngagement::factory()->count(5)->create([
            'user_id' => $user->id,
            'event_type' => 'open',
            'occurred_at' => now()->subDays(5),
        ]);

        $user->update(['last_activity_at' => now()->subDays(5)]);
    }

    $result = $this->service->updateEngagementScoresForUsers($users);

    expect($result['updated'])->toBe(3)
        ->and($result['scores'])->toHaveCount(3);
});

// === AGGREGATE METRICS TESTS ===

it('recalculates aggregate metrics from engagement records', function () {
    // Create various engagements
    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'sent',
        'occurred_at' => now()->subDays(10),
    ]);

    EmailEngagement::factory()->count(7)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(8),
    ]);

    EmailEngagement::factory()->count(3)->create([
        'user_id' => $this->user->id,
        'event_type' => 'click',
        'occurred_at' => now()->subDays(5),
    ]);

    EmailEngagement::factory()->count(1)->create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'occurred_at' => now()->subDays(7),
    ]);

    $metrics = $this->service->recalculateAggregateMetrics($this->user);

    expect($metrics['total_emails_sent'])->toBe(10)
        ->and($metrics['total_opens'])->toBe(7)
        ->and($metrics['total_clicks'])->toBe(3)
        ->and($metrics['total_bounces'])->toBe(1)
        ->and($metrics['last_email_opened_at'])->not->toBeNull()
        ->and($metrics['last_email_clicked_at'])->not->toBeNull();

    $this->user->refresh();
    expect($this->user->total_emails_sent)->toBe(10)
        ->and($this->user->total_opens)->toBe(7)
        ->and($this->user->total_clicks)->toBe(3);
});

// === ENGAGEMENT STATS TESTS ===

it('gets comprehensive engagement stats for a user', function () {
    // Create engagement data
    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'sent',
        'campaign_id' => 'campaign-1',
        'occurred_at' => now()->subDays(10),
    ]);

    EmailEngagement::factory()->count(8)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'campaign_id' => 'campaign-1',
        'occurred_at' => now()->subDays(8),
    ]);

    EmailEngagement::factory()->count(4)->create([
        'user_id' => $this->user->id,
        'event_type' => 'click',
        'campaign_id' => 'campaign-1',
        'occurred_at' => now()->subDays(5),
    ]);

    $this->user->update(['last_activity_at' => now()->subDays(5)]);

    $stats = $this->service->getEngagementStats($this->user);

    expect($stats)->toHaveKeys([
        'period_days',
        'total_sent',
        'total_opens',
        'total_clicks',
        'total_bounces',
        'unique_campaigns',
        'open_rate',
        'click_rate',
        'click_to_open_rate',
        'engagement_score',
        'engagement_level',
    ])
        ->and($stats['total_sent'])->toBe(10)
        ->and($stats['total_opens'])->toBe(8)
        ->and($stats['total_clicks'])->toBe(4)
        ->and($stats['open_rate'])->toBe(80.0)
        ->and($stats['click_rate'])->toBe(40.0)
        ->and($stats['click_to_open_rate'])->toBe(50.0)
        ->and($stats['unique_campaigns'])->toBe(1);
});

// === ENGAGEMENT LEVEL TESTS ===

it('categorizes highly engaged users correctly', function () {
    $level = $this->service->getEngagementLevel(85.0);
    expect($level)->toBe('highly_engaged');
});

it('categorizes engaged users correctly', function () {
    $level = $this->service->getEngagementLevel(65.0);
    expect($level)->toBe('engaged');
});

it('categorizes moderately engaged users correctly', function () {
    $level = $this->service->getEngagementLevel(45.0);
    expect($level)->toBe('moderately_engaged');
});

it('categorizes slightly engaged users correctly', function () {
    $level = $this->service->getEngagementLevel(25.0);
    expect($level)->toBe('slightly_engaged');
});

it('categorizes not engaged users correctly', function () {
    $level = $this->service->getEngagementLevel(10.0);
    expect($level)->toBe('not_engaged');
});

// === USER FILTERING TESTS ===

it('gets users by engagement level', function () {
    // Create users with different engagement scores
    $highlyEngaged = User::factory()->create(['engagement_score' => 85, 'cm_status' => 'active']);
    $engaged = User::factory()->create(['engagement_score' => 65, 'cm_status' => 'active']);
    $notEngaged = User::factory()->create(['engagement_score' => 15, 'cm_status' => 'active']);

    $highUsers = $this->service->getUsersByEngagementLevel('highly_engaged');

    expect($highUsers)->toHaveCount(1)
        ->and($highUsers->first()->id)->toBe($highlyEngaged->id);
});

it('gets disengaged users', function () {
    $disengaged = User::factory()->create([
        'engagement_score' => 30,
        'last_activity_at' => now()->subDays(70),
        'cm_status' => 'active',
    ]);

    $active = User::factory()->create([
        'engagement_score' => 80,
        'last_activity_at' => now()->subDays(5),
        'cm_status' => 'active',
    ]);

    $users = $this->service->getDisengagedUsers(60);

    expect($users)->toHaveCount(1)
        ->and($users->first()->id)->toBe($disengaged->id);
});

it('gets top engaged users', function () {
    User::factory()->count(5)->create([
        'cm_status' => 'active',
        'engagement_score' => 50,
    ]);

    $topUser = User::factory()->create([
        'cm_status' => 'active',
        'engagement_score' => 95,
    ]);

    $topUsers = $this->service->getTopEngagedUsers(3);

    expect($topUsers)->toHaveCount(3)
        ->and($topUsers->first()->id)->toBe($topUser->id)
        ->and((float)$topUsers->first()->engagement_score)->toBe(95.0);
});

// === OVERALL STATS TESTS ===

it('calculates overall engagement statistics', function () {
    // Create users with different engagement levels
    User::factory()->count(10)->create(['engagement_score' => 85, 'cm_status' => 'active']); // highly_engaged
    User::factory()->count(15)->create(['engagement_score' => 65, 'cm_status' => 'active']); // engaged
    User::factory()->count(20)->create(['engagement_score' => 45, 'cm_status' => 'active']); // moderately_engaged
    User::factory()->count(10)->create(['engagement_score' => 25, 'cm_status' => 'active']); // slightly_engaged
    User::factory()->count(5)->create(['engagement_score' => 10, 'cm_status' => 'active']); // not_engaged

    $stats = $this->service->getOverallStats();

    expect($stats)->toHaveKeys([
        'total_active_users',
        'average_engagement_score',
        'engagement_distribution',
        'percentage_engaged',
    ])
        ->and($stats['total_active_users'])->toBe(61) // 60 created + 1 from beforeEach
        ->and($stats['engagement_distribution']['highly_engaged'])->toBe(10)
        ->and($stats['engagement_distribution']['engaged'])->toBe(15)
        ->and($stats['percentage_engaged'])->toBeGreaterThan(0);
});

it('handles empty user base for overall stats', function () {
    // Delete the user from beforeEach
    $this->user->delete();

    $stats = $this->service->getOverallStats();

    expect($stats['total_active_users'])->toBe(0)
        ->and($stats['average_engagement_score'])->toBe(0.0)
        ->and($stats['percentage_engaged'])->toBe(0);
});

// === EDGE CASES ===

it('handles user with no activity dates gracefully', function () {
    $this->user->update([
        'last_activity_at' => null,
        'total_emails_sent' => 10,
    ]);

    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(10),
    ]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Should still calculate score without recency component
    expect($score)->toBeGreaterThanOrEqual(0);
});

it('caps open and click rates at 100%', function () {
    // Create more opens/clicks than sent (edge case)
    $this->user->update(['total_emails_sent' => 5]);

    EmailEngagement::factory()->count(10)->create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    $this->user->update(['last_activity_at' => now()->subDays(5)]);

    $score = $this->service->calculateEngagementScore($this->user);

    // Score should be capped at 100
    expect($score)->toBeLessThanOrEqual(100);
});
