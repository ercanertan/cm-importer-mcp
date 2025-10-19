<?php

use App\Jobs\SyncOrganizationConditionalJob;
use App\Livewire\Admin\Organizations\OrganizationManager;
use App\Models\CmCustomField;
use App\Models\Domain;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('it can open advanced create modal', function () {
    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->assertSet('showAdvancedCreateModal', true)
        ->assertSet('conditionLogic', 'AND')
        ->assertSet('conditions', [])
        ->assertSet('is_active', true);
});

test('it can close advanced create modal', function () {
    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->set('name', 'Test Org')
        ->call('closeAdvancedCreateModal')
        ->assertSet('showAdvancedCreateModal', false)
        ->assertSet('name', '');
});

test('it can add and remove conditions', function () {
    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->call('addCondition')
        ->assertCount('conditions', 1)
        ->call('addCondition')
        ->assertCount('conditions', 2)
        ->call('removeCondition', 0)
        ->assertCount('conditions', 1);
});

test('it validates required fields for advanced create', function () {
    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->call('createAdvancedOrganization')
        ->assertHasErrors([
            'name' => 'required',
            'domains' => 'required',
            'conditions' => 'required',
        ]);
});

test('it validates condition fields', function () {
    $customField = CmCustomField::factory()->create([
        'field_name' => 'Department',
        'is_active' => true,
    ]);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->set('name', 'Test Org')
        ->set('domains', 'test.com')
        ->call('addCondition')
        ->set('conditions.0.field_id', '') // Invalid
        ->set('conditions.0.operator', 'equals')
        ->call('createAdvancedOrganization')
        ->assertHasErrors(['conditions.0.field_id']);
});

test('it creates advanced organization with conditional rules', function () {
    Queue::fake();

    $customField = CmCustomField::factory()->create([
        'field_name' => 'Department',
        'is_active' => true,
    ]);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->set('name', 'Advanced Test Org')
        ->set('description', 'Test Description')
        ->set('is_active', true)
        ->set('domains', 'test.com, example.com')
        ->set('conditionLogic', 'AND')
        ->call('addCondition')
        ->set('conditions.0.field_id', $customField->id)
        ->set('conditions.0.operator', 'equals')
        ->set('conditions.0.value', 'Engineering')
        ->call('createAdvancedOrganization')
        ->assertHasNoErrors();

    expect(Organization::where('name', 'Advanced Test Org')->exists())->toBeTrue();

    $organization = Organization::where('name', 'Advanced Test Org')->first();

    expect($organization->conditional_rules)->not->toBeNull()
        ->and($organization->conditional_rules['logic'])->toBe('AND')
        ->and($organization->conditional_rules['conditions'])->toHaveCount(1)
        ->and($organization->conditional_rules['conditions'][0]['field_id'])->toBe($customField->id)
        ->and($organization->conditional_rules['conditions'][0]['operator'])->toBe('equals')
        ->and($organization->conditional_rules['conditions'][0]['value'])->toBe('Engineering');

    Queue::assertPushed(SyncOrganizationConditionalJob::class);
});

test('it creates organization with or logic', function () {
    Queue::fake();

    $field1 = CmCustomField::factory()->create(['field_name' => 'Department', 'is_active' => true]);
    $field2 = CmCustomField::factory()->create(['field_name' => 'Role', 'is_active' => true]);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->set('name', 'OR Logic Org')
        ->set('domains', 'test.com')
        ->set('conditionLogic', 'OR')
        ->call('addCondition')
        ->call('addCondition')
        ->set('conditions.0.field_id', $field1->id)
        ->set('conditions.0.operator', 'equals')
        ->set('conditions.0.value', 'Engineering')
        ->set('conditions.1.field_id', $field2->id)
        ->set('conditions.1.operator', 'equals')
        ->set('conditions.1.value', 'Manager')
        ->call('createAdvancedOrganization');

    $organization = Organization::where('name', 'OR Logic Org')->first();
    expect($organization->conditional_rules['logic'])->toBe('OR')
        ->and($organization->conditional_rules['conditions'])->toHaveCount(2);
});

test('it can open advanced edit modal for conditional organization', function () {
    $customField = CmCustomField::factory()->create(['field_name' => 'Department', 'is_active' => true]);

    $organization = Organization::factory()->create([
        'name' => 'Test Conditional Org',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $customField->id,
                    'operator' => 'equals',
                    'value' => 'Engineering',
                ],
            ],
        ],
    ]);

    $domain = Domain::create(['domain' => 'test.com']);
    $organization->domains()->attach($domain->id);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedEditModal', $organization->id)
        ->assertSet('showAdvancedEditModal', true)
        ->assertSet('name', 'Test Conditional Org')
        ->assertSet('conditionLogic', 'AND')
        ->assertCount('conditions', 1)
        ->assertSet('conditions.0.field_id', $customField->id)
        ->assertSet('conditions.0.operator', 'equals')
        ->assertSet('conditions.0.value', 'Engineering');
});

