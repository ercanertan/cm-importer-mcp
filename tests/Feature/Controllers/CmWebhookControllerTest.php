<?php

use App\Models\EmailEngagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'test@example.com',
        'cm_status' => 'active',
    ]);
});

// === OPEN WEBHOOK TESTS ===

it('handles email open webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'CampaignName' => 'January Newsletter',
        'IPAddress' => '192.168.1.1',
        'UserAgent' => 'Mozilla/5.0',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.open'), $webhookData);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    // Check EmailEngagement was created
    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement)->not->toBeNull()
        ->and($engagement->event_type)->toBe('open')
        ->and($engagement->campaign_id)->toBe('campaign-123')
        ->and($engagement->ip_address)->toBe('192.168.1.1');

    // Check user engagement metrics updated
    $this->user->refresh();
    expect($this->user->total_opens)->toBe(1)
        ->and($this->user->last_email_opened_at)->not->toBeNull()
        ->and($this->user->last_activity_at)->not->toBeNull();
});

it('finds user by user_id custom field for open webhook', function () {
    $webhookData = [
        'EmailAddress' => 'wrong@example.com', // Wrong email
        'Date' => '2024-01-15T10:30:00',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.open'), $webhookData);

    $response->assertStatus(200);

    // Should still find user by custom field
    expect(EmailEngagement::where('user_id', $this->user->id)->count())->toBe(1);
});

it('returns 404 when user not found for open webhook', function () {
    $webhookData = [
        'EmailAddress' => 'nonexistent@example.com',
        'Date' => '2024-01-15T10:30:00',
    ];

    $response = $this->postJson(route('webhooks.cm.open'), $webhookData);

    $response->assertStatus(404)
        ->assertJson(['error' => 'User not found']);
});

it('validates required fields for open webhook', function () {
    $response = $this->postJson(route('webhooks.cm.open'), []);

    $response->assertStatus(400)
        ->assertJson(['error' => 'Invalid webhook data']);
});

// === CLICK WEBHOOK TESTS ===

it('handles email click webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'URL' => 'https://example.com/article',
        'IPAddress' => '192.168.1.1',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.click'), $webhookData);

    $response->assertStatus(200);

    // Check EmailEngagement was created
    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement)->not->toBeNull()
        ->and($engagement->event_type)->toBe('click')
        ->and($engagement->url)->toBe('https://example.com/article');

    // Check user engagement metrics updated
    $this->user->refresh();
    expect($this->user->total_clicks)->toBe(1)
        ->and($this->user->last_email_clicked_at)->not->toBeNull();
});

// === BOUNCE WEBHOOK TESTS ===

it('handles hard bounce webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'Type' => 'Hard',
        'Reason' => 'Mailbox does not exist',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.bounce'), $webhookData);

    $response->assertStatus(200);

    // Check EmailEngagement was created
    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement)->not->toBeNull()
        ->and($engagement->event_type)->toBe('bounce')
        ->and($engagement->bounce_type)->toBe('hard')
        ->and($engagement->bounce_reason)->toBe('Mailbox does not exist');

    // Check user status updated to bounced
    $this->user->refresh();
    expect($this->user->total_bounces)->toBe(1)
        ->and($this->user->cm_status)->toBe('bounced');
});

it('handles soft bounce webhook without changing status', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'Type' => 'Soft',
        'Reason' => 'Mailbox full',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.bounce'), $webhookData);

    $response->assertStatus(200);

    // Check user status remains active for soft bounce
    $this->user->refresh();
    expect($this->user->total_bounces)->toBe(1)
        ->and($this->user->cm_status)->toBe('active');
});

// === UNSUBSCRIBE WEBHOOK TESTS ===

it('handles unsubscribe webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.unsubscribe'), $webhookData);

    $response->assertStatus(200);

    // Check EmailEngagement was created
    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement)->not->toBeNull()
        ->and($engagement->event_type)->toBe('unsubscribe');

    // Check user status updated
    $this->user->refresh();
    expect($this->user->cm_status)->toBe('unsubscribed')
        ->and($this->user->cm_unsubscribed_at)->not->toBeNull();
});

// === SPAM COMPLAINT WEBHOOK TESTS ===

it('handles spam complaint webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.spam-complaint'), $webhookData);

    $response->assertStatus(200);

    // Check user status updated
    $this->user->refresh();
    expect($this->user->cm_status)->toBe('spam_complaint');
});

