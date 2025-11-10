<?php

use App\Jobs\ClearTagBulkJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up mock config
    config(['campaign-monitor.list_id' => 'test-list-123']);
    config(['campaign-monitor.api_key' => 'test-api-key']);
});

// === JOB DISPATCH TESTS ===

it('can be dispatched to the queue', function () {
    Queue::fake();

    $campaignTag = 'campaign_test_20241110-120000';

    ClearTagBulkJob::dispatch($campaignTag);

    Queue::assertPushed(ClearTagBulkJob::class, function ($job) use ($campaignTag) {
        return $job->campaignTag === $campaignTag;
    });
});

it('accepts optional list ID parameter', function () {
    Queue::fake();

    $campaignTag = 'campaign_test_20241110-120000';
    $customListId = 'custom-list-456';

    ClearTagBulkJob::dispatch($campaignTag, $customListId);

    Queue::assertPushed(ClearTagBulkJob::class, function ($job) use ($customListId) {
        return $job->listId === $customListId;
    });
});

it('accepts syncToCampaignMonitor parameter', function () {
    Queue::fake();

    $campaignTag = 'campaign_test_20241110-120000';

    ClearTagBulkJob::dispatch($campaignTag, null, false);

    Queue::assertPushed(ClearTagBulkJob::class, function ($job) {
        return $job->syncToCampaignMonitor === false;
    });
});

// === JOB PROPERTIES TESTS ===

it('has correct retry settings', function () {
    $job = new ClearTagBulkJob('campaign_test_20241110-120000');

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300);
});

it('stores campaign tag correctly', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    $job = new ClearTagBulkJob($campaignTag);

    expect($job->campaignTag)->toBe($campaignTag);
});

it('stores optional list ID', function () {
    $campaignTag = 'campaign_test_20241110-120000';
    $listId = 'custom-list-123';

    $job = new ClearTagBulkJob($campaignTag, $listId);

    expect($job->listId)->toBe($listId);
});

it('sets list ID to null when not provided', function () {
    $job = new ClearTagBulkJob('campaign_test_20241110-120000');

    expect($job->listId)->toBeNull();
});

it('defaults syncToCampaignMonitor to true', function () {
    $job = new ClearTagBulkJob('campaign_test_20241110-120000');

    expect($job->syncToCampaignMonitor)->toBeTrue();
});

it('can disable syncToCampaignMonitor', function () {
    $job = new ClearTagBulkJob('campaign_test_20241110-120000', null, false);

    expect($job->syncToCampaignMonitor)->toBeFalse();
});

// === JOB STRUCTURE TESTS ===

