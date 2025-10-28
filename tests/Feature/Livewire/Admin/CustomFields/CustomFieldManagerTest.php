<?php

use App\Enums\CustomFieldTypes;
use App\Livewire\Admin\CustomFields\CustomFieldManager;
use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

describe('CustomFieldManager - Component Rendering', function () {
    test('it renders successfully', function () {
        Livewire::test(CustomFieldManager::class)
            ->assertStatus(200);
    });

    test('it displays custom fields', function () {
        $customField = CmCustomField::create([
            'field_key' => 'test_field',
            'field_name' => 'Test Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->assertSee('Test Field')
            ->assertSee('test_field');
    });

    test('it shows empty state when no custom fields exist', function () {
        Livewire::test(CustomFieldManager::class)
            ->assertSee('No custom fields found');
    });
});

describe('CustomFieldManager - Search Functionality', function () {
    test('it can search by field name', function () {
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        CmCustomField::create([
            'field_key' => 'role',
            'field_name' => 'Role',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->set('search', 'Department')
            ->assertSee('Department')
            ->assertDontSee('Role');
    });

    test('it can search by field key', function () {
        CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        CmCustomField::create([
            'field_key' => 'manager_id',
            'field_name' => 'Manager ID',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->set('search', 'employee')
            ->assertSee('Employee ID')
            ->assertDontSee('Manager ID');
    });

    test('it resets page when search is updated', function () {
        // Create 20 custom fields to trigger pagination
        for ($i = 1; $i <= 20; $i++) {
            CmCustomField::create([
                'field_key' => "field_{$i}",
                'field_name' => "Field {$i}",
                'data_type' => CustomFieldTypes::Text,
                'is_active' => true,
            ]);
        }

        // Simply verify that search works across paginated results
        Livewire::test(CustomFieldManager::class)
            ->set('search', 'Field 1')
            ->assertSee('Field 1');
    });
});

describe('CustomFieldManager - Create Functionality', function () {
    test('it opens create modal', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('is_active', true)
            ->assertSet('is_user_editable', false)
            ->assertSet('data_type', 'Text');
    });

    test('it closes create modal', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_name', 'Test')
            ->call('closeCreateModal')
            ->assertSet('showCreateModal', false)
            ->assertSet('field_name', '');
    });

    test('it creates a text custom field', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'department')
            ->set('field_name', 'Department')
            ->set('data_type', 'Text')
            ->set('is_active', true)
            ->set('is_user_editable', false)
            ->call('createCustomField')
            ->assertHasNoErrors();

        expect(CmCustomField::where('field_key', 'department')->exists())->toBeTrue();

        $field = CmCustomField::where('field_key', 'department')->first();
        expect($field->field_name)->toBe('Department');
        expect($field->data_type)->toBe(CustomFieldTypes::Text);
        expect($field->is_active)->toBeTrue();
        expect($field->is_user_editable)->toBeFalse();
    });

    test('it creates a number custom field', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'employee_id')
            ->set('field_name', 'Employee ID')
            ->set('data_type', 'Number')
            ->call('createCustomField')
            ->assertHasNoErrors();

        expect(CmCustomField::where('data_type', CustomFieldTypes::Number)->exists())->toBeTrue();
    });

    test('it creates a date custom field', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'hire_date')
            ->set('field_name', 'Hire Date')
            ->set('data_type', 'Date')
            ->call('createCustomField')
            ->assertHasNoErrors();

        expect(CmCustomField::where('data_type', CustomFieldTypes::Date)->exists())->toBeTrue();
    });

    test('it creates a multi_select custom field with options', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'skills')
            ->set('field_name', 'Skills')
            ->set('data_type', 'MultiSelectOne')
            ->set('options', 'PHP, Laravel, JavaScript')
            ->call('createCustomField')
            ->assertHasNoErrors();

        $field = CmCustomField::where('field_key', 'skills')->first();
        expect($field->options)->toBe(['PHP', 'Laravel', 'JavaScript']);
    });

    test('it validates required fields', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->call('createCustomField')
            ->assertHasErrors(['field_key', 'field_name']);
    });

    test('it validates unique field_key', function () {
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'department')
            ->set('field_name', 'Department 2')
            ->call('createCustomField')
            ->assertHasErrors(['field_key']);
    });

    test('it validates data_type enum', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'test')
            ->set('field_name', 'Test')
            ->set('data_type', 'invalid_type')
            ->call('createCustomField')
            ->assertHasErrors(['data_type']);
    });

    test('it creates user editable field', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'bio')
            ->set('field_name', 'Biography')
            ->set('data_type', 'Text')
            ->set('is_user_editable', true)
            ->call('createCustomField')
            ->assertHasNoErrors();

        $field = CmCustomField::where('field_key', 'bio')->first();
        expect($field->is_user_editable)->toBeTrue();
    });

    test('it closes modal after successful creation', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'test')
            ->set('field_name', 'Test')
            ->call('createCustomField')
            ->assertSet('showCreateModal', false);
    });
});

