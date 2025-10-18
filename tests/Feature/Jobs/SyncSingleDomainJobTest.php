<?php

use App\Jobs\SyncSingleDomainJob;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SyncSingleDomainJob', function () {
    beforeEach(function () {
        $this->domain = Domain::create(['domain' => 'example.com']);
        $this->organization = Organization::factory()->create();
        $this->domain->organizations()->attach($this->organization->id);
    });

    it('syncs all users from domain', function () {
        $syncLog = SyncLog::factory()->create();

        User::factory()->count(15)->create([
            'email' => fn() => fake()->userName() . '@example.com',
            'domain_id' => null,
        ]);

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        expect(User::where('domain_id', $this->domain->id)->count())->toBe(15);
    });

    it('updates sync log with results', function () {
        $syncLog = SyncLog::factory()->create(['status' => 'pending']);

        User::factory()->count(10)->create([
            'email' => fn() => fake()->userName() . '@example.com',
        ]);

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(10);
        expect($syncLog->processed_items)->toBe(10);
    });

    it('stores domain metadata in sync log', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        $syncLog->refresh();
        expect($syncLog->metadata)->toHaveKey('domain_id');
        expect($syncLog->metadata)->toHaveKey('domain_name');
        expect($syncLog->metadata['domain_id'])->toBe($this->domain->id);
        expect($syncLog->metadata['domain_name'])->toBe('example.com');
    });

    it('marks sync as failed if domain not found', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncSingleDomainJob($syncLog->id, 99999);
        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('failed');
        expect($syncLog->error_message)->toContain('not found');
    });

    it('assigns users to domain organizations', function () {
        $syncLog = SyncLog::factory()->create();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        $userOrgIds = $user->fresh()->organizations->pluck('id')->toArray();
        expect($userOrgIds)->toContain($this->organization->id);
    });

    it('handles domain with no users', function () {
        $syncLog = SyncLog::factory()->create();

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        $syncLog->refresh();
        expect($syncLog->status)->toBe('completed');
        expect($syncLog->total_items)->toBe(0);
    });

    it('handles domain with multiple organizations', function () {
        $syncLog = SyncLog::factory()->create();
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();

        $this->domain->organizations()->attach([$org1->id, $org2->id]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();

        $userOrgIds = $user->fresh()->organizations->pluck('id')->toArray();
        expect(count($userOrgIds))->toBeGreaterThanOrEqual(2);
    });

    it('uses bulk operations for performance', function () {
        $syncLog = SyncLog::factory()->create();

        // Create users with guaranteed unique emails
        for ($i = 0; $i < 1000; $i++) {
            User::factory()->create([
                'email' => "user{$i}@example.com",
            ]);
        }

        $startTime = microtime(true);
        $job = new SyncSingleDomainJob($syncLog->id, $this->domain->id);
        $job->handle();
        $endTime = microtime(true);

        // Should complete in less than 5 seconds for 1000 users
        expect($endTime - $startTime)->toBeLessThan(5.0);
    });

    it('has 1 hour timeout', function () {
        $job = new SyncSingleDomainJob(1, 1);
        expect($job->timeout)->toBe(3600);
    });
});
