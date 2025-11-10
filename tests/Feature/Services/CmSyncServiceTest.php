<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\CmSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock CM API configuration
    config([
        'campaign-monitor.api_key' => 'test-api-key',
        'campaign-monitor.list_id' => 'test-list-id',
    ]);

    $this->service = new CmSyncService();
});

it('can build custom fields for a user', function () {
    $user = User::factory()->create([
        'id' => 123,
        'email' => 'test@example.com',
        'fullname' => 'Test User',
        'tier' => 'paid_pro',
    ]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('buildCustomFields');
    $method->setAccessible(true);

    $fields = $method->invoke($this->service, $user, ['user_id', 'tier']);

    expect($fields)->toHaveCount(2)
        ->and($fields[0])->toMatchArray(['Key' => 'user_id', 'Value' => '123'])
        ->and($fields[1])->toMatchArray(['Key' => 'tier', 'Value' => 'paid_pro']);
});

it('gets user_id field value correctly', function () {
    $user = User::factory()->create(['id' => 456]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getFieldValue');
    $method->setAccessible(true);

    $value = $method->invoke($this->service, $user, 'user_id');

    expect($value)->toBe('456');
});

it('gets tier field value correctly', function () {
    $user = User::factory()->create(['tier' => 'paid_premium']);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getFieldValue');
    $method->setAccessible(true);

    $value = $method->invoke($this->service, $user, 'tier');

    expect($value)->toBe('paid_premium');
});

it('gets organization name from primary organization', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create(['name' => 'Primary Org']);
    $org2 = Organization::factory()->create(['name' => 'Secondary Org']);

    $user->organizations()->attach($org1->id, ['is_primary' => true]);
    $user->organizations()->attach($org2->id, ['is_primary' => false]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getOrganizationName');
    $method->setAccessible(true);

    $orgName = $method->invoke($this->service, $user->fresh());

    expect($orgName)->toBe('Primary Org');
});

it('gets organization name from first organization if no primary', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create(['name' => 'First Org']);

    $user->organizations()->attach($org->id, ['is_primary' => false]);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getOrganizationName');
    $method->setAccessible(true);

    $orgName = $method->invoke($this->service, $user->fresh());

    expect($orgName)->toBe('First Org');
});

it('returns empty string when user has no organizations', function () {
    $user = User::factory()->create();

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getOrganizationName');
    $method->setAccessible(true);

    $orgName = $method->invoke($this->service, $user);

    expect($orgName)->toBe('');
});

it('returns false when CM is not configured', function () {
    config([
        'campaign-monitor.api_key' => null,
        'campaign-monitor.list_id' => null,
    ]);

    $service = new CmSyncService();
    $user = User::factory()->create();

    $result = $service->syncUser($user);

    expect($result)->toBeFalse();
});

it('checks if CM is properly configured', function () {
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('isConfigured');
    $method->setAccessible(true);

    // With config
    $isConfigured = $method->invoke($this->service);
    expect($isConfigured)->toBeTrue();

    // Without config
    config(['campaign-monitor.api_key' => null]);
    $service = new CmSyncService();
    $isConfigured = $method->invoke($service);
    expect($isConfigured)->toBeFalse();
});

it('handles temp_campaign_tag field correctly', function () {
    $user = User::factory()->create();

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getFieldValue');
    $method->setAccessible(true);

    $value = $method->invoke($this->service, $user, 'temp_campaign_tag');

    expect($value)->toBe('');
});

it('returns null for unknown field keys', function () {
    $user = User::factory()->create();

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('getFieldValue');
    $method->setAccessible(true);

    $value = $method->invoke($this->service, $user, 'unknown_field');

    expect($value)->toBeNull();
});