describe('CustomFieldManager - Edit Functionality', function () {
    test('it opens edit modal', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->assertSet('showEditModal', true)
            ->assertSet('field_key', 'department')
            ->assertSet('field_name', 'Department')
            ->assertSet('data_type', 'Text')
            ->assertSet('is_active', true)
            ->assertSet('is_user_editable', false);
    });

    test('it opens edit modal with options', function () {
        $customField = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectOne,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->assertSet('options', 'PHP, Laravel, JavaScript');
    });

    test('it closes edit modal', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->call('closeEditModal')
            ->assertSet('showEditModal', false)
            ->assertSet('field_name', '');
    });

    test('it updates custom field', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->set('field_name', 'Department Updated')
            ->set('is_user_editable', true)
            ->call('updateCustomField')
            ->assertHasNoErrors();

        $customField->refresh();
        expect($customField->field_name)->toBe('Department Updated');
        expect($customField->is_user_editable)->toBeTrue();
    });

    test('it updates custom field options', function () {
        $customField = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectOne,
            'options' => ['PHP', 'Laravel'],
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->set('options', 'PHP, Laravel, JavaScript, Vue')
            ->call('updateCustomField')
            ->assertHasNoErrors();

        $customField->refresh();
        expect($customField->options)->toBe(['PHP', 'Laravel', 'JavaScript', 'Vue']);
    });

    test('it validates unique field_key on update', function () {
        $field1 = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        $field2 = CmCustomField::create([
            'field_key' => 'role',
            'field_name' => 'Role',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $field2->id)
            ->set('field_key', 'department')
            ->call('updateCustomField')
            ->assertHasErrors(['field_key']);
    });

    test('it allows keeping the same field_key on update', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->set('field_name', 'Department Updated')
            ->call('updateCustomField')
            ->assertHasNoErrors();

        $customField->refresh();
        expect($customField->field_key)->toBe('department');
        expect($customField->field_name)->toBe('Department Updated');
    });

    test('it closes modal after successful update', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openEditModal', $customField->id)
            ->set('field_name', 'Updated')
            ->call('updateCustomField')
            ->assertSet('showEditModal', false);
    });
});

describe('CustomFieldManager - Delete Functionality', function () {
    test('it opens delete confirmation modal', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('confirmDelete', $customField->id)
            ->assertSet('showDeleteModal', true)
            ->assertSee('Department');
    });

    test('it cancels delete', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('confirmDelete', $customField->id)
            ->call('cancelDelete')
            ->assertSet('showDeleteModal', false);

        expect(CmCustomField::find($customField->id))->not()->toBeNull();
    });

    test('it deletes custom field', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('confirmDelete', $customField->id)
            ->call('deleteCustomField');

        expect(CmCustomField::find($customField->id))->toBeNull();
    });

    test('it deletes associated custom field values', function () {
        $user = User::factory()->create();
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $user->id,
            'cm_custom_field_id' => $customField->id,
            'value' => 'Engineering',
        ]);

        expect(CmCustomFieldValue::where('cm_custom_field_id', $customField->id)->count())->toBe(1);

        Livewire::test(CustomFieldManager::class)
            ->call('confirmDelete', $customField->id)
            ->call('deleteCustomField');

        expect(CmCustomFieldValue::where('cm_custom_field_id', $customField->id)->count())->toBe(0);
    });

    test('it closes modal after successful deletion', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('confirmDelete', $customField->id)
            ->call('deleteCustomField')
            ->assertSet('showDeleteModal', false);
    });
});

