<?php

use App\Jobs\SyncDomainOrganizationsJob;
use App\Models\CmCustomField;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
});

test('Domain::assignUsersFromDomain respects conditional rules', function () {
    // Create custom fields
    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization
    $conditionalOrg = Organization::factory()->create([
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

    // Create domain and associate with conditional organization
    $domain = Domain::factory()->create(['domain' => 'example.com']);
    $conditionalOrg->domains()->attach($domain->id);

    // Create users with different department values
    $engineer = User::factory()->create(['email' => 'john@example.com', 'domain_id' => $domain->id]);
    $engineer->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Engineering',
    ]);

    $sales = User::factory()->create(['email' => 'jane@example.com', 'domain_id' => $domain->id]);
    $sales->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Sales',
    ]);

    // Run sync
    $result = $domain->assignUsersFromDomain();

    // Verify engineer is assigned to conditional org
    $engineer->refresh();
    expect($engineer->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Verify sales person is NOT assigned to conditional org
    $sales->refresh();
    expect($sales->organizations->pluck('id')->toArray())->not->toContain($conditionalOrg->id);

    // Verify sales person is assigned to Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($defaultOrg)->not->toBeNull();
    expect($sales->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);
});

test('Domain::assignUsersToOrganization respects conditional rules', function () {
    // Create custom field
    $levelField = CmCustomField::factory()->create([
        'field_key' => 'Level',
        'field_name' => 'Level',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization
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

    // Create domain
    $domain = Domain::factory()->create(['domain' => 'company.com']);

    // Create users
    $senior = User::factory()->create(['email' => 'senior@company.com', 'domain_id' => $domain->id]);
    $senior->customFieldValues()->create([
        'cm_custom_field_id' => $levelField->id,
        'value' => 'Senior',
    ]);

    $junior = User::factory()->create(['email' => 'junior@company.com', 'domain_id' => $domain->id]);
    $junior->customFieldValues()->create([
        'cm_custom_field_id' => $levelField->id,
        'value' => 'Junior',
    ]);

    // Assign users to conditional organization
    $count = $domain->assignUsersToOrganization($seniorOrg);

    // Should only return count of qualified users
    expect($count)->toBe(1);

    // Verify senior is assigned to conditional org
    $senior->refresh();
    expect($senior->organizations->pluck('id')->toArray())->toContain($seniorOrg->id);

    // Verify junior is NOT assigned to conditional org
    $junior->refresh();
    expect($junior->organizations->pluck('id')->toArray())->not->toContain($seniorOrg->id);

    // Verify junior is assigned to Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($junior->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);
});

test('SyncDomainOrganizationsJob respects conditional rules', function () {
    // Create custom field
    $titleField = CmCustomField::factory()->create([
        'field_key' => 'Title',
        'field_name' => 'Title',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization
    $managerOrg = Organization::factory()->create([
        'name' => 'Managers',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $titleField->id,
                    'operator' => 'contains',
                    'value' => 'Manager',
                ],
            ],
        ],
    ]);

    // Create domain and associate with conditional organization
    $domain = Domain::factory()->create(['domain' => 'tech.com']);
    $managerOrg->domains()->attach($domain->id);

    // Create users
    $manager = User::factory()->create(['email' => 'alice@tech.com', 'domain_id' => $domain->id]);
    $manager->customFieldValues()->create([
        'cm_custom_field_id' => $titleField->id,
        'value' => 'Engineering Manager',
    ]);

    $developer = User::factory()->create(['email' => 'bob@tech.com', 'domain_id' => $domain->id]);
    $developer->customFieldValues()->create([
        'cm_custom_field_id' => $titleField->id,
        'value' => 'Senior Developer',
    ]);

    // Create sync log
    $syncLog = SyncLog::factory()->create([
        'type' => 'domain_organizations',
        'status' => 'pending',
    ]);

    // Run sync job
    $job = new SyncDomainOrganizationsJob($syncLog->id, $domain->id, [$managerOrg->id]);
    $job->handle();

    // Verify manager is assigned to conditional org
    $manager->refresh();
    expect($manager->organizations->pluck('id')->toArray())->toContain($managerOrg->id);

    // Verify developer is NOT assigned to conditional org
    $developer->refresh();
    expect($developer->organizations->pluck('id')->toArray())->not->toContain($managerOrg->id);

    // Verify developer is assigned to Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($developer->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);

    // Verify sync log completed
    $syncLog->refresh();
    expect($syncLog->status)->toBe('completed');
});

test('sync handles mixed conditional and non-conditional organizations', function () {
    // Create custom field
    $roleField = CmCustomField::factory()->create([
        'field_key' => 'Role',
        'field_name' => 'Role',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization
    $devOrg = Organization::factory()->create([
        'name' => 'Developers',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $roleField->id,
                    'operator' => 'equals',
                    'value' => 'Developer',
                ],
            ],
        ],
    ]);

    // Create non-conditional organization
    $allStaffOrg = Organization::factory()->create([
        'name' => 'All Staff',
        'conditional_rules' => null,
    ]);

    // Create domain and associate with both organizations
    $domain = Domain::factory()->create(['domain' => 'company.com']);
    $devOrg->domains()->attach($domain->id);
    $allStaffOrg->domains()->attach($domain->id);

    // Create users
    $developer = User::factory()->create(['email' => 'dev@company.com', 'domain_id' => $domain->id]);
    $developer->customFieldValues()->create([
        'cm_custom_field_id' => $roleField->id,
        'value' => 'Developer',
    ]);

    $designer = User::factory()->create(['email' => 'designer@company.com', 'domain_id' => $domain->id]);
    $designer->customFieldValues()->create([
        'cm_custom_field_id' => $roleField->id,
        'value' => 'Designer',
    ]);

    // Run sync
    $result = $domain->assignUsersFromDomain();

    // Verify developer is in both organizations
    $developer->refresh();
    expect($developer->organizations->pluck('id')->toArray())->toContain($devOrg->id);
    expect($developer->organizations->pluck('id')->toArray())->toContain($allStaffOrg->id);

    // Verify designer is only in non-conditional organization
    $designer->refresh();
    expect($designer->organizations->pluck('id')->toArray())->not->toContain($devOrg->id);
    expect($designer->organizations->pluck('id')->toArray())->toContain($allStaffOrg->id);
});

