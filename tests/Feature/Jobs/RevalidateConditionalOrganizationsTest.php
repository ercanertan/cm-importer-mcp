<?php

use App\Jobs\RevalidateConditionalOrganizationsJob;
use App\Models\CmCustomField;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Log::spy();
});

test('revalidation removes auto-assigned users who no longer meet conditions', function () {
    // Create custom field
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

    // Create user with Engineering department
    $user = User::factory()->create(['email' => 'john@example.com']);
    $user->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Engineering',
    ]);

    // Auto-assign user to conditional org
    $user->organizations()->attach($conditionalOrg->id, ['is_manual' => false]);

    // Verify user is in the organization
    expect($user->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Update user's department to Sales (no longer qualifies)
    $user->customFieldValues()->where('cm_custom_field_id', $departmentField->id)->update([
        'value' => 'Sales',
    ]);

    // Create sync log and run revalidation
    $syncLog = SyncLog::factory()->create(['type' => 'revalidate_conditional', 'status' => 'pending']);
    $job = new RevalidateConditionalOrganizationsJob($syncLog->id, $conditionalOrg->id, false);
    $job->handle();

    // Verify user was removed from conditional organization
    $user->refresh();
    expect($user->organizations->pluck('id')->toArray())->not->toContain($conditionalOrg->id);

    // Verify user was moved to Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($user->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);
});

test('revalidation preserves manual assignments even if user does not meet conditions', function () {
    // Create custom field
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

    // Create user with Sales department (does NOT meet conditions)
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $user->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Sales',
    ]);

    // MANUALLY assign user to conditional org (super admin override)
    $user->organizations()->attach($conditionalOrg->id, ['is_manual' => true]);

    // Verify user is in the organization
    expect($user->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Run revalidation
    $syncLog = SyncLog::factory()->create(['type' => 'revalidate_conditional', 'status' => 'pending']);
    $job = new RevalidateConditionalOrganizationsJob($syncLog->id, $conditionalOrg->id, false);
    $job->handle();

    // Verify user is STILL in conditional organization (manual assignment preserved)
    $user->refresh();
    expect($user->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Verify is_manual flag is still true
    $pivot = DB::table('organization_user')
        ->where('user_id', $user->id)
        ->where('organization_id', $conditionalOrg->id)
        ->first();

    expect($pivot->is_manual)->toBe(1);
});

test('revalidation handles mixed manual and auto assignments correctly', function () {
    // Create custom field
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

    // User 1: Auto-assigned, meets conditions (should stay)
    $user1 = User::factory()->create(['email' => 'auto-match@example.com']);
    $user1->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Engineering',
    ]);
    $user1->organizations()->attach($conditionalOrg->id, ['is_manual' => false]);

    // User 2: Auto-assigned, does NOT meet conditions (should be removed)
    $user2 = User::factory()->create(['email' => 'auto-no-match@example.com']);
    $user2->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Sales',
    ]);
    $user2->organizations()->attach($conditionalOrg->id, ['is_manual' => false]);

    // User 3: Manually assigned, does NOT meet conditions (should stay)
    $user3 = User::factory()->create(['email' => 'manual-no-match@example.com']);
    $user3->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Marketing',
    ]);
    $user3->organizations()->attach($conditionalOrg->id, ['is_manual' => true]);

    // Run revalidation
    $syncLog = SyncLog::factory()->create(['type' => 'revalidate_conditional', 'status' => 'pending']);
    $job = new RevalidateConditionalOrganizationsJob($syncLog->id, $conditionalOrg->id, false);
    $job->handle();

    // Verify User 1 stays (auto-assigned, meets conditions)
    $user1->refresh();
    expect($user1->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Verify User 2 was removed (auto-assigned, does NOT meet conditions)
    $user2->refresh();
    expect($user2->organizations->pluck('id')->toArray())->not->toContain($conditionalOrg->id);

    // Verify User 3 stays (manually assigned, preserved even though doesn't meet conditions)
    $user3->refresh();
    expect($user3->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);
});

