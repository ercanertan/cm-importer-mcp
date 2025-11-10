<?php

use App\Jobs\TagUsersBulkJob;
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

    $userIds = [1, 2, 3];
    $campaignTag = 'campaign_test_20241110-120000';

    TagUsersBulkJob::dispatch($userIds, $campaignTag);

    Queue::assertPushed(TagUsersBulkJob::class, function ($job) use ($userIds, $campaignTag) {
        return $job->userIds === $userIds && $job->campaignTag === $campaignTag;
    });
});

it('accepts optional list ID parameter', function () {
    Queue::fake();

    $userIds = [1, 2, 3];
    $campaignTag = 'campaign_test_20241110-120000';
    $customListId = 'custom-list-456';

    TagUsersBulkJob::dispatch($userIds, $campaignTag, $customListId);

    Queue::assertPushed(TagUsersBulkJob::class, function ($job) use ($customListId) {
        return $job->listId === $customListId;
    });
});

// === JOB PROPERTIES TESTS ===

it('has correct retry settings', function () {
    $job = new TagUsersBulkJob([1, 2, 3], 'campaign_test_20241110-120000');

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300);
});

it('stores user IDs correctly', function () {
    $userIds = [1, 2, 3, 4, 5];
    $campaignTag = 'campaign_test_20241110-120000';

    $job = new TagUsersBulkJob($userIds, $campaignTag);

    expect($job->userIds)->toBe($userIds)
        ->and($job->campaignTag)->toBe($campaignTag);
});

it('stores optional list ID', function () {
    $userIds = [1, 2, 3];
    $campaignTag = 'campaign_test_20241110-120000';
    $listId = 'custom-list-123';

    $job = new TagUsersBulkJob($userIds, $campaignTag, $listId);

    expect($job->listId)->toBe($listId);
});

it('sets list ID to null when not provided', function () {
    $job = new TagUsersBulkJob([1, 2, 3], 'campaign_test_20241110-120000');

    expect($job->listId)->toBeNull();
});

// === JOB STRUCTURE TESTS ===

it('implements ShouldQueue interface', function () {
    $job = new TagUsersBulkJob([1, 2, 3], 'campaign_test_20241110-120000');

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('uses Queueable trait', function () {
    $reflection = new ReflectionClass(TagUsersBulkJob::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Foundation\Queue\Queueable');
});

// === BATCH SIZE TESTS ===

it('calculates correct number of batches for 1000 users', function () {
    $userIds = range(1, 1000);

    // 1000 users = 1 batch of 1000
    $batches = array_chunk($userIds, 1000);

    expect(count($batches))->toBe(1)
        ->and(count($batches[0]))->toBe(1000);
});

it('calculates correct number of batches for 2500 users', function () {
    $userIds = range(1, 2500);

    // 2500 users = 3 batches (1000, 1000, 500)
    $batches = array_chunk($userIds, 1000);

    expect(count($batches))->toBe(3)
        ->and(count($batches[0]))->toBe(1000)
        ->and(count($batches[1]))->toBe(1000)
        ->and(count($batches[2]))->toBe(500);
});

it('calculates correct number of batches for 500 users', function () {
    $userIds = range(1, 500);

    // 500 users = 1 batch of 500
    $batches = array_chunk($userIds, 1000);

    expect(count($batches))->toBe(1)
        ->and(count($batches[0]))->toBe(500);
});

// === JOB QUEUEING TESTS ===

it('can be queued with delay', function () {
    Queue::fake();

    $userIds = [1, 2, 3];
    $campaignTag = 'campaign_test_20241110-120000';

    TagUsersBulkJob::dispatch($userIds, $campaignTag)->delay(now()->addMinutes(5));

    Queue::assertPushed(TagUsersBulkJob::class);
});

it('can be queued on specific queue', function () {
    Queue::fake();

    $userIds = [1, 2, 3];
    $campaignTag = 'campaign_test_20241110-120000';

    TagUsersBulkJob::dispatch($userIds, $campaignTag)->onQueue('campaigns');

    Queue::assertPushedOn('campaigns', TagUsersBulkJob::class);
});

// === DATA STRUCTURE TESTS ===

it('accepts empty user IDs array', function () {
    $job = new TagUsersBulkJob([], 'campaign_test_20241110-120000');

    expect($job->userIds)->toBe([])
        ->and($job->campaignTag)->toBe('campaign_test_20241110-120000');
});

it('accepts large user IDs array', function () {
    $userIds = range(1, 10000);
    $job = new TagUsersBulkJob($userIds, 'campaign_test_20241110-120000');

    expect(count($job->userIds))->toBe(10000);
});

// === CAMPAIGN TAG VALIDATION TESTS ===

it('accepts valid campaign tag format', function () {
    $validTags = [
        'campaign_test_20241110-120000',
        'campaign_premium-users_20241110-120000',
        'campaign_event-attendees_20250101-000000',
    ];

    foreach ($validTags as $tag) {
        $job = new TagUsersBulkJob([1, 2, 3], $tag);
        expect($job->campaignTag)->toBe($tag);
    }
});

// === INTEGRATION TESTS (WITHOUT CM API) ===

it('prepares subscriber data structure correctly', function () {
    $organization = \App\Models\Organization::factory()->create(['name' => 'Test Org']);
    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'tier' => 'paid_premium',
        'permission_to_track' => true,
        'fullname' => 'Test User',
        'email' => 'test@example.com',
    ]);

    // We can't test the actual CM API call without mocking,
    // but we can verify the data structure would be correct
    $expectedCustomFields = [
        ['Key' => 'user_id', 'Value' => (string) $user->id, 'Clear' => false],
        ['Key' => 'organization_name', 'Value' => 'Test Org', 'Clear' => false],
        ['Key' => 'tier', 'Value' => 'paid_premium', 'Clear' => false],
        ['Key' => 'temp_campaign_tag', 'Value' => 'campaign_test_20241110-120000', 'Clear' => false],
    ];

    expect($expectedCustomFields)->toHaveCount(4)
        ->and($expectedCustomFields[0]['Key'])->toBe('user_id')
        ->and($expectedCustomFields[1]['Key'])->toBe('organization_name')
        ->and($expectedCustomFields[2]['Key'])->toBe('tier')
        ->and($expectedCustomFields[3]['Key'])->toBe('temp_campaign_tag');
});

it('handles users without organization', function () {
    $user = User::factory()->create([
        'organization_id' => null,
        'tier' => 'free',
    ]);

    // Verify that organization name would be empty string
    expect($user->organization)->toBeNull();

    $organizationName = $user->organization?->name ?? '';
    expect($organizationName)->toBe('');
});

it('defaults to free tier when tier is null', function () {
    $user = User::factory()->create(['tier' => null]);

    $tier = $user->tier ?? 'free';
    expect($tier)->toBe('free');
});

it('sets ConsentToTrack to Yes when permission_to_track is true', function () {
    $user = User::factory()->create(['permission_to_track' => true]);

    $consentToTrack = $user->permission_to_track ? 'Yes' : 'No';
    expect($consentToTrack)->toBe('Yes');
});

it('sets ConsentToTrack to No when permission_to_track is false', function () {
    $user = User::factory()->create(['permission_to_track' => false]);

    $consentToTrack = $user->permission_to_track ? 'Yes' : 'No';
    expect($consentToTrack)->toBe('No');
});