test('it cannot open advanced edit for regular organization', function () {
    $organization = Organization::factory()->create([
        'name' => 'Regular Org',
        'conditional_rules' => null,
    ]);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedEditModal', $organization->id)
        ->assertSet('showAdvancedEditModal', false);

    // Verify the organization doesn't have conditional rules
    expect($organization->hasConditionalRules())->toBeFalse();
});

test('it can update advanced organization', function () {
    Queue::fake();

    $field1 = CmCustomField::factory()->create(['field_name' => 'Department', 'is_active' => true]);
    $field2 = CmCustomField::factory()->create(['field_name' => 'Role', 'is_active' => true]);

    $organization = Organization::factory()->create([
        'name' => 'Original Name',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                [
                    'field_id' => $field1->id,
                    'operator' => 'equals',
                    'value' => 'Engineering',
                ],
            ],
        ],
    ]);

    Domain::create(['domain' => 'test.com']);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedEditModal', $organization->id)
        ->set('name', 'Updated Name')
        ->set('description', 'Updated Description')
        ->set('domains', 'updated.com')
        ->set('conditionLogic', 'OR')
        ->call('addCondition')
        ->set('conditions.1.field_id', $field2->id)
        ->set('conditions.1.operator', 'contains')
        ->set('conditions.1.value', 'Manager')
        ->call('updateAdvancedOrganization')
        ->assertHasNoErrors();

    $organization->refresh();

    expect($organization->name)->toBe('Updated Name')
        ->and($organization->description)->toBe('Updated Description')
        ->and($organization->conditional_rules['logic'])->toBe('OR')
        ->and($organization->conditional_rules['conditions'])->toHaveCount(2);

    Queue::assertPushed(SyncOrganizationConditionalJob::class);
});

test('it displays edit advanced button for conditional organizations', function () {
    $conditionalOrg = Organization::factory()->create([
        'name' => 'Conditional Org',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [],
        ],
    ]);

    $regularOrg = Organization::factory()->create([
        'name' => 'Regular Org',
        'conditional_rules' => null,
    ]);

    Livewire::test(OrganizationManager::class)
        ->assertSee('Conditional Org')
        ->assertSee('Regular Org');

    expect($conditionalOrg->hasConditionalRules())->toBeTrue()
        ->and($regularOrg->hasConditionalRules())->toBeFalse();
});

test('it supports all operators', function () {
    Queue::fake();

    $customField = CmCustomField::factory()->create(['field_name' => 'Status', 'is_active' => true]);

    $operators = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'starts_with',
        'ends_with',
        'is_empty',
        'is_not_empty',
    ];

    foreach ($operators as $operator) {
        Livewire::test(OrganizationManager::class)
            ->call('openAdvancedCreateModal')
            ->set('name', "Org with {$operator}")
            ->set('domains', 'test.com')
            ->call('addCondition')
            ->set('conditions.0.field_id', $customField->id)
            ->set('conditions.0.operator', $operator)
            ->set('conditions.0.value', 'test')
            ->call('createAdvancedOrganization')
            ->assertHasNoErrors();

        $org = Organization::where('name', "Org with {$operator}")->first();
        expect($org->conditional_rules['conditions'][0]['operator'])->toBe($operator);
    }
});

test('it validates unique organization name on create', function () {
    Organization::factory()->create(['name' => 'Existing Org']);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal')
        ->set('name', 'Existing Org')
        ->set('domains', 'test.com')
        ->call('addCondition')
        ->call('createAdvancedOrganization')
        ->assertHasErrors(['name' => 'unique']);
});

test('it validates unique organization name on update', function () {
    $field = CmCustomField::factory()->create(['is_active' => true]);

    Organization::factory()->create(['name' => 'Existing Org']);

    $orgToUpdate = Organization::factory()->create([
        'name' => 'Org To Update',
        'conditional_rules' => [
            'logic' => 'AND',
            'conditions' => [
                ['field_id' => $field->id, 'operator' => 'equals', 'value' => 'test'],
            ],
        ],
    ]);

    Livewire::test(OrganizationManager::class)
        ->call('openAdvancedEditModal', $orgToUpdate->id)
        ->set('name', 'Existing Org')
        ->set('domains', 'test.com')
        ->call('updateAdvancedOrganization')
        ->assertHasErrors(['name' => 'unique']);
});

test('it loads available custom fields', function () {
    $activeField = CmCustomField::factory()->create([
        'field_name' => 'Active Field',
        'is_active' => true,
    ]);

    $inactiveField = CmCustomField::factory()->create([
        'field_name' => 'Inactive Field',
        'is_active' => false,
    ]);

    $component = Livewire::test(OrganizationManager::class)
        ->call('openAdvancedCreateModal');

    $availableFields = $component->availableCustomFields;

    expect($availableFields->contains($activeField))->toBeTrue()
        ->and($availableFields->contains($inactiveField))->toBeFalse();
});