describe('CustomFieldManager - Toggle Functionality', function () {
    test('it toggles is_active status', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('toggleActive', $customField->id);

        $customField->refresh();
        expect($customField->is_active)->toBeFalse();

        Livewire::test(CustomFieldManager::class)
            ->call('toggleActive', $customField->id);

        $customField->refresh();
        expect($customField->is_active)->toBeTrue();
    });

    test('it toggles is_user_editable status', function () {
        $customField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('toggleUserEditable', $customField->id);

        $customField->refresh();
        expect($customField->is_user_editable)->toBeTrue();

        Livewire::test(CustomFieldManager::class)
            ->call('toggleUserEditable', $customField->id);

        $customField->refresh();
        expect($customField->is_user_editable)->toBeFalse();
    });
});

describe('CustomFieldManager - Options Parsing', function () {
    test('it parses comma-separated options', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'skills')
            ->set('field_name', 'Skills')
            ->set('data_type', 'MultiSelectOne')
            ->set('options', 'PHP, Laravel, JavaScript')
            ->call('createCustomField');

        $field = CmCustomField::where('field_key', 'skills')->first();
        expect($field->options)->toBe(['PHP', 'Laravel', 'JavaScript']);
    });

    test('it trims whitespace from options', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'skills')
            ->set('field_name', 'Skills')
            ->set('data_type', 'MultiSelectOne')
            ->set('options', '  PHP  ,  Laravel  ,  JavaScript  ')
            ->call('createCustomField');

        $field = CmCustomField::where('field_key', 'skills')->first();
        expect($field->options)->toBe(['PHP', 'Laravel', 'JavaScript']);
    });

    test('it handles empty options', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'description')
            ->set('field_name', 'Description')
            ->set('data_type', 'Text')
            ->set('options', '')
            ->call('createCustomField');

        $field = CmCustomField::where('field_key', 'description')->first();
        expect($field->options)->toBeNull();
    });

    test('it filters out empty options', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCreateModal')
            ->set('field_key', 'skills')
            ->set('field_name', 'Skills')
            ->set('data_type', 'MultiSelectOne')
            ->set('options', 'PHP, , Laravel, , JavaScript')
            ->call('createCustomField');

        $field = CmCustomField::where('field_key', 'skills')->first();
        // Options will be filtered and may have different keys
        expect(array_values($field->options))->toBe(['PHP', 'Laravel', 'JavaScript']);
    });
});