// === UPDATE WEBHOOK TESTS ===

it('handles subscriber update webhook successfully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'State' => 'Active',
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.update'), $webhookData);

    $response->assertStatus(200);

    // Check user status updated
    $this->user->refresh();
    expect($this->user->cm_status)->toBe('active');
});

// === DEACTIVATE WEBHOOK TESTS ===

it('handles deactivate webhook with bounce event', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'Events' => [
            [
                'Event' => 'Bounce',
                'Type' => 'Hard',
                'Reason' => 'Domain does not exist',
                'Date' => '2024-01-15T10:30:00',
            ],
        ],
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.deactivate'), $webhookData);

    $response->assertStatus(200);

    // Check engagement created
    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement)->not->toBeNull()
        ->and($engagement->event_type)->toBe('bounce')
        ->and($engagement->bounce_type)->toBe('hard');

    // Check user status updated
    $this->user->refresh();
    expect($this->user->cm_status)->toBe('bounced');
});

it('handles deactivate webhook with unsubscribe event', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'Events' => [
            [
                'Event' => 'Unsubscribe',
                'Date' => '2024-01-15T10:30:00',
            ],
        ],
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.deactivate'), $webhookData);

    $response->assertStatus(200);

    // Check user status updated
    $this->user->refresh();
    expect($this->user->cm_status)->toBe('unsubscribed');
});

it('handles deactivate webhook with multiple events', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'Events' => [
            [
                'Event' => 'Bounce',
                'Type' => 'Soft',
                'Reason' => 'Mailbox full',
                'Date' => '2024-01-15T10:30:00',
            ],
            [
                'Event' => 'Bounce',
                'Type' => 'Hard',
                'Reason' => 'Domain does not exist',
                'Date' => '2024-01-15T10:31:00',
            ],
        ],
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $response = $this->postJson(route('webhooks.cm.deactivate'), $webhookData);

    $response->assertStatus(200);

    // Check multiple engagements created
    expect(EmailEngagement::where('user_id', $this->user->id)->count())->toBe(2);
});

// === EDGE CASES ===

it('handles webhook without custom fields gracefully', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        // No CustomFields
    ];

    $response = $this->postJson(route('webhooks.cm.open'), $webhookData);

    $response->assertStatus(200);

    // Should find user by email
    expect(EmailEngagement::where('user_id', $this->user->id)->count())->toBe(1);
});

it('handles webhook with empty custom fields array', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CustomFields' => [],
    ];

    $response = $this->postJson(route('webhooks.cm.open'), $webhookData);

    $response->assertStatus(200);

    // Should find user by email
    expect(EmailEngagement::where('user_id', $this->user->id)->count())->toBe(1);
});

it('increments engagement metrics correctly for multiple events', function () {
    // Send 3 open events
    for ($i = 0; $i < 3; $i++) {
        $this->postJson(route('webhooks.cm.open'), [
            'EmailAddress' => $this->user->email,
            'Date' => now()->toIso8601String(),
            'CustomFields' => [['Key' => 'user_id', 'Value' => (string)$this->user->id]],
        ]);
    }

    // Send 2 click events
    for ($i = 0; $i < 2; $i++) {
        $this->postJson(route('webhooks.cm.click'), [
            'EmailAddress' => $this->user->email,
            'Date' => now()->toIso8601String(),
            'CustomFields' => [['Key' => 'user_id', 'Value' => (string)$this->user->id]],
        ]);
    }

    $this->user->refresh();
    expect($this->user->total_opens)->toBe(3)
        ->and($this->user->total_clicks)->toBe(2);
});

it('stores full webhook payload in event_data', function () {
    $webhookData = [
        'EmailAddress' => $this->user->email,
        'Date' => '2024-01-15T10:30:00',
        'CampaignID' => 'campaign-123',
        'CustomData' => ['foo' => 'bar'],
        'CustomFields' => [
            ['Key' => 'user_id', 'Value' => (string)$this->user->id],
        ],
    ];

    $this->postJson(route('webhooks.cm.open'), $webhookData);

    $engagement = EmailEngagement::where('user_id', $this->user->id)->first();
    expect($engagement->event_data)->toBeArray()
        ->and($engagement->event_data['CustomData']['foo'])->toBe('bar');
});
