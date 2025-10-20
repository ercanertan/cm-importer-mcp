<?php

use App\Models\CmCustomField;
use App\Models\CmImportLog;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use App\Services\CampaignMonitorImportService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Log::spy();

    // Create and authenticate a test user for imports
    $testUser = User::factory()->create(['email' => 'test@admin.com']);
    $this->actingAs($testUser);
});

test('imports users to conditional organization when rules match (AND logic)', function () {
    // Create custom fields
    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    $locationField = CmCustomField::factory()->create([
        'field_key' => 'Location',
        'field_name' => 'Location',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create organization with AND conditional rules
    $organization = Organization::factory()->create([
        'name' => 'Engineering Team',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $departmentField->id,
                    'operator' => 'equals',
                    'value' => 'Engineering',
                ],
                [
                    'field_id' => $locationField->id,
                    'operator' => 'equals',
                    'value' => 'San Francisco',
                ],
            ],
        ],
    ]);

    // Create domain and associate with organization
    $domain = Domain::factory()->create(['domain' => 'example.com']);
    $organization->domains()->attach($domain->id);

    // Create CSV with users that meet both conditions
    $csv = "Email,Department,Location\n";
    $csv .= "john@example.com,Engineering,San Francisco\n";
    $csv .= "jane@example.com,Engineering,New York\n"; // Doesn't meet location condition
    $csv .= "bob@example.com,Sales,San Francisco\n"; // Doesn't meet department condition

    $filePath = storage_path('app/test-import.csv');
    file_put_contents($filePath, $csv);

    // Import
    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    if (!$result['success']) {
        dump('Error:', $result['error'] ?? 'Unknown', 'Log:', $result['log'] ?? null);
    }

    expect($result['success'])->toBeTrue();

    // Check john@example.com is assigned to Engineering Team (meets both conditions)
    $john = User::where('email', 'john@example.com')->first();
    expect($john)->not->toBeNull();
    expect($john->organizations->pluck('id')->toArray())->toContain($organization->id);

    // Check jane@example.com is NOT assigned to Engineering Team (doesn't meet location)
    $jane = User::where('email', 'jane@example.com')->first();
    expect($jane)->not->toBeNull();
    $janeOrgIds = $jane->organizations->pluck('id')->toArray();
    expect($janeOrgIds)->not->toContain($organization->id);

    // Verify custom field values were saved
    expect($john->customFieldValues()->where('cm_custom_field_id', $departmentField->id)->first()->value)
        ->toBe('Engineering');

    @unlink($filePath);
});

test('imports users to conditional organization when rules match (OR logic)', function () {
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

    // Create organization with OR conditional rules
    $organization = Organization::factory()->create([
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

    // Create domain and associate with organization
    $domain = Domain::factory()->create(['domain' => 'tech.com']);
    $organization->domains()->attach($domain->id);

    // Create CSV
    $csv = "Email,Department,Role\n";
    $csv .= "alice@tech.com,Engineering,Designer\n"; // Meets department condition
    $csv .= "bob@tech.com,Marketing,Developer\n"; // Meets role condition
    $csv .= "charlie@tech.com,Sales,Manager\n"; // Meets neither

    $filePath = storage_path('app/test-import-or.csv');
    file_put_contents($filePath, $csv);

    // Import
    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    // Alice meets department condition (Engineering)
    $alice = User::where('email', 'alice@tech.com')->first();
    expect($alice->organizations->pluck('id')->toArray())->toContain($organization->id);

    // Bob meets role condition (Developer)
    $bob = User::where('email', 'bob@tech.com')->first();
    expect($bob->organizations->pluck('id')->toArray())->toContain($organization->id);

    // Charlie meets neither condition
    $charlie = User::where('email', 'charlie@tech.com')->first();
    expect($charlie->organizations->pluck('id')->toArray())->not->toContain($organization->id);

    @unlink($filePath);
});

test('users excluded from conditional org fall back to default organization', function () {
    // Create custom field
    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create conditional organization
    $conditionalOrg = Organization::factory()->create([
        'name' => 'Sales Team',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $departmentField->id,
                    'operator' => 'equals',
                    'value' => 'Sales',
                ],
            ],
        ],
    ]);

    $domain = Domain::factory()->create(['domain' => 'company.com']);
    $conditionalOrg->domains()->attach($domain->id);

    // Create CSV with user that doesn't meet condition
    $csv = "Email,Department\n";
    $csv .= "john@company.com,Engineering\n"; // Doesn't meet Sales condition

    $filePath = storage_path('app/test-fallback.csv');
    file_put_contents($filePath, $csv);

    // Import
    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    $john = User::where('email', 'john@company.com')->first();
    expect($john)->not->toBeNull();

    // Should NOT be in conditional organization
    expect($john->organizations->pluck('id')->toArray())->not->toContain($conditionalOrg->id);

    // Should be in Default Organization
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($defaultOrg)->not->toBeNull();
    expect($john->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);

    @unlink($filePath);
});