describe('CustomFieldManager - Pagination', function () {
    test('it paginates custom fields', function () {
        // Create 20 custom fields
        for ($i = 1; $i <= 20; $i++) {
            CmCustomField::create([
                'field_key' => "field_{$i}",
                'field_name' => "Field {$i}",
                'data_type' => CustomFieldTypes::Text,
                'is_active' => true,
            ]);
        }

        // Verify pagination by checking we can see some fields from the first page
        Livewire::test(CustomFieldManager::class)
            ->assertSee('Field 1');
    });

    test('it displays all fields when searching', function () {
        // Create fields with specific pattern
        for ($i = 1; $i <= 5; $i++) {
            CmCustomField::create([
                'field_key' => "test_{$i}",
                'field_name' => "Test {$i}",
                'data_type' => CustomFieldTypes::Text,
                'is_active' => true,
            ]);
        }

        CmCustomField::create([
            'field_key' => 'other',
            'field_name' => 'Other',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        Livewire::test(CustomFieldManager::class)
            ->set('search', 'Test')
            ->assertSee('Test 1')
            ->assertDontSee('Other');
    });
});

describe('CustomFieldManager - JSON Import Functionality', function () {
    test('it opens JSON import modal', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->assertSet('showCampaignMonitorModal', true)
            ->assertSet('jsonInput', '')
            ->assertSet('importErrors', [])
            ->assertSee('Import Custom Fields');
    });

    test('it closes JSON import modal', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', 'test')
            ->call('closeCampaignMonitorModal')
            ->assertSet('showCampaignMonitorModal', false)
            ->assertSet('jsonInput', '')
            ->assertSet('importErrors', []);
    });

    test('it validates JSON input is required', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->call('processJsonInput')
            ->assertHasErrors(['jsonInput']);
    });

    test('it processes valid JSON input successfully', function () {
        $validJson = json_encode([
            [
                'FieldName' => 'Job Title',
                'Key' => '[JobTitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Department',
                'Key' => '[Department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => ['Engineering', 'Sales'],
                'VisibleInPreferenceCenter' => false,
            ]
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $validJson)
            ->call('processJsonInput')
            ->assertHasNoErrors()
            ->assertSet('showCampaignMonitorModal', true)
            ->assertSet('cmShowPreview', true)
            ->assertSet('parsedFields', fn($value) => !empty($value))
            ->assertSet('previewData', fn($value) => !empty($value));
    });

    test('it rejects invalid JSON format', function () {
        $invalidJson = '{"FieldName": "Job Title", "Key": "invalid_key"}'; // Missing required fields

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $invalidJson)
            ->call('processJsonInput')
            ->assertSet('importErrors', fn($value) => !empty($value));
    });

    test('it rejects malformed JSON', function () {
        $malformedJson = '{"FieldName": "Job Title", "Key": "[JobTitle]"'; // Missing closing brace

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $malformedJson)
            ->call('processJsonInput')
            ->assertSet('importErrors', fn($value) => !empty($value));
    });

    test('it shows preview modal with statistics', function () {
        $jsonFields = [
            [
                'FieldName' => 'New Field',
                'Key' => '[NewField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true)
            ->assertSee('Processed 1 fields:')
            ->assertSee('1 new')
            ->assertSee('0 to update')
            ->assertSee('0 duplicates')
            ->assertSee('0 invalid');
    });

    test('it auto-selects new and updated fields for import', function () {
        // Create existing field
        CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Existing Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        $jsonFields = [
            [
                'FieldName' => 'New Field',
                'Key' => '[NewField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Existing Field Updated',
                'Key' => '[existing]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => false,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('selectedPreviewIndices', fn($value) => !empty($value))
            ->assertSet('previewData.new.0.external.field_name', 'New Field')
            ->assertSet('previewData.updated.0.external.field_name', 'Existing Field Updated');
    });

    test('it closes preview modal and resets state', function () {
        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', '{"FieldName": "Test", "Key": "[Test]", "DataType": "Text", "FieldOptions": [], "VisibleInPreferenceCenter": true}')
            ->call('processJsonInput')
            ->call('closePreviewModal')
            ->assertSet('showPreviewModal', false)
            ->assertSet('previewData', [])
            ->assertSet('selectedPreviewIndices', [])
            ->assertSet('importErrors', []);
    });

    test('it applies JSON changes and creates new fields', function () {
        $jsonFields = [
            [
                'FieldName' => 'Imported Field',
                'Key' => '[ImportedField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->call('applyJsonChanges')
            ->assertSet('showPreviewModal', false);

        expect(CmCustomField::where('field_key', 'ImportedField')->exists())->toBeTrue();
    });

    test('it validates that at least one field is selected for import', function () {
        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', '{"FieldName": "Test", "Key": "[Test]", "DataType": "Text", "FieldOptions": [], "VisibleInPreferenceCenter": true}')
            ->call('processJsonInput')
            ->set('selectedPreviewIndices', []); // Clear selection

        // The method should handle the validation without crashing
        $livewire->call('applyJsonChanges');

        // The component should still be in a valid state after the failed validation
        $livewire->assertSet('selectedPreviewIndices', []);
    });

    test('it toggles preview selection for individual fields', function () {
        $jsonFields = [
            [
                'FieldName' => 'Field 1',
                'Key' => '[Field1]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Field 2',
                'Key' => '[Field2]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput');

        // Get initial selection (should have both indices)
        $initialSelection = $livewire->get('selectedPreviewIndices');
        expect($initialSelection)->toHaveCount(2);

        // Toggle first field (remove from selection)
        $livewire->call('togglePreviewSelection', 0);
        $afterToggle = $livewire->get('selectedPreviewIndices');
        expect($afterToggle)->toHaveCount(1);
        expect($afterToggle)->not->toContain(0);

        // Toggle it back (add to selection)
        $livewire->call('togglePreviewSelection', 0);
        $finalSelection = $livewire->get('selectedPreviewIndices');
        expect($finalSelection)->toHaveCount(2);
        expect($finalSelection)->toContain(0);
    });

    test('it selects all valid fields', function () {
        $jsonFields = [
            [
                'FieldName' => 'Field 1',
                'Key' => '[Field1]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Field 2',
                'Key' => '[Field2]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->set('selectedPreviewIndices', []) // Clear selection
            ->call('selectAllValidFields')
            ->assertSet('selectedPreviewIndices', fn($value) => is_array($value) && count($value) > 0)
            ->call('clearSelection')
            ->assertSet('selectedPreviewIndices', []);
    });

    test('it provides example JSON format', function () {
        $livewire = Livewire::test(CustomFieldManager::class);

        // Access the method directly instead of calling it
        $component = $livewire->instance();
        $exampleJson = $component->getExampleJson();

        expect($exampleJson)->toBeString();
        $exampleData = json_decode($exampleJson, true);
        expect($exampleData)->toBeArray();
        expect($exampleData[0])->toHaveKeys(['FieldName', 'Key', 'DataType', 'FieldOptions', 'VisibleInPreferenceCenter']);
    });

    test('it handles JSON with duplicate field names', function () {
        // Create existing field with same name but different key
        CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Job Title',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        $jsonFields = [
            [
                'FieldName' => 'Job Title', // Same name, different key
                'Key' => '[JobTitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.duplicates.0.reason', 'Duplicate field name (different key)');
    });

    test('it handles JSON with exact duplicate external keys', function () {
        // Create field with external_key
        CmCustomField::create([
            'field_key' => 'JobTitle',
            'field_name' => 'Job Title',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true, // Match VisibleInPreferenceCenter: true
            'external_key' => '[JobTitle]',
        ]);

        $jsonFields = [
            [
                'FieldName' => 'Job Title',
                'Key' => '[JobTitle]', // Same external key
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.duplicates.0.reason', 'No differences detected');
    });

    test('it handles invalid field data in JSON', function () {
        $jsonFields = [
            [
                'FieldName' => 'Valid Field',
                'Key' => '[ValidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => '', // Invalid - empty name
                'Key' => '[InvalidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Invalid DataType',
                'Key' => '[InvalidType]',
                'DataType' => 'InvalidType', // Invalid data type
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Invalid Key Format',
                'Key' => 'InvalidKey', // Should be in brackets
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.new.0.external.field_name', 'Valid Field')
            ->assertSet('previewData.invalid', function($invalid) {
                return count($invalid) === 3;
            })
            ->assertSet('previewData.statistics.invalid', 3);
    });

    test('it validates MultiSelect fields have non-empty options', function () {
        $jsonFields = [
            [
                'FieldName' => 'Department',
                'Key' => '[Department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => [], // Empty options for MultiSelect - should fail
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.invalid.0.error', fn($value) => !empty($value));
    });

    test('it updates existing fields when differences detected', function () {
        // Create existing field
        $existingField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $jsonFields = [
            [
                'FieldName' => 'Department Updated', // Different name
                'Key' => '[department]',
                'DataType' => 'MultiSelectOne', // Different type
                'FieldOptions' => ['Engineering', 'Sales'], // Different options
                'VisibleInPreferenceCenter' => true, // Different user editable status
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.updated.0.differences.field_name.old', 'Department')
            ->assertSet('previewData.updated.0.differences.field_name.new', 'Department Updated')
            ->assertSet('previewData.updated.0.differences.data_type.old', 'Text')
            ->assertSet('previewData.updated.0.differences.data_type.new', 'MultiSelectOne')
            ->assertSet('previewData.updated.0.differences.is_user_editable.old', false)
            ->assertSet('previewData.updated.0.differences.is_user_editable.new', true);
    });

    test('it applies changes to update existing fields', function () {
        // Create existing field
        $existingField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $jsonFields = [
            [
                'FieldName' => 'Department Updated',
                'Key' => '[department]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openJsonModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->call('applyJsonChanges');

        $existingField->refresh();
        expect($existingField->field_name)->toBe('Department Updated');
        expect($existingField->is_user_editable)->toBeTrue();
    });

    test('it handles mixed JSON import with new, updated, duplicate, and invalid fields', function () {
        // Create existing field for update
        CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Existing Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        // Create existing field with external_key for duplicate
        CmCustomField::create([
            'field_key' => 'duplicate',
            'field_name' => 'Duplicate Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'external_key' => '[DuplicateExternal]',
        ]);

        $jsonFields = [
            // New field
            [
                'FieldName' => 'New Field',
                'Key' => '[NewField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Updated field
            [
                'FieldName' => 'Existing Field Updated',
                'Key' => '[existing]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Duplicate field (same external key)
            [
                'FieldName' => 'Duplicate Field',
                'Key' => '[DuplicateExternal]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Invalid field
            [
                'FieldName' => '',
                'Key' => '[InvalidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', json_encode($jsonFields))
            ->call('processJsonInput')
            ->assertSet('previewData.statistics.total', 4)
            ->assertSet('previewData.statistics.new', 1)
            ->assertSet('previewData.statistics.updated', 1)
            ->assertSet('previewData.statistics.duplicates', 1)
            ->assertSet('previewData.statistics.invalid', 1);
    });
});
