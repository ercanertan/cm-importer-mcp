<?php

use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CmCustomFieldValue Model', function () {
    describe('factory and states', function () {
        it('creates custom field value with default state', function () {
            $value = CmCustomFieldValue::factory()->create();

            expect($value->value)->not->toBeNull();
            expect($value->user_id)->not->toBeNull();
            expect($value->cm_custom_field_id)->not->toBeNull();
        });

        it('creates value with text state', function () {
            $value = CmCustomFieldValue::factory()->withTextValue()->create();

            expect($value->value)->toBeString();
        });

        it('creates value with number state', function () {
            $value = CmCustomFieldValue::factory()->withNumberValue()->create();

            expect(is_numeric($value->value))->toBeTrue();
        });

        it('creates value with date state', function () {
            $value = CmCustomFieldValue::factory()->withDateValue()->create();

            // Should match date format YYYY-MM-DD
            expect(preg_match('/^\d{4}-\d{2}-\d{2}$/', $value->value))->toBe(1);
        });

        it('creates value with multi-select state', function () {
            $value = CmCustomFieldValue::factory()->withMultiSelectValue()->create();

            expect(str_contains($value->value, ','))->toBeTrue();
        });
    });

    describe('relationships', function () {
        it('belongs to user', function () {
            $user = User::factory()->create();
            $value = CmCustomFieldValue::factory()->create(['user_id' => $user->id]);

            expect($value->user)->toBeInstanceOf(User::class);
            expect($value->user->id)->toBe($user->id);
        });

        it('belongs to custom field', function () {
            $field = CmCustomField::factory()->create();
            $value = CmCustomFieldValue::factory()->create(['cm_custom_field_id' => $field->id]);

            expect($value->customField)->toBeInstanceOf(CmCustomField::class);
            expect($value->customField->id)->toBe($field->id);
        });

        it('eager loads relationships', function () {
            $value = CmCustomFieldValue::factory()->create();

            $loaded = CmCustomFieldValue::with(['user', 'customField'])->find($value->id);

            expect($loaded->relationLoaded('user'))->toBeTrue();
            expect($loaded->relationLoaded('customField'))->toBeTrue();
        });
    });

    describe('scopes', function () {
        it('filters by field key', function () {
            $field1 = CmCustomField::factory()->create(['field_key' => 'department']);
            $field2 = CmCustomField::factory()->create(['field_key' => 'role']);

            $value1 = CmCustomFieldValue::factory()->create(['cm_custom_field_id' => $field1->id]);
            CmCustomFieldValue::factory()->create(['cm_custom_field_id' => $field2->id]);

            $results = CmCustomFieldValue::byField('department')->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($value1->id);
        });

        it('filters by user', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $value1 = CmCustomFieldValue::factory()->create(['user_id' => $user1->id]);
            CmCustomFieldValue::factory()->create(['user_id' => $user2->id]);

            $results = CmCustomFieldValue::byUser($user1->id)->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($value1->id);
        });

        it('chains scopes', function () {
            $user = User::factory()->create();
            $field = CmCustomField::factory()->create(['field_key' => 'company']);

            $value = CmCustomFieldValue::factory()->create([
                'user_id' => $user->id,
                'cm_custom_field_id' => $field->id,
            ]);

            // Create noise data
            CmCustomFieldValue::factory()->create(['user_id' => $user->id]);
            CmCustomFieldValue::factory()->create(['cm_custom_field_id' => $field->id]);

            $result = CmCustomFieldValue::byUser($user->id)->byField('company')->first();

            expect($result->id)->toBe($value->id);
        });
    });

    describe('formatted value attribute', function () {
        it('formats text values as-is', function () {
            $field = CmCustomField::factory()->create(['data_type' => 'text']);
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'Some text value',
            ]);

            expect($value->formatted_value)->toBe('Some text value');
        });

        it('formats date values correctly', function () {
            $field = CmCustomField::factory()->date()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '2024-01-15',
            ]);

            expect($value->formatted_value)->toBe('2024-01-15');
        });

        it('formats date values with time', function () {
            $field = CmCustomField::factory()->date()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '2024-01-15 10:30:00',
            ]);

            expect($value->formatted_value)->toBe('2024-01-15');
        });

        it('handles invalid date format gracefully', function () {
            $field = CmCustomField::factory()->date()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'invalid-date',
            ]);

            // Should return original value if parsing fails
            expect($value->formatted_value)->toBe('invalid-date');
        });

        it('formats number values as float', function () {
            $field = CmCustomField::factory()->number()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '123.45',
            ]);

            expect($value->formatted_value)->toBe(123.45);
            expect($value->formatted_value)->toBeFloat();
        });

        it('handles non-numeric values in number fields', function () {
            $field = CmCustomField::factory()->number()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'not-a-number',
            ]);

            expect($value->formatted_value)->toBe('not-a-number');
        });

        it('formats multi-select values as array', function () {
            $field = CmCustomField::factory()->multiSelect()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'Option1,Option2,Option3',
            ]);

            $formatted = $value->formatted_value;

            expect($formatted)->toBeArray();
            expect($formatted)->toBe(['Option1', 'Option2', 'Option3']);
        });

        it('handles single-item multi-select values', function () {
            $field = CmCustomField::factory()->multiSelect()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'SingleOption',
            ]);

            $formatted = $value->formatted_value;

            expect($formatted)->toBeArray();
            expect($formatted)->toBe(['SingleOption']);
        });

        it('cascades delete when custom field is deleted', function () {
            $field = CmCustomField::factory()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'test value',
            ]);

            $valueId = $value->id;

            // Delete the field
            $field->delete();

            // Value should also be deleted due to CASCADE
            expect(CmCustomFieldValue::find($valueId))->toBeNull();
        });
    });

    describe('edge cases', function () {
        it('handles empty string values', function () {
            $field = CmCustomField::factory()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '',
            ]);

            expect($value->value)->toBe('');
            expect($value->formatted_value)->toBe('');
        });

        it('handles null values', function () {
            $field = CmCustomField::factory()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => null,
            ]);

            expect($value->value)->toBeNull();
        });

        it('handles very long text values', function () {
            $field = CmCustomField::factory()->create(['data_type' => 'text']);
            $longText = str_repeat('Lorem ipsum ', 1000); // ~12000 characters

            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => $longText,
            ]);

            expect(strlen($value->value))->toBeGreaterThan(10000);
        });

        it('handles special characters in values', function () {
            $field = CmCustomField::factory()->create();
            $specialValue = '<script>alert("test")</script>';

            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => $specialValue,
            ]);

            expect($value->value)->toBe($specialValue);
        });

        it('handles unicode characters', function () {
            $field = CmCustomField::factory()->create();
            $unicodeValue = '日本語テスト';

            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => $unicodeValue,
            ]);

            expect($value->value)->toBe($unicodeValue);
        });

        it('maintains data integrity across updates', function () {
            $value = CmCustomFieldValue::factory()->create(['value' => 'Original']);

            $value->update(['value' => 'Updated']);

            expect($value->fresh()->value)->toBe('Updated');
        });

        it('allows multiple values per user for different fields', function () {
            $user = User::factory()->create();
            $field1 = CmCustomField::factory()->create(['field_key' => 'department']);
            $field2 = CmCustomField::factory()->create(['field_key' => 'role']);

            $value1 = CmCustomFieldValue::factory()->create([
                'user_id' => $user->id,
                'cm_custom_field_id' => $field1->id,
                'value' => 'Engineering',
            ]);

            $value2 = CmCustomFieldValue::factory()->create([
                'user_id' => $user->id,
                'cm_custom_field_id' => $field2->id,
                'value' => 'Developer',
            ]);

            $userValues = CmCustomFieldValue::byUser($user->id)->get();

            expect($userValues)->toHaveCount(2);
        });
    });

    describe('value formatting consistency', function () {
        it('consistently formats integer numbers', function () {
            $field = CmCustomField::factory()->number()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '42',
            ]);

            expect($value->formatted_value)->toBe(42.0);
        });

        it('consistently formats decimal numbers', function () {
            $field = CmCustomField::factory()->number()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => '3.14159',
            ]);

            expect($value->formatted_value)->toBe(3.14159);
        });

        it('handles multi-select with spaces', function () {
            $field = CmCustomField::factory()->multiSelect()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'Option 1,Option 2,Option 3',
            ]);

            $formatted = $value->formatted_value;

            expect($formatted)->toContain('Option 1');
            expect($formatted)->toContain('Option 2');
            expect($formatted)->toContain('Option 3');
        });

        it('handles single value in multi-select field', function () {
            $field = CmCustomField::factory()->multiSelect()->create();
            $value = CmCustomFieldValue::factory()->create([
                'cm_custom_field_id' => $field->id,
                'value' => 'Single Option',
            ]);

            $formatted = $value->formatted_value;

            expect($formatted)->toBeArray();
            expect($formatted)->toBe(['Single Option']);
        });
    });
});
