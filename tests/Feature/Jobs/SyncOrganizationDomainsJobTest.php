<?php

use App\Jobs\SyncOrganizationDomainsJob;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SyncOrganizationDomainsJob', function () {
    beforeEach(function () {
        $this->organization = Organization::factory()->create(['name' => 'Test Org']);
        $this->defaultOrganization = Organization::factory()->create(['name' => 'Default Organization']);
    });

    it('creates domains if they do not exist', function () {
        $syncLog = SyncLog::factory()->create();

        expect(Domain::count())->toBe(0);

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com', 'test.com'],
            false
        );

        $job->handle();

        expect(Domain::count())->toBe(2);
        expect(Domain::where('domain', 'example.com')->exists())->toBeTrue();
        expect(Domain::where('domain', 'test.com')->exists())->toBeTrue();
    });

    it('associates domains with organization', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com', 'test.com'],
            false
        );

        $job->handle();

        $domainNames = $this->organization->fresh()->domains->pluck('domain')->toArray();
        expect($domainNames)->toContain('example.com');
        expect($domainNames)->toContain('test.com');
    });

    it('removes domains not in the list (CRITICAL BUG FIX)', function () {
        // Create domains and associate them
        $domain1 = Domain::create(['domain' => 'keep.com']);
        $domain2 = Domain::create(['domain' => 'remove.com']);
        $domain3 = Domain::create(['domain' => 'also-remove.com']);

        $this->organization->domains()->attach([$domain1->id, $domain2->id, $domain3->id]);

        expect($this->organization->domains)->toHaveCount(3);

        $syncLog = SyncLog::factory()->create();

        // Sync to only keep.com (should remove the other two)
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['keep.com'],
            false
        );

        $job->handle();

        $domainNames = $this->organization->fresh()->domains->pluck('domain')->toArray();
        expect($domainNames)->toHaveCount(1);
        expect($domainNames)->toContain('keep.com');
        expect($domainNames)->not->toContain('remove.com');
        expect($domainNames)->not->toContain('also-remove.com');
    });

    it('syncs users when syncUsers is true', function () {
        $syncLog = SyncLog::factory()->create();

        User::factory()->count(10)->create([
            'email' => fn() => fake()->userName() . '@example.com',
        ]);

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com'],
            true // syncUsers = true
        );

        $job->handle();

        $userCount = User::whereHas('organizations', function ($query) {
            $query->where('organizations.id', $this->organization->id);
        })->count();

        expect($userCount)->toBeGreaterThan(0);
    });

    it('does not sync users when syncUsers is false', function () {
        $syncLog = SyncLog::factory()->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => null,
        ]);

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com'],
            false // syncUsers = false
        );

        $job->handle();

        // User should NOT be automatically assigned
        expect($user->fresh()->organization_id)->toBeNull();
    });

    it('handles comma-separated and trimmed domain names', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            [' example.com ', '  test.com', 'another.org  '],
            false
        );

        $job->handle();

        expect(Domain::where('domain', 'example.com')->exists())->toBeTrue();
        expect(Domain::where('domain', 'test.com')->exists())->toBeTrue();
        expect(Domain::where('domain', 'another.org')->exists())->toBeTrue();
        expect(Domain::where('domain', ' example.com ')->exists())->toBeFalse();
    });

    it('lowercases all domain names', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['EXAMPLE.COM', 'Test.Com'],
            false
        );

        $job->handle();

        expect(Domain::where('domain', 'example.com')->exists())->toBeTrue();
        expect(Domain::where('domain', 'test.com')->exists())->toBeTrue();
        expect(Domain::where('domain', 'EXAMPLE.COM')->exists())->toBeFalse();
    });

    it('updates sync log with completion status', function () {
        $syncLog = SyncLog::factory()->create(['status' => 'pending']);

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com'],
            false
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(1);
        expect($syncLog->successful_items)->toBe(1);
        expect($syncLog->failed_items)->toBe(0);
    });

    it('stores metadata about synced domains', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com', 'test.com'],
            true
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->metadata)->toHaveKey('organization_id');
        expect($syncLog->metadata)->toHaveKey('organization_name');
        expect($syncLog->metadata)->toHaveKey('sync_users_enabled');
        expect($syncLog->metadata['sync_users_enabled'])->toBeTrue();
    });

    it('marks sync as failed on error', function () {
        $syncLog = SyncLog::factory()->create();

        // Use invalid organization ID
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            99999,
            ['example.com'],
            false
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('failed');
        expect($syncLog->error_message)->toContain('not found');
    });

    it('handles empty domain list', function () {
        // Create some existing domains
        $domain1 = Domain::create(['domain' => 'remove1.com']);
        $domain2 = Domain::create(['domain' => 'remove2.com']);
        $this->organization->domains()->attach([$domain1->id, $domain2->id]);

        $syncLog = SyncLog::factory()->create();

        // Empty array should remove all domains
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            [],
            false
        );

        $job->handle();

        expect($this->organization->fresh()->domains)->toHaveCount(0);
    });

    it('handles multiple domains for same organization', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['domain1.com', 'domain2.com', 'domain3.com', 'domain4.com', 'domain5.com'],
            false
        );

        $job->handle();

        expect($this->organization->fresh()->domains)->toHaveCount(5);
    });

    it('does not duplicate domain creation', function () {
        // Pre-create a domain
        $existingDomain = Domain::create(['domain' => 'example.com']);

        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com', 'test.com'],
            false
        );

        $job->handle();

        // Should only have 2 total domains
        expect(Domain::count())->toBe(2);
        expect(Domain::where('domain', 'example.com')->count())->toBe(1);
    });

    it('removes default organization when assigning to specific org', function () {
        $domain = Domain::create(['domain' => 'example.com']);
        $domain->organizations()->attach($this->defaultOrganization->id);

        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['example.com'],
            false
        );

        $job->handle();

        $domainOrgIds = $domain->fresh()->organizations->pluck('id')->toArray();
        expect($domainOrgIds)->not->toContain($this->defaultOrganization->id);
    });

    it('has 1 hour timeout', function () {
        $job = new SyncOrganizationDomainsJob(1, 1, [], false);
        expect($job->timeout)->toBe(3600);
    });

    it('tracks progress for each domain', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->organization->id,
            ['domain1.com', 'domain2.com', 'domain3.com'],
            false
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->processed_items)->toBe(3);
    });
});
