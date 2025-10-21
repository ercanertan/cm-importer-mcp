<?php

use App\Livewire\Admin\Users\UserCustomFieldsEditor;
use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create();
    actingAs($this->admin);

    $this->targetUser = User::factory()->create([
        'fullname' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

describe('UserCustomFieldsEditor - Component Rendering', function () {
    test('it renders successfully', function () {
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertStatus(200);
    });

    test('it displays user information', function () {
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSee('John Doe')
            ->assertSee('john@example.com');
    });

    test('it displays all active custom fields', function () {
        $userEditableField = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $adminOnlyField = CmCustomField::create([
            'field_key' => 'internal_notes',
            'field_name' => 'Internal Notes',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        // Admin should see both fields
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSee('Biography')
            ->assertSee('Internal Notes');
    });

    test('it does not display inactive fields', function () {
        $activeField = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $inactiveField = CmCustomField::create([
            'field_key' => 'old_field',
            'field_name' => 'Old Field',
            'data_type' => 'text',
            'is_active' => false,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSee('Biography')
            ->assertDontSee('Old Field');
    });

    test('it shows empty state when no active fields exist', function () {
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSee('No active custom fields are available');
    });

    test('it shows field editability badges', function () {
        $userEditableField = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $adminOnlyField = CmCustomField::create([
            'field_key' => 'internal_notes',
            'field_name' => 'Internal Notes',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSee('User Editable')
            ->assertSee('Admin Only');
    });
});

describe('UserCustomFieldsEditor - Loading Existing Values', function () {
    test('it loads existing field values for target user', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Software Developer',
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSet("fieldValues.{$field->id}", 'Software Developer');
    });

    test('it initializes empty values for fields without existing data', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSet("fieldValues.{$field->id}", '');
    });

    test('it does not load values from other users', function () {
        $otherUser = User::factory()->create();

        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        // Create value for other user
        CmCustomFieldValue::create([
            'user_id' => $otherUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Other User Bio',
        ]);

        // Target user should see empty value
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSet("fieldValues.{$field->id}", '');
    });
});

describe('UserCustomFieldsEditor - Saving Field Values', function () {
    test('it saves text field value for target user', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Software Developer')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value)->not()->toBeNull();
        expect($value->value)->toBe('Software Developer');
    });

    test('it saves admin-only fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'internal_notes',
            'field_name' => 'Internal Notes',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Admin notes here')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('Admin notes here');
    });

    test('it saves number field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => 'number',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", '12345')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('12345');
    });

    test('it saves date field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => 'date',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", '2024-01-15')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('2024-01-15');
    });

    test('it saves multi_select field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Laravel')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('Laravel');
    });

    test('it updates existing field value', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Old Value',
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'New Value')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('New Value');
    });

    test('it deletes field value when empty', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Old Value',
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", '')
            ->call('save')
            ->assertHasNoErrors();

        $exists = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->exists();

        expect($exists)->toBeFalse();
    });

    test('it saves multiple fields at once', function () {
        $field1 = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        $field2 = CmCustomField::create([
            'field_key' => 'internal_notes',
            'field_name' => 'Internal Notes',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field1->id}", 'Software Developer')
            ->set("fieldValues.{$field2->id}", 'Good employee')
            ->call('save')
            ->assertHasNoErrors();

        $value1 = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field1->id)
            ->first();

        $value2 = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field2->id)
            ->first();

        expect($value1->value)->toBe('Software Developer');
        expect($value2->value)->toBe('Good employee');
    });
});