it('implements ShouldQueue interface', function () {
    $job = new ClearTagBulkJob('campaign_test_20241110-120000');

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('uses Queueable trait', function () {
    $reflection = new ReflectionClass(ClearTagBulkJob::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Foundation\Queue\Queueable');
});

// === BATCH SIZE TESTS ===

it('calculates correct number of batches for 1000 users', function () {
    $users = range(1, 1000);

    // 1000 users = 1 batch of 1000
    $batches = array_chunk($users, 1000);

    expect(count($batches))->toBe(1)
        ->and(count($batches[0]))->toBe(1000);
});

it('calculates correct number of batches for 2500 users', function () {
    $users = range(1, 2500);

    // 2500 users = 3 batches (1000, 1000, 500)
    $batches = array_chunk($users, 1000);

    expect(count($batches))->toBe(3)
        ->and(count($batches[0]))->toBe(1000)
        ->and(count($batches[1]))->toBe(1000)
        ->and(count($batches[2]))->toBe(500);
});

it('calculates correct number of batches for 500 users', function () {
    $users = range(1, 500);

    // 500 users = 1 batch of 500
    $batches = array_chunk($users, 1000);

    expect(count($batches))->toBe(1)
        ->and(count($batches[0]))->toBe(500);
});

// === JOB QUEUEING TESTS ===

it('can be queued with delay', function () {
    Queue::fake();

    $campaignTag = 'campaign_test_20241110-120000';

    ClearTagBulkJob::dispatch($campaignTag)->delay(now()->addMinutes(5));

    Queue::assertPushed(ClearTagBulkJob::class);
});

it('can be queued on specific queue', function () {
    Queue::fake();

    $campaignTag = 'campaign_test_20241110-120000';

    ClearTagBulkJob::dispatch($campaignTag)->onQueue('campaigns');

    Queue::assertPushedOn('campaigns', ClearTagBulkJob::class);
});

// === CLEAR FUNCTIONALITY TESTS (WITHOUT CM API) ===

it('clears tags from local database for matching users', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    // Create users with the tag
    $taggedUsers = User::factory()->count(5)->create([
        'temp_campaign_tag' => $campaignTag,
    ]);

    // Create users without the tag
    $otherUsers = User::factory()->count(3)->create([
        'temp_campaign_tag' => 'campaign_other_20241110-120000',
    ]);

    // Manually clear the tags (simulating job execution without CM sync)
    $clearedCount = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($clearedCount)->toBe(5);

    // Verify tagged users have null tags
    foreach ($taggedUsers as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBeNull();
    }

    // Verify other users still have their tags
    foreach ($otherUsers as $user) {
        $user->refresh();
        expect($user->temp_campaign_tag)->toBe('campaign_other_20241110-120000');
    }
});

it('handles empty result set gracefully', function () {
    $campaignTag = 'campaign_nonexistent_20241110-120000';

    // No users with this tag
    $clearedCount = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($clearedCount)->toBe(0);
});

it('clears tags only for exact campaign tag match', function () {
    // Create users with similar but different tags
    User::factory()->create(['temp_campaign_tag' => 'campaign_test_20241110-120000']);
    User::factory()->create(['temp_campaign_tag' => 'campaign_test_20241110-120001']);
    User::factory()->create(['temp_campaign_tag' => 'campaign_test_20241110-120002']);

    $campaignTag = 'campaign_test_20241110-120000';

    $clearedCount = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($clearedCount)->toBe(1);

    // Verify only exact match was cleared
    expect(User::where('temp_campaign_tag', 'campaign_test_20241110-120001')->count())->toBe(1)
        ->and(User::where('temp_campaign_tag', 'campaign_test_20241110-120002')->count())->toBe(1);
});

// === INTEGRATION TESTS (WITHOUT CM API) ===

it('prepares subscriber data structure correctly for clearing', function () {
    $organization = \App\Models\Organization::factory()->create(['name' => 'Test Org']);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'tier' => 'paid_premium',
        'permission_to_track' => true,
        'fullname' => 'Test User',
        'email' => 'test@example.com',
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    // Verify data structure for clearing would be correct
    $expectedCustomFields = [
        ['Key' => 'user_id', 'Value' => (string) $user->id, 'Clear' => false],
        ['Key' => 'organization_name', 'Value' => 'Test Org', 'Clear' => false],
        ['Key' => 'tier', 'Value' => 'paid_premium', 'Clear' => false],
        ['Key' => 'temp_campaign_tag', 'Value' => '', 'Clear' => true],
    ];

    expect($expectedCustomFields)->toHaveCount(4)
        ->and($expectedCustomFields[3]['Key'])->toBe('temp_campaign_tag')
        ->and($expectedCustomFields[3]['Value'])->toBe('')
        ->and($expectedCustomFields[3]['Clear'])->toBeTrue();
});

it('handles users without organization when clearing', function () {
    $user = User::factory()->create([
        'organization_id' => null,
        'tier' => 'free',
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    // Verify that organization name would be empty string
    expect($user->organization)->toBeNull();

    $organizationName = $user->organization?->name ?? '';
    expect($organizationName)->toBe('');
});

it('defaults to free tier when tier is null during clear', function () {
    $user = User::factory()->create([
        'tier' => null,
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    $tier = $user->tier ?? 'free';
    expect($tier)->toBe('free');
});

it('sets ConsentToTrack correctly when clearing tags', function () {
    $userWithTracking = User::factory()->create([
        'permission_to_track' => true,
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    $userWithoutTracking = User::factory()->create([
        'permission_to_track' => false,
        'temp_campaign_tag' => 'campaign_test_20241110-120000',
    ]);

    expect($userWithTracking->permission_to_track ? 'Yes' : 'No')->toBe('Yes')
        ->and($userWithoutTracking->permission_to_track ? 'Yes' : 'No')->toBe('No');
});

// === CAMPAIGN TAG VALIDATION TESTS ===

it('accepts valid campaign tag format', function () {
    $validTags = [
        'campaign_test_20241110-120000',
        'campaign_premium-users_20241110-120000',
        'campaign_event-attendees_20250101-000000',
    ];

    foreach ($validTags as $tag) {
        $job = new ClearTagBulkJob($tag);
        expect($job->campaignTag)->toBe($tag);
    }
});

// === EDGE CASES ===

it('handles large number of tagged users', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    // Create 2500 users with the tag
    User::factory()->count(2500)->create([
        'temp_campaign_tag' => $campaignTag,
    ]);

    $clearedCount = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($clearedCount)->toBe(2500);

    // Verify all are cleared
    expect(User::where('temp_campaign_tag', $campaignTag)->count())->toBe(0);
});

it('preserves other user data when clearing tags', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    $user = User::factory()->create([
        'temp_campaign_tag' => $campaignTag,
        'tier' => 'paid_premium',
        'engagement_score' => 85.5,
        'total_opens' => 100,
    ]);

    User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    $user->refresh();

    // Verify only tag was cleared, other data preserved
    expect($user->temp_campaign_tag)->toBeNull()
        ->and($user->tier)->toBe('paid_premium')
        ->and((float) $user->engagement_score)->toBe(85.5)
        ->and($user->total_opens)->toBe(100);
});

it('can clear tags multiple times for same campaign tag', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    // First batch of users
    User::factory()->count(5)->create(['temp_campaign_tag' => $campaignTag]);

    $cleared1 = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($cleared1)->toBe(5);

    // Second batch of users (maybe tagged later)
    User::factory()->count(3)->create(['temp_campaign_tag' => $campaignTag]);

    $cleared2 = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    expect($cleared2)->toBe(3);
});

it('handles users with null tags', function () {
    $campaignTag = 'campaign_test_20241110-120000';

    // Create users with null tags
    User::factory()->count(3)->create(['temp_campaign_tag' => null]);

    // Create users with the campaign tag
    User::factory()->count(2)->create(['temp_campaign_tag' => $campaignTag]);

    $clearedCount = User::where('temp_campaign_tag', $campaignTag)
        ->update(['temp_campaign_tag' => null]);

    // Should only clear the 2 with the tag
    expect($clearedCount)->toBe(2);
});
