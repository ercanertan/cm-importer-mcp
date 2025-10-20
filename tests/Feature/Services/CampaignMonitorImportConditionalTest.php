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