describe('UserCustomFieldsEditor - Validation', function () {
    test('it validates number fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => 'number',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'not-a-number')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it validates date fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => 'date',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'not-a-date')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it allows valid number values', function () {
        $field = CmCustomField::create([
            'field_key' => 'employee_id',
            'field_name' => 'Employee ID',
            'data_type' => 'number',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", '12345')
            ->call('save')
            ->assertHasNoErrors();
    });

    test('it allows valid date values', function () {
        $field = CmCustomField::create([
            'field_key' => 'hire_date',
            'field_name' => 'Hire Date',
            'data_type' => 'date',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", '2024-01-15')
            ->call('save')
            ->assertHasNoErrors();
    });
});

describe('UserCustomFieldsEditor - User Isolation', function () {
    test('it only affects the target user', function () {
        $otherUser = User::factory()->create();

        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        // Create value for other user
        CmCustomFieldValue::create([
            'user_id' => $otherUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Other User Bio',
        ]);

        // Save value for target user
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Target User Bio')
            ->call('save');

        // Check target user has the new value
        $targetValue = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($targetValue->value)->toBe('Target User Bio');

        // Check other user still has their original value
        $otherValue = CmCustomFieldValue::where('user_id', $otherUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($otherValue->value)->toBe('Other User Bio');
    });

    test('it does not affect admin user values', function () {
        $field = CmCustomField::create([
            'field_key' => 'bio',
            'field_name' => 'Biography',
            'data_type' => 'text',
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        // Create value for admin
        CmCustomFieldValue::create([
            'user_id' => $this->admin->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Admin Bio',
        ]);

        // Save value for target user
        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Target User Bio')
            ->call('save');

        // Check admin still has their original value
        $adminValue = CmCustomFieldValue::where('user_id', $this->admin->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($adminValue->value)->toBe('Admin Bio');
    });
});

describe('UserCustomFieldsEditor - Multi-Select with Allow Multiple', function () {
    test('it saves array values for multi_select with allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", ['PHP', 'Laravel', 'JavaScript'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value)->not()->toBeNull();
        expect($value->value)->toBeArray();
        expect($value->value)->toBe(['PHP', 'Laravel', 'JavaScript']);
    });

    test('it saves single string value for multi_select without allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => 'multi_select',
            'allow_multiple' => false,
            'options' => ['Sales', 'Engineering', 'Marketing'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Engineering')
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe('Engineering');
        expect($value->value)->toBeString();
    });

    test('it loads array values for multi_select with allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->assertSet("fieldValues.{$field->id}", ['PHP', 'Laravel']);
    });

    test('it deletes empty array values', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", [])
            ->call('save')
            ->assertHasNoErrors();

        $exists = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->exists();

        expect($exists)->toBeFalse();
    });

    test('it updates array values for multi_select with allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript', 'Vue.js'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        CmCustomFieldValue::create([
            'user_id' => $this->targetUser->id,
            'cm_custom_field_id' => $field->id,
            'value' => ['PHP', 'Laravel'],
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", ['JavaScript', 'Vue.js'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBe(['JavaScript', 'Vue.js']);
    });

    test('it validates array type for multi_select with allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", 'Not an array')
            ->call('save')
            ->assertHasErrors(["fieldValues.{$field->id}"]);
    });

    test('it handles single item array for multi_select with allow_multiple', function () {
        $field = CmCustomField::create([
            'field_key' => 'skills',
            'field_name' => 'Skills',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['PHP', 'Laravel', 'JavaScript'],
            'is_active' => true,
            'is_user_editable' => true,
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", ['PHP'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBeArray();
        expect($value->value)->toBe(['PHP']);
    });

    test('admin can manage multi_select arrays for admin-only fields', function () {
        $field = CmCustomField::create([
            'field_key' => 'internal_tags',
            'field_name' => 'Internal Tags',
            'data_type' => 'multi_select',
            'allow_multiple' => true,
            'options' => ['VIP', 'Beta Tester', 'Early Adopter'],
            'is_active' => true,
            'is_user_editable' => false, // Admin only
        ]);

        Livewire::test(UserCustomFieldsEditor::class, ['userId' => $this->targetUser->id])
            ->set("fieldValues.{$field->id}", ['VIP', 'Beta Tester'])
            ->call('save')
            ->assertHasNoErrors();

        $value = CmCustomFieldValue::where('user_id', $this->targetUser->id)
            ->where('cm_custom_field_id', $field->id)
            ->first();

        expect($value->value)->toBeArray();
        expect($value->value)->toBe(['VIP', 'Beta Tester']);
    });
});
