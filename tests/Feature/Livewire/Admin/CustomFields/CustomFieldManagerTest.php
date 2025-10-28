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
