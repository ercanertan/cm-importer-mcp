<?php

use App\Models\User;
use App\Services\CampaignTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CampaignTagService();
});

// === TAG GENERATION TESTS ===

it('generates campaign tag with correct format', function () {
    $tag = $this->service->generateCampaignTag('Premium Webinar');

    expect($tag)->toMatch('/^campaign_[a-z0-9-]+_\d{8}-\d{6}$/');
});

it('generates unique tags for same campaign name', function () {
    $tag1 = $this->service->generateCampaignTag('Test Campaign');
    sleep(1); // Ensure different timestamp
    $tag2 = $this->service->generateCampaignTag('Test Campaign');

    expect($tag1)->not->toBe($tag2);
});

it('slugifies campaign name in tag', function () {
    $tag = $this->service->generateCampaignTag('Premium Users - Special Event');

    expect($tag)->toContain('campaign_premium-users-special-event_');
});

// === TAG USERS TESTS ===

it('tags users in a collection', function () {
    $users = User::factory()->count(5)->create();
    $campaignTag = 'campaign_test_20241110-120000';

    $result = $this->service->tagUsers($users, $campaignTag);

    expect($result['tagged'])->toBe(5)
        ->and($result['failed'])->toBe(0);

    foreach ($users as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBe($campaignTag);
    }
});

it('tags users in bulk by IDs', function () {
    $users = User::factory()->count(10)->create();
    $userIds = $users->pluck('id')->toArray();
    $campaignTag = 'campaign_test_20241110-120000';

    $count = $this->service->tagUsersBulk($userIds, $campaignTag);

    expect($count)->toBe(10);

    foreach ($users as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBe($campaignTag);
    }
});

it('tags users from query with active users', function () {
    $activeUsers = User::factory()->count(5)->create(['cm_status' => 'active']);
    User::factory()->count(3)->create(['cm_status' => 'unsubscribed']);

    $query = User::where('cm_status', 'active');

    $result = $this->service->tagUsersFromQuery($query, 'Active Users Campaign');

    expect($result['tagged'])->toBe(5)
        ->and($result['campaign_tag'])->toMatch('/^campaign_active-users-campaign_\d{8}-\d{6}$/')
        ->and($result['user_ids'])->toHaveCount(5);
});

it('handles empty query result gracefully', function () {
    $query = User::where('id', 0); // No users match

    $result = $this->service->tagUsersFromQuery($query, 'Empty Campaign');

    expect($result['tagged'])->toBe(0)
        ->and($result['campaign_tag'])->toMatch('/^campaign_empty-campaign_\d{8}-\d{6}$/');
});

// === GET USERS BY TAG TESTS ===

it('gets users by campaign tag', function () {
    $taggedUsers = User::factory()->count(5)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
        'cm_status' => 'active',
    ]);

    User::factory()->count(3)->create([
        'temp_campaign_tag' => 'campaign_other_20241110-120000',
        'cm_status' => 'active',
    ]);

    $users = $this->service->getUsersByTag('campaign_test_20241110-120000');

    expect($users)->toHaveCount(5);
});

it('only gets active users by tag', function () {
    User::factory()->count(3)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
        'cm_status' => 'active',
    ]);

    User::factory()->count(2)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
        'cm_status' => 'unsubscribed',
    ]);

    $users = $this->service->getUsersByTag('campaign_test_20241110-120000');

    expect($users)->toHaveCount(3);
});

it('counts users by campaign tag', function () {
    User::factory()->count(7)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
        'cm_status' => 'active',
    ]);

    $count = $this->service->countUsersByTag('campaign_test_20241110-120000');

    expect($count)->toBe(7);
});

// === CLEAR TAG TESTS ===

it('clears campaign tag from users', function () {
    $users = User::factory()->count(5)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    $count = $this->service->clearTag('campaign_test_20241110-120000');

    expect($count)->toBe(5);

    foreach ($users as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBeNull();
    }
});

it('clears all campaign tags', function () {
    User::factory()->count(3)->create(['temp_campaign_tag' => 'campaign_a_20241110-120000']);
    User::factory()->count(2)->create(['temp_campaign_tag' => 'campaign_b_20241110-120000']);
    User::factory()->count(1)->create(['temp_campaign_tag' => null]);

    $count = $this->service->clearAllTags();

    expect($count)->toBe(5);

    $taggedUsers = User::whereNotNull('temp_campaign_tag')->count();
    expect($taggedUsers)->toBe(0);
});

// === TAG VALIDATION TESTS ===

it('validates correct campaign tag format', function () {
    $validTag = 'campaign_test-campaign_20241110-120000';

    expect($this->service->isValidTag($validTag))->toBeTrue();
});

it('rejects invalid campaign tag formats', function () {
    $invalidTags = [
        'invalid_tag',
        'campaign_test',
        'campaign_test_20241110',
        'test_campaign_20241110-120000',
        'campaign__20241110-120000',
    ];

    foreach ($invalidTags as $tag) {
        expect($this->service->isValidTag($tag))->toBeFalse();
    }
});

it('extracts campaign name from valid tag', function () {
    $tag = 'campaign_premium-webinar_20241110-120000';

    $name = $this->service->extractCampaignName($tag);

    expect($name)->toBe('premium-webinar');
});

it('returns null for invalid tag when extracting name', function () {
    $name = $this->service->extractCampaignName('invalid_tag');

    expect($name)->toBeNull();
});

// === TAG STATISTICS TESTS ===

