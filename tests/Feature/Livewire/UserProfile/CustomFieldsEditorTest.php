<?php

use App\Enums\CustomFieldTypes;
use App\Livewire\UserProfile\CustomFieldsEditor;
use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

describe('CustomFieldsEditor - Component Rendering', function () {
    test('it renders successfully', function () {
        Livewire::test(CustomFieldsEditor::class)
            ->assertStatus(200);
    });

    test('it displays only user-editable custom fields', function () {
        $userEditableField = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $adminOnlyField = CmCustomField::create([
            'field_key' => 'internal_notes',
            'field_name' => 'Internal Notes',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->assertSee('Biography')
            ->assertDontSee('Internal Notes');
    });

    test('it does not display inactive fields', function () {
        $activeField = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $inactiveField = CmCustomField::create([
            'field_key' => 'old_field',
            'field_name' => 'Old Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => false,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->assertSee('Biography')
            ->assertDontSee('Old Field');
    });

    test('it shows empty state when no user-editable fields exist', function () {
        Livewire::test(CustomFieldsEditor::class)
            ->assertSee('No editable custom fields are available');
    });
});

describe('CustomFieldsEditor - Loading Existing Values', function () {
    test('it loads existing field values', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->user->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Software Developer',
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->assertSet("fieldValues.{$field->id}", 'Software Developer');
    });

    test('it initializes empty values for fields without existing data', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->assertSet("fieldValues.{$field->id}", '');
    });
});

describe('CustomFieldsEditor - Saving Field Values', function () {
    test('it saves text field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'Software Developer')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value)->not()->toBeNull();
        expect($value->value)->toBe('Software Developer');
    });

    test('it saves number field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => CustomFieldTypes::Number,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", '12345')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('12345');
    });

    test('it saves date field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => CustomFieldTypes::Date,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", '2024-01-15')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('2024-01-15');
    });

    test('it saves multi_select field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectOne,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'Laravel')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('Laravel');
    });

    test('it updates existing field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->user->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Old Value',
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'New Value')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('New Value');
    });

    test('it deletes field value when empty', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->user->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Old Value',
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", '')
            ->call('save')
            ->assertHasNoErrors();

        $exists = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->exists();

        expect($exists)->toBeFalse();
    });

    test('it saves multiple fields at once', function () {
        $field1 = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $field2 = CmCustomField::create([
            'field_key' => 'location',
            'field_name' => 'Location',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field1->id}", 'Software Developer')
            ->set("fieldValues.{$field2->id}", 'San Francisco')
            ->call('save')
            ->assertHasNoErrors();

        $value1 = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field1->id)
            ->first();

        $value2 = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field2->id)
            ->first();

        expect($value1->value)->toBe('Software Developer');
        expect($value2->value)->toBe('San Francisco');
    });
});

describe('CustomFieldsEditor - Validation', function () {
    test('it validates number fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => CustomFieldTypes::Number,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'not-a-number')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it validates date fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => CustomFieldTypes::Date,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'not-a-date')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it allows valid number values', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => CustomFieldTypes::Number,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", '12345')
            ->call('save')
            ->assertHasNoErrors();
    });

    test('it allows valid date values', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => CustomFieldTypes::Date,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", '2024-01-15')
            ->call('save')
            ->assertHasNoErrors();
    });
});

describe('CustomFieldsEditor - Flash Messages', function () {
    test('it shows success message after saving', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'Software Developer')
            ->call('save')
            ->assertHasNoErrors();

        // Verify the value was actually saved
        $value = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value)->not()->toBeNull();
    });
});

describe('CustomFieldsEditor - User Isolation', function () {
    test('it only saves values for the authenticated user', function () {
        $otherUser = User::factory()->create();

        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'My Bio')
            ->call('save');

        // Check authenticated user has the value
        $authUserValue = CmCustomFieldValue::where('user_id', $this->user->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($authUserValue)->not()->toBeNull();
        expect($authUserValue->value)->toBe('My Bio');

        // Check other user does not have the value
        $otherUserValue = CmCustomFieldValue::where('user_id', $otherUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->exists();

        expect($otherUserValue)->toBeFalse();
    });

    test('it only loads values for the authenticated user', function () {
        $otherUser = User::factory()->create();

        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        // Create value for other user
        CmCustomFieldValue::create([
            'user_id' => $otherUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Other User Bio',
        ]);

        // Authenticated user should see empty value
        Livewire::test(CustomFieldsEditor::class)
            ->assertSet("fieldValues.{$field->id}", '');
    });
});

describe('CustomFieldsEditor - Multi-Select', function () {
    test('it saves array values for MultiSelectMany', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", ['PHP', 'Laravel', 'JavaScript'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value)->not()->toBeNull();
        expect($value->value)->toBeArray();
        expect($value->value)->toBe(['PHP', 'Laravel', 'JavaScript']);
    });

    test('it saves single string value for MultiSelectOne', function () {
        $field = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::MultiSelectOne,
            'options' => ['Sales', 'Engineering', 'Marketing'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'Engineering')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('Engineering');
        expect($value->value)->toBeString();
    });

    test('it loads array values for MultiSelectMany', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => auth()->id(),
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->assertSet("fieldValues.{$field->id}", ['PHP', 'Laravel']);
    });

    test('it deletes empty array values', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => auth()->id(),
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", [])
            ->call('save')
            ->assertHasNoErrors();

        $exists = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->exists();

        expect($exists)->toBeFalse();
    });

    test('it updates array values for MultiSelectMany', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => auth()->id(),
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", ['JavaScript', 'Vue.js'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe(['JavaScript', 'Vue.js']);
    });

    test('it validates array type for MultiSelectMany', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", 'Not an array')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it handles single item array for MultiSelectMany', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => CustomFieldTypes::MultiSelectMany,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(CustomFieldsEditor::class)
            ->set("fieldValues.{$field->id}", ['PHP'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', auth()->id())
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBeArray();
        expect($value->value)->toBe(['PHP']);
    });
});
