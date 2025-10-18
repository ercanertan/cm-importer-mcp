<?php

use App\Jobs\SyncDomainOrganizationsJob;
use App\Jobs\SyncOrganizationDomainsJob;
use App\Jobs\SyncSingleDomainJob;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Domain-Organization Integration', function () {
    beforeEach(function () {
        $this->defaultOrg = Organization::factory()->create(['name' => 'Default Organization']);
        $this->org1 = Organization::factory()->create(['name' => 'Org 1']);
        $this->org2 = Organization::factory()->create(['name' => 'Org 2']);
    });

    it('properly syncs bidirectional relationships', function () {
        $domain = Domain::create(['domain' => 'example.com']);
        $syncLog = SyncLog::factory()->create();

        // Sync domain to organizations
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $domain->id,
            [$this->org1->id, $this->org2->id]
        );
        $job->handle();

        // Check both sides of relationship
        expect($domain->fresh()->organizations)->toHaveCount(2);
        expect($this->org1->fresh()->domains->pluck('id'))->toContain($domain->id);
        expect($this->org2->fresh()->domains->pluck('id'))->toContain($domain->id);
    });

    it('properly removes domains when syncing organization', function () {
        $domain1 = Domain::create(['domain' => 'keep.com']);
        $domain2 = Domain::create(['domain' => 'remove.com']);

        $this->org1->domains()->attach([$domain1->id, $domain2->id]);

        $syncLog = SyncLog::factory()->create();

        // Sync to only keep domain1
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->org1->id,
            ['keep.com'],
            false
        );
        $job->handle();

        // domain2 should be removed
        expect($this->org1->fresh()->domains->pluck('domain')->toArray())->toBe(['keep.com']);
        expect($domain2->fresh()->organizations->pluck('id'))->not->toContain($this->org1->id);
    });

    it('handles complex many-to-many scenarios', function () {
        $domain = Domain::create(['domain' => 'example.com']);

        // Create users
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        $user2 = User::factory()->create(['email' => 'user2@example.com']);

        $syncLog = SyncLog::factory()->create();

        // Sync domain to multiple organizations
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $domain->id,
            [$this->org1->id, $this->org2->id]
        );
        $job->handle();

        // Both users should be in both organizations
        $user1OrgIds = $user1->fresh()->organizations->pluck('id')->toArray();
        $user2OrgIds = $user2->fresh()->organizations->pluck('id')->toArray();

        expect($user1OrgIds)->toContain($this->org1->id);
        expect($user1OrgIds)->toContain($this->org2->id);
        expect($user2OrgIds)->toContain($this->org1->id);
        expect($user2OrgIds)->toContain($this->org2->id);
    });

    it('prevents duplicate pivot table entries across multiple syncs', function () {
        $domain = Domain::create(['domain' => 'example.com']);
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Sync 3 times
        for ($i = 0; $i < 3; $i++) {
            $syncLog = SyncLog::factory()->create();
            $job = new SyncDomainOrganizationsJob(
                $syncLog->id,
                $domain->id,
                [$this->org1->id]
            );
            $job->handle();
        }

        // Should only have ONE pivot entry
        $pivotCount = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('organization_id', $this->org1->id)
            ->count();

        expect($pivotCount)->toBe(1);
    });

    it('handles default organization removal correctly', function () {
        $domain = Domain::create(['domain' => 'example.com']);
        $domain->organizations()->attach($this->defaultOrg->id);

        $syncLog = SyncLog::factory()->create();

        // Assign to specific organization (should remove default)
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->org1->id,
            ['example.com'],
            false
        );
        $job->handle();

        $domainOrgIds = $domain->fresh()->organizations->pluck('id')->toArray();
        expect($domainOrgIds)->not->toContain($this->defaultOrg->id);
        expect($domainOrgIds)->toContain($this->org1->id);
    });

    it('handles user migration during domain sync', function () {
        $domain = Domain::create(['domain' => 'example.com']);

        // Create users with different organizations
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'organization_id' => $this->defaultOrg->id,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'organization_id' => null,
        ]);

        $syncLog = SyncLog::factory()->create();
        $job = new SyncSingleDomainJob($syncLog->id, $domain->id);

        $domain->organizations()->attach($this->org1->id);
        $job->handle();

        // Both users should have domain_id set
        expect($user1->fresh()->domain_id)->toBe($domain->id);
        expect($user2->fresh()->domain_id)->toBe($domain->id);
    });

    it('maintains data integrity across concurrent syncs', function () {
        $domain = Domain::create(['domain' => 'example.com']);

        User::factory()->count(100)->create([
            'email' => fn() => fake()->userName() . '@example.com',
        ]);

        // Simulate concurrent syncs
        $jobs = [];
        for ($i = 0; $i < 3; $i++) {
            $syncLog = SyncLog::factory()->create();
            $jobs[] = new SyncDomainOrganizationsJob(
                $syncLog->id,
                $domain->id,
                [$this->org1->id]
            );
        }

        foreach ($jobs as $job) {
            $job->handle();
        }

        // All users should have exactly one pivot entry
        $users = User::where('email', 'like', '%@example.com')->get();
        foreach ($users as $user) {
            $pivotCount = DB::table('organization_user')
                ->where('user_id', $user->id)
                ->where('organization_id', $this->org1->id)
                ->count();

            expect($pivotCount)->toBe(1);
        }
    });

    it('handles edge case of empty domain list', function () {
        $domain1 = Domain::create(['domain' => 'domain1.com']);
        $domain2 = Domain::create(['domain' => 'domain2.com']);

        $this->org1->domains()->attach([$domain1->id, $domain2->id]);

        $syncLog = SyncLog::factory()->create();

        // Sync to empty array (remove all domains)
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->org1->id,
            [],
            false
        );
        $job->handle();

        expect($this->org1->fresh()->domains)->toHaveCount(0);
    });

    it('preserves users when removing domain from organization', function () {
        $domain = Domain::create(['domain' => 'example.com']);
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'domain_id' => $domain->id,
        ]);

        $this->org1->domains()->attach($domain->id);

        $syncLog = SyncLog::factory()->create();

        // Remove domain from organization
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->org1->id,
            [],
            false
        );
        $job->handle();

        // User should still exist with domain_id
        expect($user->fresh()->domain_id)->toBe($domain->id);
        expect(User::count())->toBe(1);
    });

    it('handles large scale operations efficiently', function () {
        $domains = [];
        for ($i = 0; $i < 10; $i++) {
            $domains[] = "domain{$i}.com";
        }

        $syncLog = SyncLog::factory()->create();

        $startTime = microtime(true);
        $job = new SyncOrganizationDomainsJob(
            $syncLog->id,
            $this->org1->id,
            $domains,
            false
        );
        $job->handle();
        $endTime = microtime(true);

        // Should create 10 domains quickly
        expect(Domain::count())->toBe(10);
        expect($endTime - $startTime)->toBeLessThan(3.0);
        expect($this->org1->fresh()->domains)->toHaveCount(10);
    });
});