test('dry-run mode does not make actual changes', function () {
    // Create custom field
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

    // Create user who no longer meets conditions
    $user = User::factory()->create(['email' => 'john@example.com']);
    $user->customFieldValues()->create([
        'cm_custom_field_id' => $departmentField->id,
        'value' => 'Sales', // Does NOT meet Engineering condition
    ]);
    $user->organizations()->attach($conditionalOrg->id, ['is_manual' => false]);

    // Run revalidation in DRY RUN mode
    $syncLog = SyncLog::factory()->create(['type' => 'revalidate_conditional', 'status' => 'pending']);
    $job = new RevalidateConditionalOrganizationsJob($syncLog->id, $conditionalOrg->id, true); // dry_run = true
    $job->handle();

    // Verify user is STILL in conditional organization (no changes made)
    $user->refresh();
    expect($user->organizations->pluck('id')->toArray())->toContain($conditionalOrg->id);

    // Verify sync log shows dry_run = true
    $syncLog->refresh();
    expect($syncLog->metadata['dry_run'])->toBe(true);
    expect($syncLog->metadata['total_removed'])->toBe(1); // Would remove 1 user
});

test('revalidation processes all conditional organizations when no specific org is provided', function () {
    // Create custom field
    $levelField = CmCustomField::factory()->create([
        'field_key' => 'Level',
        'field_name' => 'Level',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create two conditional organizations
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

    $juniorOrg = Organization::factory()->create([
        'name' => 'Junior Staff',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $levelField->id,
                    'operator' => 'equals',
                    'value' => 'Junior',
                ],
            ],
        ],
    ]);

    // User 1: In Senior org, but now Junior level (should be removed)
    $user1 = User::factory()->create();
    $user1->customFieldValues()->create(['cm_custom_field_id' => $levelField->id, 'value' => 'Junior']);
    $user1->organizations()->attach($seniorOrg->id, ['is_manual' => false]);

    // User 2: In Junior org, but now Senior level (should be removed)
    $user2 = User::factory()->create();
    $user2->customFieldValues()->create(['cm_custom_field_id' => $levelField->id, 'value' => 'Senior']);
    $user2->organizations()->attach($juniorOrg->id, ['is_manual' => false]);

    // Run revalidation for ALL conditional organizations (no specific org ID)
    $syncLog = SyncLog::factory()->create(['type' => 'revalidate_conditional', 'status' => 'pending']);
    $job = new RevalidateConditionalOrganizationsJob($syncLog->id, null, false); // null = all orgs
    $job->handle();

    // Verify both users were removed from their incorrect organizations
    $user1->refresh();
    expect($user1->organizations->pluck('id')->toArray())->not->toContain($seniorOrg->id);

    $user2->refresh();
    expect($user2->organizations->pluck('id')->toArray())->not->toContain($juniorOrg->id);

    // Verify sync log processed 2 organizations
    $syncLog->refresh();
    expect($syncLog->metadata['total_organizations'])->toBe(2);
    expect($syncLog->metadata['total_removed'])->toBe(2);
});

test('SyncOrganizationConditionalJob removes disqualified users', function () {
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

    // Create domain
    $domain = Domain::factory()->create(['domain' => 'tech.com']);
    $devOrg->domains()->attach($domain->id);

    // Create users
    $developer = User::factory()->create(['email' => 'dev@tech.com', 'domain_id' => $domain->id]);
    $developer->customFieldValues()->create(['cm_custom_field_id' => $roleField->id, 'value' => 'Developer']);

    // Create user who WAS a developer but no longer is
    $formerDev = User::factory()->create(['email' => 'former@tech.com', 'domain_id' => $domain->id]);
    $formerDev->customFieldValues()->create(['cm_custom_field_id' => $roleField->id, 'value' => 'Manager']);
    $formerDev->organizations()->attach($devOrg->id, ['is_manual' => false]); // Was auto-assigned before

    // Run sync (this should add developer and remove formerDev)
    $syncLog = SyncLog::factory()->create(['type' => 'organization_conditional', 'status' => 'pending']);
    $job = new \App\Jobs\SyncOrganizationConditionalJob(
        $syncLog->id,
        $devOrg->id,
        ['tech.com'],
        $devOrg->conditional_rules['conditions'],
        'AND'
    );
    $job->handle();

    // Verify developer is in the org
    $developer->refresh();
    expect($developer->organizations->pluck('id')->toArray())->toContain($devOrg->id);

    // Verify formerDev was REMOVED from the org
    $formerDev->refresh();
    expect($formerDev->organizations->pluck('id')->toArray())->not->toContain($devOrg->id);

    // Verify formerDev was moved to Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($formerDev->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);
});
