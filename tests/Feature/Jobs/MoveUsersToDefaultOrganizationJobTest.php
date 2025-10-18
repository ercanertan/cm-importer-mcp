<?php

use App\Jobs\MoveUsersToDefaultOrganizationJob;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MoveUsersToDefaultOrganizationJob', function () {
    beforeEach(function () {
        $this->defaultOrganization = Organization::factory()->create(['name' => 'Default Organization']);
        $this->organization = Organization::factory()->create(['name' => 'Test Org']);
    });

    it('moves all users from organization to default organization', function () {
        $syncLog = SyncLog::factory()->create();

        // Create users in the organization to be deleted
        User::factory()->count(10)->create([
            'organization_id' => $this->organization->id,
        ]);

        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            $this->defaultOrganization->id
        );

        $job->handle();

        // All users should now be in default organization
        expect(User::where('organization_id', $this->defaultOrganization->id)->count())->toBe(10);
        expect(User::where('organization_id', $this->organization->id)->count())->toBe(0);
    });

    it('uses bulk update for performance', function () {
        $syncLog = SyncLog::factory()->create();

        User::factory()->count(1000)->create([
            'organization_id' => $this->organization->id,
        ]);

        $startTime = microtime(true);
        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            $this->defaultOrganization->id
        );
        $job->handle();
        $endTime = microtime(true);

        // Should complete in less than 2 seconds for 1000 users
        expect($endTime - $startTime)->toBeLessThan(2.0);
        expect(User::where('organization_id', $this->defaultOrganization->id)->count())->toBe(1000);
    });

    it('updates sync log with results', function () {
        $syncLog = SyncLog::factory()->create(['status' => 'pending']);

        User::factory()->count(25)->create([
            'organization_id' => $this->organization->id,
        ]);

        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            $this->defaultOrganization->id
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(25);
        expect($syncLog->successful_items)->toBe(25);
    });

    it('stores metadata about the move', function () {
        $syncLog = SyncLog::factory()->create();

        User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            $this->defaultOrganization->id
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->metadata)->toHaveKey('organization_id');
        expect($syncLog->metadata)->toHaveKey('default_organization_id');
        expect($syncLog->metadata)->toHaveKey('moved_count');
        expect($syncLog->metadata['moved_count'])->toBe(1);
    });

    it('handles organization with no users', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            $this->defaultOrganization->id
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(0);
    });

    it('marks sync as failed on error', function () {
        $syncLog = SyncLog::factory()->create();

        // This should fail because we can't move to an org that doesn't exist
        $job = new MoveUsersToDefaultOrganizationJob(
            $syncLog->id,
            $this->organization->id,
            99999 // Non-existent org
        );

        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('failed');
    });

    it('has 1 hour timeout', function () {
        $job = new MoveUsersToDefaultOrganizationJob(1, 1, 1);
        expect($job->timeout)->toBe(3600);
    });
});