it('gets tag statistics', function () {
    $users = User::factory()->count(10)->create([
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
        'cm_status' => 'active',
        'tier' => 'paid_premium',
        'engagement_score' => 75,
    ]);

    $stats = $this->service->getTagStats('campaign_test_20241110-120000');

    expect($stats)->toHaveKeys([
        'campaign_tag',
        'total_users',
        'by_tier',
        'by_cm_status',
        'avg_engagement_score',
        'organizations',
    ])
        ->and($stats['total_users'])->toBe(10)
        ->and($stats['by_tier']['paid_premium'])->toBe(10)
        ->and($stats['avg_engagement_score'])->toBe(75.0);
});

it('gets all active tags', function () {
    User::factory()->count(3)->create(['temp_campaign_tag' => 'campaign_a_20241110-120000']);
    User::factory()->count(2)->create(['temp_campaign_tag' => 'campaign_b_20241110-120000']);
    User::factory()->count(1)->create(['temp_campaign_tag' => null]);

    $tags = $this->service->getActiveTags();

    expect($tags)->toHaveCount(2)
        ->and($tags->contains('campaign_a_20241110-120000'))->toBeTrue()
        ->and($tags->contains('campaign_b_20241110-120000'))->toBeTrue();
});

it('gets statistics for all active tags', function () {
    User::factory()->count(3)->create(['temp_campaign_tag' => 'campaign_webinar_20241110-120000']);
    User::factory()->count(2)->create(['temp_campaign_tag' => 'campaign_newsletter_20241110-120000']);

    $stats = $this->service->getAllTagsStats();

    expect($stats)->toHaveCount(2)
        ->and($stats['campaign_webinar_20241110-120000']['user_count'])->toBe(3)
        ->and($stats['campaign_newsletter_20241110-120000']['user_count'])->toBe(2)
        ->and($stats['campaign_webinar_20241110-120000']['campaign_name'])->toBe('webinar');
});

// === SEGMENT NAME GENERATION TESTS ===

it('generates segment name with default prefix', function () {
    $name = $this->service->generateSegmentName('Premium Users');

    expect($name)->toMatch('/^\[One-off\] Premium Users \(\d{4}-\d{2}-\d{2}\)$/');
});

it('generates segment name with description', function () {
    $name = $this->service->generateSegmentName('Premium Users', 'High Engagement');

    expect($name)->toMatch('/^\[One-off\] Premium Users - High Engagement \(\d{4}-\d{2}-\d{2}\)$/');
});

// === HELPER METHODS TESTS ===

it('tags highly engaged users by tier', function () {
    $premiumHighEngaged = User::factory()->count(5)->create([
        'tier' => 'paid_premium',
        'engagement_score' => 80,
        'cm_status' => 'active',
    ]);

    $premiumLowEngaged = User::factory()->count(3)->create([
        'tier' => 'paid_premium',
        'engagement_score' => 50,
        'cm_status' => 'active',
    ]);

    $freeHighEngaged = User::factory()->count(2)->create([
        'tier' => 'free',
        'engagement_score' => 80,
        'cm_status' => 'active',
    ]);

    $result = $this->service->tagHighlyEngagedByTier('paid_premium', 70.0, 'Premium Engaged');

    expect($result['tagged'])->toBe(5);

    foreach ($premiumHighEngaged as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->not->toBeNull();
    }
});

it('tags event attendees who are active', function () {
    $event = \App\Models\Event::factory()->create();

    $activeAttendees = User::factory()->count(5)->create([
        'last_activity_at' => now()->subDays(10),
        'cm_status' => 'active',
    ]);

    $inactiveAttendees = User::factory()->count(3)->create([
        'last_activity_at' => now()->subDays(100),
        'cm_status' => 'active',
    ]);

    foreach ($activeAttendees as $user) {
        \App\Models\EventAttendance::factory()->attended()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendance_confirmed' => true,
        ]);
    }

    foreach ($inactiveAttendees as $user) {
        \App\Models\EventAttendance::factory()->attended()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'attendance_confirmed' => true,
        ]);
    }

    $result = $this->service->tagEventAttendeesActive($event->id, 30, 'Event Active Users');

    expect($result['tagged'])->toBe(5);
});

it('tags disengaged users for re-engagement', function () {
    $disengaged = User::factory()->count(5)->create([
        'cm_status' => 'active',
        'engagement_score' => 30,
        'last_activity_at' => now()->subDays(90),
    ]);

    $engaged = User::factory()->count(3)->create([
        'cm_status' => 'active',
        'engagement_score' => 70,
        'last_activity_at' => now()->subDays(5),
    ]);

    $result = $this->service->tagDisengagedUsers(60, 'Re-engagement Campaign');

    expect($result['tagged'])->toBe(5);

    foreach ($disengaged as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->not->toBeNull();
    }
});

// === EDGE CASES ===

it('handles tagging with no matching users', function () {
    $result = $this->service->tagHighlyEngagedByTier('non_existent_tier', 90.0, 'Test');

    expect($result['tagged'])->toBe(0);
});

it('handles clearing tag that does not exist', function () {
    $count = $this->service->clearTag('campaign_nonexistent_20241110-120000');

    expect($count)->toBe(0);
});

it('returns empty collection for getUsersByTag with no matches', function () {
    $users = $this->service->getUsersByTag('campaign_nonexistent_20241110-120000');

    expect($users)->toHaveCount(0);
});

it('handles transaction rollback on error', function () {
    $users = User::factory()->count(3)->create();

    // Force an error by passing invalid tag (assuming database constraint)
    try {
        // Create a scenario where update might fail
        $this->service->tagUsers($users, str_repeat('a', 300)); // Tag too long
    } catch (\Exception $e) {
        // Expected to throw
    }

    // Verify users were not tagged
    foreach ($users as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBeNull();
    }
})->skip('Requires database constraint testing');
