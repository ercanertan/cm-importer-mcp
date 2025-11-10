<?php

use App\Models\EmailEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can create an email engagement record', function () {
    $engagement = EmailEngagement::create([
        'user_id' => $this->user->id,
        'campaign_id' => 'cm-campaign-123',
        'campaign_name' => 'October Newsletter',
        'event_type' => 'open',
        'occurred_at' => now(),
    ]);

    expect($engagement)->toBeInstanceOf(EmailEngagement::class)
        ->and($engagement->user_id)->toBe($this->user->id)
        ->and($engagement->campaign_id)->toBe('cm-campaign-123')
        ->and($engagement->event_type)->toBe('open');
});

it('belongs to a user', function () {
    $engagement = EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
    ]);

    expect($engagement->user)->toBeInstanceOf(User::class)
        ->and($engagement->user->id)->toBe($this->user->id);
});

it('casts event_data to array', function () {
    $eventData = ['ip' => '192.168.1.1', 'location' => 'US'];

    $engagement = EmailEngagement::create([
        'user_id' => $this->user->id,
        'event_type' => 'click',
        'event_data' => $eventData,
        'occurred_at' => now(),
    ]);

    expect($engagement->event_data)->toBeArray()
        ->and($engagement->event_data)->toBe($eventData);
});

it('casts occurred_at to datetime', function () {
    $engagement = EmailEngagement::create([
        'user_id' => $this->user->id,
        'event_type' => 'open',
        'occurred_at' => '2024-01-15 10:30:00',
    ]);

    expect($engagement->occurred_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// Test scopes
it('filters by event type using ofType scope', function () {
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'bounce']);

    $opens = EmailEngagement::ofType('open')->get();

    expect($opens)->toHaveCount(1)
        ->and($opens->first()->event_type)->toBe('open');
});

it('filters opens using opens scope', function () {
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click']);

    $opens = EmailEngagement::opens()->get();

    expect($opens)->toHaveCount(2)
        ->and($opens->every(fn($e) => $e->event_type === 'open'))->toBeTrue();
});

it('filters clicks using clicks scope', function () {
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click', 'url' => 'https://example.com/link1']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click', 'url' => 'https://example.com/link2']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    $clicks = EmailEngagement::clicks()->get();

    expect($clicks)->toHaveCount(2)
        ->and($clicks->every(fn($e) => $e->event_type === 'click'))->toBeTrue();
});

it('filters bounces using bounces scope', function () {
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'bounce', 'bounce_type' => 'hard']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'bounce', 'bounce_type' => 'soft']);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    $bounces = EmailEngagement::bounces()->get();

    expect($bounces)->toHaveCount(2)
        ->and($bounces->every(fn($e) => $e->event_type === 'bounce'))->toBeTrue();
});

it('filters by campaign using forCampaign scope', function () {
    $campaignId = 'cm-campaign-123';

    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'campaign_id' => $campaignId]);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'campaign_id' => $campaignId]);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'campaign_id' => 'other-campaign']);

    $campaignEngagements = EmailEngagement::forCampaign($campaignId)->get();

    expect($campaignEngagements)->toHaveCount(2)
        ->and($campaignEngagements->every(fn($e) => $e->campaign_id === $campaignId))->toBeTrue();
});

it('filters recent engagements using recent scope', function () {
    // Create engagement from 25 days ago (should be included)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'occurred_at' => now()->subDays(25),
    ]);

    // Create engagement from 35 days ago (should be excluded)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'occurred_at' => now()->subDays(35),
    ]);

    $recentEngagements = EmailEngagement::recent(30)->get();

    expect($recentEngagements)->toHaveCount(1);
});

it('can customize days for recent scope', function () {
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'occurred_at' => now()->subDays(5)]);
    EmailEngagement::factory()->create(['user_id' => $this->user->id, 'occurred_at' => now()->subDays(8)]);

    $recentEngagements = EmailEngagement::recent(7)->get();

    expect($recentEngagements)->toHaveCount(1);
});

// Test helper methods
it('identifies open events with isOpen method', function () {
    $open = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);
    $click = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click']);

    expect($open->isOpen())->toBeTrue()
        ->and($click->isOpen())->toBeFalse();
});

it('identifies click events with isClick method', function () {
    $click = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'click']);
    $open = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    expect($click->isClick())->toBeTrue()
        ->and($open->isClick())->toBeFalse();
});

it('identifies bounce events with isBounce method', function () {
    $bounce = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'bounce']);
    $open = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    expect($bounce->isBounce())->toBeTrue()
        ->and($open->isBounce())->toBeFalse();
});

it('identifies hard bounce events with isHardBounce method', function () {
    $hardBounce = EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'bounce_type' => 'hard',
    ]);

    $softBounce = EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'bounce_type' => 'soft',
    ]);

    $open = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    expect($hardBounce->isHardBounce())->toBeTrue()
        ->and($softBounce->isHardBounce())->toBeFalse()
        ->and($open->isHardBounce())->toBeFalse();
});

it('identifies soft bounce events with isSoftBounce method', function () {
    $softBounce = EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'bounce_type' => 'soft',
    ]);

    $hardBounce = EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'bounce_type' => 'hard',
    ]);

    $open = EmailEngagement::factory()->create(['user_id' => $this->user->id, 'event_type' => 'open']);

    expect($softBounce->isSoftBounce())->toBeTrue()
        ->and($hardBounce->isSoftBounce())->toBeFalse()
        ->and($open->isSoftBounce())->toBeFalse();
});

it('stores click-specific data', function () {
    $engagement = EmailEngagement::create([
        'user_id' => $this->user->id,
        'event_type' => 'click',
        'url' => 'https://example.com/article/123',
        'ip_address' => '192.168.1.100',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'occurred_at' => now(),
    ]);

    expect($engagement->url)->toBe('https://example.com/article/123')
        ->and($engagement->ip_address)->toBe('192.168.1.100')
        ->and($engagement->user_agent)->toContain('Mozilla');
});

it('stores bounce-specific data', function () {
    $engagement = EmailEngagement::create([
        'user_id' => $this->user->id,
        'event_type' => 'bounce',
        'bounce_type' => 'hard',
        'bounce_reason' => 'Mailbox does not exist',
        'occurred_at' => now(),
    ]);

    expect($engagement->bounce_type)->toBe('hard')
        ->and($engagement->bounce_reason)->toBe('Mailbox does not exist');
});

it('can chain multiple scopes', function () {
    $campaignId = 'cm-campaign-123';

    // Create opens for the campaign (recent)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'campaign_id' => $campaignId,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    // Create clicks for the campaign (recent)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'campaign_id' => $campaignId,
        'event_type' => 'click',
        'occurred_at' => now()->subDays(5),
    ]);

    // Create opens for the campaign (old)
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'campaign_id' => $campaignId,
        'event_type' => 'open',
        'occurred_at' => now()->subDays(40),
    ]);

    // Create opens for different campaign
    EmailEngagement::factory()->create([
        'user_id' => $this->user->id,
        'campaign_id' => 'other-campaign',
        'event_type' => 'open',
        'occurred_at' => now()->subDays(5),
    ]);

    $result = EmailEngagement::forCampaign($campaignId)
        ->opens()
        ->recent(30)
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->campaign_id)->toBe($campaignId)
        ->and($result->first()->event_type)->toBe('open');
});

it('cascades delete when user is deleted', function () {
    $engagement = EmailEngagement::factory()->create(['user_id' => $this->user->id]);

    expect(EmailEngagement::count())->toBe(1);

    $this->user->delete();

    expect(EmailEngagement::count())->toBe(0);
});
