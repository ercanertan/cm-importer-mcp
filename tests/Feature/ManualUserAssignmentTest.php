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

describe('Manual User Assignment', function () {
    beforeEach(function () {
        $this->defaultOrg = Organization::factory()->create(['name' => 'Default Organization']);
        $this->org1 = Organization::factory()->create(['name' => 'Org 1']);
        $this->org2 = Organization::factory()->create(['name' => 'Org 2']);
        $this->domain = Domain::create(['domain' => 'example.com']);
        $this->user = User::factory()->create(['email' => 'test@example.com']);
    });

    it('sets is_manual flag to true when admin manually assigns user', function () {
        // Manually assign user to organization
        $this->user->organizations()->attach($this->org1->id, ['is_manual' => true]);

        $pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot->is_manual)->toBe(1);
    });

    it('sets is_manual flag to false during auto domain sync', function () {
        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Auto sync via domain job
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        $pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot->is_manual)->toBe(0);
    });

    it('preserves manual assignments when domain sync runs', function () {
        // Manually assign user to org2
        $this->user->organizations()->attach($this->org2->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Auto sync domain to org1 (should preserve manual org2 assignment)
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        $userOrgs = $this->user->fresh()->organizations->pluck('id')->toArray();

        // User should belong to both org1 (auto) and org2 (manual)
        expect($userOrgs)->toContain($this->org1->id);
        expect($userOrgs)->toContain($this->org2->id);

        // Check is_manual flags
        $org1Pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        $org2Pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org2->id)
            ->first();

        expect($org1Pivot->is_manual)->toBe(0); // Auto
        expect($org2Pivot->is_manual)->toBe(1); // Manual
    });

    it('preserves manual assignments across multiple domain syncs', function () {
        // Manually assign user to org2
        $this->user->organizations()->attach($this->org2->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);

        // Run sync 3 times
        for ($i = 0; $i < 3; $i++) {
            $syncLog = SyncLog::factory()->create();
            $job = new SyncDomainOrganizationsJob(
                $syncLog->id,
                $this->domain->id,
                [$this->org1->id]
            );
            $job->handle();
        }

        $userOrgs = $this->user->fresh()->organizations->pluck('id')->toArray();

        // Manual assignment should still exist after 3 syncs
        expect($userOrgs)->toContain($this->org2->id);

        $org2Pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org2->id)
            ->first();

        expect($org2Pivot->is_manual)->toBe(1);
    });

    it('removes manual assignments when admin explicitly removes them', function () {
        // Manually assign user to org1
        $this->user->organizations()->attach($this->org1->id, ['is_manual' => true]);

        expect($this->user->organizations->pluck('id'))->toContain($this->org1->id);

        // Admin removes the assignment
        $this->user->organizations()->detach($this->org1->id);

        expect($this->user->fresh()->organizations->pluck('id'))->not->toContain($this->org1->id);
    });

    it('allows user to be manually assigned to multiple organizations', function () {
        // Manually assign to multiple orgs
        $this->user->organizations()->attach([
            $this->org1->id => ['is_manual' => true],
            $this->org2->id => ['is_manual' => true],
        ]);

        $userOrgs = $this->user->fresh()->organizations;

        expect($userOrgs)->toHaveCount(2);
        expect($userOrgs->pluck('id')->toArray())->toMatchArray([$this->org1->id, $this->org2->id]);

        // Both should be marked as manual
        $pivots = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->whereIn('organization_id', [$this->org1->id, $this->org2->id])
            ->get();

        foreach ($pivots as $pivot) {
            expect($pivot->is_manual)->toBe(1);
        }
    });

    it('handles scenario where user has both manual and auto assignments', function () {
        // Manually assign to org2
        $this->user->organizations()->attach($this->org2->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Auto sync to org1
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        $userOrgs = $this->user->fresh()->organizations->pluck('id')->toArray();

        // User should have both
        expect($userOrgs)->toHaveCount(2);
        expect($userOrgs)->toContain($this->org1->id); // Auto
        expect($userOrgs)->toContain($this->org2->id); // Manual

        // Verify is_manual flags are correct
        $pivots = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->orderBy('organization_id')
            ->get()
            ->keyBy('organization_id');

        expect($pivots[$this->org1->id]->is_manual)->toBe(0);
        expect($pivots[$this->org2->id]->is_manual)->toBe(1);
    });

    it('preserves manual assignments when domain changes organizations', function () {
        // Manually assign user to org2
        $this->user->organizations()->attach($this->org2->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Auto sync to org1
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        // Now change domain to sync to a different org (org2)
        $syncLog2 = SyncLog::factory()->create();
        $job2 = new SyncDomainOrganizationsJob(
            $syncLog2->id,
            $this->domain->id,
            [$this->org2->id] // Now syncing to org2 instead
        );
        $job2->handle();

        $userOrgs = $this->user->fresh()->organizations->pluck('id')->toArray();

        // User should only have org2 (manual assignment preserved, org1 removed)
        expect($userOrgs)->toContain($this->org2->id);
        expect($userOrgs)->not->toContain($this->org1->id);

        // org2 should still be marked as manual
        $org2Pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org2->id)
            ->first();

        expect($org2Pivot->is_manual)->toBe(1);
    });

    it('prevents duplicate entries when user is manually assigned to org that domain auto-assigns', function () {
        // Manually assign user to org1
        $this->user->organizations()->attach($this->org1->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Auto sync to same org1
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        // Should only have ONE pivot entry
        $pivotCount = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org1->id)
            ->count();

        expect($pivotCount)->toBe(1);

        // Manual flag should be preserved (manual takes precedence)
        $pivot = DB::table('organization_user')
            ->where('user_id', $this->user->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot->is_manual)->toBe(1);
    });

    it('handles user with no email domain match', function () {
        $userNoMatch = User::factory()->create(['email' => 'test@different.com']);

        // Manually assign to org1
        $userNoMatch->organizations()->attach($this->org1->id, ['is_manual' => true]);

        $this->domain->organizations()->attach($this->org1->id);
        $syncLog = SyncLog::factory()->create();

        // Domain sync should not affect this user
        $job = new SyncDomainOrganizationsJob(
            $syncLog->id,
            $this->domain->id,
            [$this->org1->id]
        );
        $job->handle();

        // Manual assignment should still exist
        $userOrgs = $userNoMatch->fresh()->organizations->pluck('id')->toArray();
        expect($userOrgs)->toContain($this->org1->id);

        $pivot = DB::table('organization_user')
            ->where('user_id', $userNoMatch->id)
            ->where('organization_id', $this->org1->id)
            ->first();

        expect($pivot->is_manual)->toBe(1);
    });
});