test('sync respects OR logic in conditional rules', function () {
    // Create custom fields
    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    $roleField = CmCustomField::factory()->create([
        'field_key' => 'Role',
        'field_name' => 'Role',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization with OR logic
    $techOrg = Organization::factory()->create([
        'name' => 'Tech Team',
        'conditional_rules' => [
            'logic' => 'OR',
            'conditions' => [
                [
                    'field_id' => $departmentField->id,
                    'operator' => 'equals',
                    'value' => 'Engineering',
                ],
                [
                    'field_id' => $roleField->id,
                    'operator' => 'equals',
                    'value' => 'Developer',
                ],
            ],
        ],
    ]);

    // Create domain
    $domain = Domain::factory()->create(['domain' => 'tech.com']);
    $techOrg->domains()->attach($domain->id);

    // Create users
    $engineer = User::factory()->create(['email' => 'eng@tech.com', 'domain_id' => $domain->id]);
    $engineer->customFieldValues()->createMany([
        [
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Engineering',
        ],
        [
            'cm_custom_field_id' => $roleField->id,
            'value' => 'Manager',
        ],
    ]);

    $developer = User::factory()->create(['email' => 'dev@tech.com', 'domain_id' => $domain->id]);
    $developer->customFieldValues()->createMany([
        [
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Marketing',
        ],
        [
            'cm_custom_field_id' => $roleField->id,
            'value' => 'Developer',
        ],
    ]);

    $sales = User::factory()->create(['email' => 'sales@tech.com', 'domain_id' => $domain->id]);
    $sales->customFieldValues()->createMany([
        [
            'cm_custom_field_id' => $departmentField->id,
            'value' => 'Sales',
        ],
        [
            'cm_custom_field_id' => $roleField->id,
            'value' => 'Account Executive',
        ],
    ]);

    // Run sync
    $result = $domain->assignUsersFromDomain();

    // Verify engineer is assigned (meets department condition)
    $engineer->refresh();
    expect($engineer->organizations->pluck('id')->toArray())->toContain($techOrg->id);

    // Verify developer is assigned (meets role condition)
    $developer->refresh();
    expect($developer->organizations->pluck('id')->toArray())->toContain($techOrg->id);

    // Verify sales is NOT assigned (meets neither condition)
    $sales->refresh();
    expect($sales->organizations->pluck('id')->toArray())->not->toContain($techOrg->id);
});
