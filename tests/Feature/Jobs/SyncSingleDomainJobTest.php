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

    it('proactively discovers and assigns users to ALL qualifying conditional organizations', function () {
        // Create custom fields for Department and Level
        $departmentField = \App\Models\CmCustomField::factory()->create([
            'field_key' => 'Department',
            'field_name' => 'Department',
            'data_type' => 'text',
            'is_active' => true,
        ]);

        $levelField = \App\Models\CmCustomField::factory()->create([
            'field_key' => 'Level',
            'field_name' => 'Level',
            'data_type' => 'text',
            'is_active' => true,
        ]);

        // Create domain
        $domain = Domain::factory()->create(['domain' => 'techcorp.com']);

        // Create 3 conditional organizations ALL associated with the same domain
        $engineeringOrg = Organization::factory()->create([
            'name' => 'Engineering Team',
            'conditional_rules' => [
                'logic' => 'AND',
                'conditions' => [
                    [
                        'field_id' => $departmentField->id,
                        'operator' => 'equals',
                        'value' => 'Engineering',
                    ],
                ],
            ],
        ]);
        $engineeringOrg->domains()->attach($domain->id);

        $seniorOrg = Organization::factory()->create([
            'name' => 'Senior Staff',
            'conditional_rules' => [
                'logic' => 'AND',
                'conditions' => [
                    [
                        'field_id' => $levelField->id,
                        'operator' => 'equals',
                        'value' => 'Senior',
                    ],
                ],
            ],
        ]);
        $seniorOrg->domains()->attach($domain->id);

        $seniorEngOrg = Organization::factory()->create([
            'name' => 'Senior Engineers',
            'conditional_rules' => [
                'logic' => 'AND',
                'conditions' => [
                    [
                        'field_id' => $departmentField->id,
                        'operator' => 'equals',
                        'value' => 'Engineering',
                    ],
                    [
                        'field_id' => $levelField->id,
                        'operator' => 'equals',
                        'value' => 'Senior',
                    ],
                ],
            ],
        ]);
        $seniorEngOrg->domains()->attach($domain->id);

        // Create users with different combinations
        // User 1: Senior Engineer (should be in ALL 3 orgs)
        $seniorEng = User::factory()->create(['email' => 'senior-eng@techcorp.com']);
        $seniorEng->customFieldValues()->create([
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Engineering',
        ]);
        $seniorEng->customFieldValues()->create([
            'cm_custom_field_id' => $levelField->id,
            'value' => 'Senior',
        ]);

        // User 2: Junior Engineer (should be in Engineering only)
        $juniorEng = User::factory()->create(['email' => 'junior-eng@techcorp.com']);
        $juniorEng->customFieldValues()->create([
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Engineering',
        ]);
        $juniorEng->customFieldValues()->create([
            'cm_custom_field_id' => $levelField->id,
            'value' => 'Junior',
        ]);

        // User 3: Senior in Sales (should be in Senior Staff only)
        $seniorSales = User::factory()->create(['email' => 'senior-sales@techcorp.com']);
        $seniorSales->customFieldValues()->create([
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Sales',
        ]);
        $seniorSales->customFieldValues()->create([
            'cm_custom_field_id' => $levelField->id,
            'value' => 'Senior',
        ]);

        // Run sync
        $syncLog = SyncLog::factory()->create();
        $job = new SyncSingleDomainJob($syncLog->id, $domain->id);
        $job->handle();

        // Verify User 1 (Senior Engineer) is in ALL 3 conditional orgs
        $seniorEng->refresh();
        $seniorEngOrgIds = $seniorEng->organizations->pluck('id')->toArray();
        expect($seniorEngOrgIds)->toContain($engineeringOrg->id);
        expect($seniorEngOrgIds)->toContain($seniorOrg->id);
        expect($seniorEngOrgIds)->toContain($seniorEngOrg->id);

        // Verify User 2 (Junior Engineer) is ONLY in Engineering org
        $juniorEng->refresh();
        $juniorEngOrgIds = $juniorEng->organizations->pluck('id')->toArray();
        expect($juniorEngOrgIds)->toContain($engineeringOrg->id);
        expect($juniorEngOrgIds)->not->toContain($seniorOrg->id);
        expect($juniorEngOrgIds)->not->toContain($seniorEngOrg->id);

        // Verify User 3 (Senior Sales) is ONLY in Senior Staff org
        $seniorSales->refresh();
        $seniorSalesOrgIds = $seniorSales->organizations->pluck('id')->toArray();
        expect($seniorSalesOrgIds)->not->toContain($engineeringOrg->id);
        expect($seniorSalesOrgIds)->toContain($seniorOrg->id);
        expect($seniorSalesOrgIds)->not->toContain($seniorEngOrg->id);
    });

    it('proactive discovery respects domain boundaries during sync', function () {
        // Create custom field
        $departmentField = \App\Models\CmCustomField::factory()->create([
            'field_key' => 'Department',
            'field_name' => 'Department',
            'data_type' => 'text',
            'is_active' => true,
        ]);

        // Create two domains
        $domainA = Domain::factory()->create(['domain' => 'companyA.com']);
        $domainB = Domain::factory()->create(['domain' => 'companyB.com']);

        // Create conditional org associated ONLY with domainA
        $engineeringOrg = Organization::factory()->create([
            'name' => 'Engineering Team',
            'conditional_rules' => [
                'logic' => 'AND',
                'conditions' => [
                    [
                        'field_id' => $departmentField->id,
                        'operator' => 'equals',
                        'value' => 'Engineering',
                    ],
                ],
            ],
        ]);
        $engineeringOrg->domains()->attach($domainA->id); // ONLY domainA

        // Create a regular (non-conditional) org for domainB
        $regularOrgB = Organization::factory()->create(['name' => 'Company B General']);
        $regularOrgB->domains()->attach($domainB->id);

        // Create user in domainB with Engineering department
        $userB = User::factory()->create(['email' => 'engineer@companyB.com']);
        $userB->customFieldValues()->create([
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Engineering',
        ]);

        // Run sync on domainB
        $syncLog = SyncLog::factory()->create();
        $job = new SyncSingleDomainJob($syncLog->id, $domainB->id);
        $job->handle();

        // Verify userB is NOT in Engineering org (domain boundary respected)
        $userB->refresh();
        $userBOrgIds = $userB->organizations->pluck('id')->toArray();
        expect($userBOrgIds)->not->toContain($engineeringOrg->id);

        // Verify userB is in the regular org for domainB
        expect($userBOrgIds)->toContain($regularOrgB->id);
    });
});