test('imports work correctly with mixed organizations (with and without rules)', function () {
    // Create custom field
    $levelField = CmCustomField::factory()->create([
        'field_key' => 'Level',
        'field_name' => 'Level',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Organization with conditional rules
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

    $seniorDomain = Domain::factory()->create(['domain' => 'senior.com']);
    $seniorOrg->domains()->attach($seniorDomain->id);

    // Organization without conditional rules (normal assignment)
    $regularOrg = Organization::factory()->create([
        'name' => 'Regular Team',
        'conditional_rules' => null,
    ]);

    $regularDomain = Domain::factory()->create(['domain' => 'regular.com']);
    $regularOrg->domains()->attach($regularDomain->id);

    // Create CSV
    $csv = "Email,Level\n";
    $csv .= "senior@senior.com,Senior\n"; // Should go to Senior Staff
    $csv .= "junior@senior.com,Junior\n"; // Should go to Default (doesn't meet condition)
    $csv .= "alice@regular.com,Junior\n"; // Should go to Regular Team (no rules)

    $filePath = storage_path('app/test-mixed.csv');
    file_put_contents($filePath, $csv);

    // Import
    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    // senior@senior.com meets condition
    $senior = User::where('email', 'senior@senior.com')->first();
    expect($senior->organizations->pluck('id')->toArray())->toContain($seniorOrg->id);

    // junior@senior.com doesn't meet condition
    $junior = User::where('email', 'junior@senior.com')->first();
    expect($junior->organizations->pluck('id')->toArray())->not->toContain($seniorOrg->id);

    // alice@regular.com goes to regular org (no conditions)
    $alice = User::where('email', 'alice@regular.com')->first();
    expect($alice->organizations->pluck('id')->toArray())->toContain($regularOrg->id);

    @unlink($filePath);
});

test('tests all supported operators', function () {
    // Create custom fields
    $nameField = CmCustomField::factory()->create([
        'field_key' => 'Name',
        'field_name' => 'Name',
        'data_type' => 'text',
    ]);

    $titleField = CmCustomField::factory()->create([
        'field_key' => 'Title',
        'field_name' => 'Title',
        'data_type' => 'text',
    ]);

    // Test contains operator
    $org = Organization::factory()->create([
        'name' => 'Engineers',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $titleField->id,
                    'operator' => 'contains',
                    'value' => 'Engineer',
                ],
            ],
        ],
    ]);

    $domain = Domain::factory()->create(['domain' => 'eng.com']);
    $org->domains()->attach($domain->id);

    $csv = "Email,Title\n";
    $csv .= "john@eng.com,Senior Engineer\n"; // Contains 'Engineer'
    $csv .= "jane@eng.com,Product Manager\n"; // Doesn't contain 'Engineer'

    $filePath = storage_path('app/test-operators.csv');
    file_put_contents($filePath, $csv);

    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    $john = User::where('email', 'john@eng.com')->first();
    expect($john->organizations->pluck('id')->toArray())->toContain($org->id);

    $jane = User::where('email', 'jane@eng.com')->first();
    expect($jane->organizations->pluck('id')->toArray())->not->toContain($org->id);

    @unlink($filePath);
});

