<?php

use App\Jobs\SyncDomainOrganizationsJob;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('SyncDomainOrganizationsJob', function () {
    beforeEach(function () {
        $this->domain = Domain::create(['domain' => 'example.com']);
        $this->organization = Organization::factory()->create(['name' => 'Test Org']);
    });

    it('syncs organizations to domain', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        expect($this->domain->fresh()->organizations)->toHaveCount(1);
        expect($this->domain->fresh()->organizations->first()->id)->toBe($this->organization->id);
    });

    it('removes organizations not in the list', function () {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $org3 = Organization::factory()->create();

        // Initially attach all 3
        $this->domain->organizations()->attach([$org1->id, $org2->id, $org3->id]);

        $syncLog = SyncLog::factory()->create();

        // Sync to only org1 and org2 (should remove org3)
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$org1->id, $org2->id]
        );

        $job->handle();

        $domainOrgIds = $this->domain->fresh()->organizations->pluck('id')->toArray();
        expect($domainOrgIds)->toContain($org1->id);
        expect($domainOrgIds)->toContain($org2->id);
        expect($domainOrgIds)->not->toContain($org3->id);
    });

    it('syncs users to organizations', function () {
        $syncLog = SyncLog::factory()->create();

        User::factory()->count(10)->create([
            'email' => fn() => fake()->userName() . '@example.com',
            'domain_id' => null,
        ]);

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        // All users should be synced to organization
        $userCount = User::where('email', 'like', '%@example.com')
            ->whereHas('organizations', function ($query) {
                $query->where('organizations.id', $this->organization->id);
            })
            ->count();

        expect($userCount)->toBe(10);
    });

    it('updates sync log with progress', function () {
        $syncLog = SyncLog::factory()->create(['status' => 'pending']);

        User::factory()->count(5)->create([
            'email' => fn() => fake()->userName() . '@example.com',
        ]);

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(5);
        expect($syncLog->successful_items)->toBe(5);
    });

    it('marks sync log as failed on error', function () {
        $syncLog = SyncLog::factory()->create();

        // Use invalid domain ID to trigger error
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            99999, // Non-existent domain
            [$this->organization->id]
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('failed');
        expect($syncLog->error_message)->toContain('Domain');
    });

    it('sets legacy organization_id field', function () {
        $syncLog = SyncLog::factory()->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'organization_id' => null,
        ]);

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        expect($user->fresh()->organization_id)->toBe($this->organization->id);
    });

    it('handles multiple organizations', function () {
        $syncLog = SyncLog::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $org3 = Organization::factory()->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$org1->id, $org2->id, $org3->id]
        );

        $job->handle();

        $userOrgIds = $user->fresh()->organizations->pluck('id')->toArray();
        expect($userOrgIds)->toContain($org1->id);
        expect($userOrgIds)->toContain($org2->id);
        expect($userOrgIds)->toContain($org3->id);
    });

    it('does not duplicate pivot table entries', function () {
        $syncLog = SyncLog::factory()->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        // Run twice
        $job->handle();

        $syncLog2 = SyncLog::factory()->create();
        $job2 = new SyncDomainOrganizationsJob(
            $syncLog2->id,
            $this->domain->id,
            [$this->organization->id]
        );
        $job2->handle();

        $pivotCount = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('organization_id', $this->organization->id)
            ->count();

        expect($pivotCount)->toBe(1);
    });

    it('updates progress in batches', function () {
        $syncLog = SyncLog::factory()->create();

        // Create users with guaranteed unique emails
        for ($i = 0; $i < 250; $i++) {
            User::factory()->create([
                'email' => "batchuser{$i}@example.com",
            ]);
        }

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->processed_items)->toBe(250);
        expect($syncLog->successful_items)->toBe(250);
    });

    it('stores metadata about the sync', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->metadata)->toHaveKey('domain_id');
        expect($syncLog->metadata)->toHaveKey('domain_name');
        expect($syncLog->metadata)->toHaveKey('organization_ids');
        expect($syncLog->metadata['domain_id'])->toBe($this->domain->id);
    });

    it('handles timeout gracefully', function () {
        $job = new SyncDomainOrganizationsJob(1, 1, [1]);
        expect($job->timeout)->toBe(3600); // 1 hour timeout
    });

    it('can be queued', function () {
        Queue::fake();

        $syncLog = SyncLog::factory()->create();

        SyncDomainOrganizationsJob::dispatch(
            $syncLog->id,
            $this->domain->id,
            [$this->organization->id]
        );

        Queue::assertPushed(SyncDomainOrganizationsJob::class);
    });
});