test('proactively discovers and assigns users to ALL qualifying conditional organizations', function () {
    // This tests the NEW feature: evaluateAllConditionalOrganizations()
    // Scenario: User's domain matches multiple orgs, system should find ALL conditional orgs they qualify for

    // Create custom fields
    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    $levelField = CmCustomField::factory()->create([
        'field_key' => 'Level',
        'field_name' => 'Level',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Create domain (shared by multiple organizations)
    $domain = Domain::factory()->create(['domain' => 'company.com']);

    // Organization 1: Regular org (no conditional rules) associated with domain
    $regularOrg = Organization::factory()->create([
        'name' => 'Company General',
        'conditional_rules' => null,
    ]);
    $regularOrg->domains()->attach($domain->id);

    // Organization 2: Conditional org for Engineering department
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

    // Organization 3: Conditional org for Senior level
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

    // Organization 4: Conditional org for Senior Engineers (both conditions)
    $seniorEngineers = Organization::factory()->create([
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
    $seniorEngineers->domains()->attach($domain->id);

    // Create CSV with various users
    $csv = "Email,Department,Level\n";
    $csv .= "senior-eng@company.com,Engineering,Senior\n"; // Should match Senior Engineers
    $csv .= "junior-eng@company.com,Engineering,Junior\n"; // Should match Engineering Team
    $csv .= "senior-sales@company.com,Sales,Senior\n"; // Should match Senior Staff
    $csv .= "junior-sales@company.com,Sales,Junior\n"; // Should match nothing (fall to default)

    $filePath = storage_path('app/test-proactive-discovery.csv');
    file_put_contents($filePath, $csv);

    // Import
    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    // User 1: Senior Engineer - should be assigned to Senior Engineers org (most specific match)
    $seniorEng = User::where('email', 'senior-eng@company.com')->first();
    expect($seniorEng)->not->toBeNull();
    expect($seniorEng->organizations->pluck('id')->toArray())->toContain($seniorEngineers->id);

    // Verify custom fields were saved correctly
    expect($seniorEng->customFieldValues()->where('cm_custom_field_id', $departmentField->id)->first()->value)
        ->toBe('Engineering');
    expect($seniorEng->customFieldValues()->where('cm_custom_field_id', $levelField->id)->first()->value)
        ->toBe('Senior');

    // User 2: Junior Engineer - should be in Engineering Team
    $juniorEng = User::where('email', 'junior-eng@company.com')->first();
    expect($juniorEng)->not->toBeNull();
    expect($juniorEng->organizations->pluck('id')->toArray())->toContain($engineeringOrg->id);

    // User 3: Senior Sales - should be in Senior Staff
    $seniorSales = User::where('email', 'senior-sales@company.com')->first();
    expect($seniorSales)->not->toBeNull();
    expect($seniorSales->organizations->pluck('id')->toArray())->toContain($seniorOrg->id);

    // User 4: Junior Sales - should stay in Company General (no matching conditional rules)
    $juniorSales = User::where('email', 'junior-sales@company.com')->first();
    expect($juniorSales)->not->toBeNull();

    // Junior Sales should be in Company General (the base org for the domain, since no conditional rules match)
    expect($juniorSales->organizations->pluck('id')->toArray())->toContain($regularOrg->id);

    // Verify junior sales is NOT in any conditional org
    expect($juniorSales->organizations->pluck('id')->toArray())->not->toContain($engineeringOrg->id);
    expect($juniorSales->organizations->pluck('id')->toArray())->not->toContain($seniorOrg->id);
    expect($juniorSales->organizations->pluck('id')->toArray())->not->toContain($seniorEngineers->id);

    @unlink($filePath);
});

test('proactive discovery respects domain boundaries for conditional orgs', function () {
    // This test verifies that users are NOT assigned to conditional orgs if their domain doesn't match
    // even if they meet all the conditional rules

    $departmentField = CmCustomField::factory()->create([
        'field_key' => 'Department',
        'field_name' => 'Department',
        'data_type' => 'text',
        'is_active' => true,
    ]);

    // Domain with associated conditional org
    $domain1 = Domain::factory()->create(['domain' => 'companyA.com']);

    // Domain with NO associated organizations
    $domain2 = Domain::factory()->create(['domain' => 'companyB.com']);

    // Conditional org for Engineering, ONLY associated with domain1
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
    $engineeringOrg->domains()->attach($domain1->id); // Only domain1

    // Create CSV with engineers from both domains
    $csv = "Email,Department\n";
    $csv .= "jane@companyB.com,Engineering\n"; // Domain does NOT match Engineering Team

    $filePath = storage_path('app/test-domain-boundary.csv');
    file_put_contents($filePath, $csv);

    $service = new CampaignMonitorImportService();
    $result = $service->importFromCsv($filePath);

    expect($result['success'])->toBeTrue();

    // Jane from companyB.com should NOT be in Engineering Team (domain doesn't match)
    // even though she has Department=Engineering
    $jane = User::where('email', 'jane@companyB.com')->first();
    expect($jane)->not->toBeNull();
    expect($jane->organizations->pluck('id')->toArray())->not->toContain($engineeringOrg->id);

    // Jane should be in Default Organization since her domain has no organizations
    $defaultOrg = Organization::where('name', 'Default Organization')->first();
    expect($defaultOrg)->not->toBeNull();
    expect($jane->organizations->pluck('id')->toArray())->toContain($defaultOrg->id);

    @unlink($filePath);
});
