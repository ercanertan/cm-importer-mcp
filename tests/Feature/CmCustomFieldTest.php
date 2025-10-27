<?php

use App\Enums\CustomFieldTypes;
use App\Models\CmCustomField;
use App\Models\CmCustomFieldValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CmCustomField Model', function () {
    describe('factory and states', function () {
        it('creates custom field with default text type', function () {
            $field = CmCustomField::factory()->create();

            expect($field->data_type)->toBe(CustomFieldTypes::Text);
            expect($field->is_active)->toBeTrue();
            expect($field->field_key)->not->toBeNull();
        });

        it('creates inactive custom field', function () {
            $field = CmCustomField::factory()->inactive()->create();

            expect($field->is_active)->toBeFalse();
        });

        it('creates number type custom field', function () {
            $field = CmCustomField::factory()->number()->create();

            expect($field->data_type)->toBe(CustomFieldTypes::Number);
        });

        it('creates date type custom field', function () {
            $field = CmCustomField::factory()->date()->create();

            expect($field->data_type)->toBe(CustomFieldTypes::Date);
        });

        it('creates multi-select type custom field', function () {
            $field = CmCustomField::factory()->multiSelect()->create();

            expect($field->data_type)->toBe(CustomFieldTypes::MultiSelectOne);
            expect($field->options)->toBeArray();
            expect($field->options)->toHaveCount(3);
        });
    });

    describe('data type detection', function () {
        beforeEach(function () {
            $this->field = CmCustomField::factory()->create();
        });

        it('detects numeric values as number type', function () {
            expect($this->field->detectDataType('123'))->toBe(CustomFieldTypes::Number);
            expect($this->field->detectDataType('45.67'))->toBe(CustomFieldTypes::Number);
            expect($this->field->detectDataType('0'))->toBe(CustomFieldTypes::Number);
        });

        it('detects date values as date type', function () {
            expect($this->field->detectDataType('2024-01-15'))->toBe(CustomFieldTypes::Date);
            expect($this->field->detectDataType('2024-12-31 10:30:00'))->toBe(CustomFieldTypes::Date);
        });

        it('detects comma-separated values as multi_select', function () {
            expect($this->field->detectDataType('Option1,Option2,Option3'))->toBe(CustomFieldTypes::MultiSelectOne);
        });

        it('detects semicolon-separated values as multi_select', function () {
            expect($this->field->detectDataType('Option1;Option2;Option3'))->toBe(CustomFieldTypes::MultiSelectOne);
        });

        it('detects plain text as text type', function () {
            expect($this->field->detectDataType('Hello World'))->toBe(CustomFieldTypes::Text);
            expect($this->field->detectDataType('Some description'))->toBe(CustomFieldTypes::Text);
        });

        it('handles empty values', function () {
            expect($this->field->detectDataType(''))->toBe(CustomFieldTypes::Text);
        });

        it('prioritizes number over date format', function () {
            // A value that's numeric should be detected as number
            expect($this->field->detectDataType('20240115'))->toBe(CustomFieldTypes::Number);
        });
    });

    describe('scopes', function () {
        it('filters active fields', function () {
            CmCustomField::factory()->create(['is_active' => true]);
            CmCustomField::factory()->create(['is_active' => true]);
            CmCustomField::factory()->inactive()->create();

            $activeFields = CmCustomField::active()->get();

            expect($activeFields)->toHaveCount(2);
        });

        it('filters by field key', function () {
            $field = CmCustomField::factory()->create(['field_key' => 'department']);
            CmCustomField::factory()->create(['field_key' => 'role']);

            $result = CmCustomField::byFieldKey('department')->first();

            expect($result->id)->toBe($field->id);
        });

        it('chains scopes', function () {
            $activeField = CmCustomField::factory()->create([
                'field_key' => 'company',
                'is_active' => true,
            ]);

            CmCustomField::factory()->create([
                'field_key' => 'industry', // Different key to avoid unique constraint
                'is_active' => false,
            ]);

            $result = CmCustomField::active()->byFieldKey('company')->first();

            expect($result->id)->toBe($activeField->id);
        });
    });

    describe('relationships', function () {
        it('has many custom field values', function () {
            $field = CmCustomField::factory()->create();

            CmCustomFieldValue::factory()->count(3)->create([
                'cm_custom_field_id' => $field->id,
            ]);

            expect($field->customFieldValues)->toHaveCount(3);
            expect($field->customFieldValues->first())->toBeInstanceOf(CmCustomFieldValue::class);
        });

        it('has many users through custom field values', function () {
            $field = CmCustomField::factory()->create();
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            CmCustomFieldValue::factory()->create([
                'user_id' => $user1->id,
                'cm_custom_field_id' => $field->id,
                'value' => 'Value 1',
            ]);

            CmCustomFieldValue::factory()->create([
                'user_id' => $user2->id,
                'cm_custom_field_id' => $field->id,
                'value' => 'Value 2',
            ]);

            $users = $field->users;

            expect($users)->toHaveCount(2);
            expect($users->pluck('id')->toArray())->toContain($user1->id);
            expect($users->pluck('id')->toArray())->toContain($user2->id);
        });

        it('includes pivot value in users relationship', function () {
            $field = CmCustomField::factory()->create();
            $user = User::factory()->create();

            CmCustomFieldValue::factory()->create([
                'user_id' => $user->id,
                'cm_custom_field_id' => $field->id,
                'value' => 'Test Value',
            ]);

            $userWithPivot = $field->users()->first();

            expect($userWithPivot->pivot->value)->toBe('Test Value');
        });
    });

    describe('last seen tracking', function () {
        it('updates last seen timestamp', function () {
            $field = CmCustomField::factory()->create([
                'last_seen_at' => now()->subDays(7),
            ]);

            $oldTimestamp = $field->last_seen_at;

            sleep(1);
            $field->updateLastSeen();

            expect($field->fresh()->last_seen_at)->toBeGreaterThan($oldTimestamp);
        });

        it('sets last seen to current time', function () {
            $field = CmCustomField::factory()->create();

            $field->updateLastSeen();

            $lastSeen = $field->fresh()->last_seen_at;
            expect($lastSeen->diffInSeconds(now()))->toBeLessThan(2);
        });
    });

    describe('casts', function () {
        it('casts options as array', function () {
            $options = ['Option A', 'Option B', 'Option C'];
            $field = CmCustomField::factory()->create([
                'options' => $options,
            ]);

            expect($field->fresh()->options)->toBeArray();
            expect($field->fresh()->options)->toBe($options);
        });

        it('handles null options', function () {
            $field = CmCustomField::factory()->create([
                'options' => null,
            ]);

            expect($field->fresh()->options)->toBeNull();
        });

        it('casts is_active as boolean', function () {
            $field = CmCustomField::factory()->create(['is_active' => true]);

            expect($field->fresh()->is_active)->toBeTrue();
            expect($field->fresh()->is_active)->toBeBool();
        });

        it('casts last_seen_at as datetime', function () {
            $field = CmCustomField::factory()->create();

            expect($field->last_seen_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        });
    });

    describe('edge cases', function () {
        it('enforces unique field_key constraint', function () {
            $fieldKey = 'unique_field';
            CmCustomField::factory()->create(['field_key' => $fieldKey]);

            // Creating with same key should throw exception due to unique constraint
            expect(fn() => CmCustomField::factory()->create(['field_key' => $fieldKey]))
                ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
        });

        it('handles special characters in field values', function () {
            $field = CmCustomField::factory()->create();

            $specialValue = 'Value with "quotes" and, commas;';
            $type = $field->detectDataType($specialValue);

            expect($type)->toBe(CustomFieldTypes::MultiSelectOne); // Has comma and semicolon
        });

        it('properly generates field name from field key', function () {
            $field = CmCustomField::factory()->create(['field_key' => 'test']);

            expect($field->field_name)->not->toBeNull();
        });

        it('maintains consistency across data type detection', function () {
            $field = CmCustomField::factory()->create();

            $value = '2024-01-15';
            $type1 = $field->detectDataType($value);
            $type2 = $field->detectDataType($value);

            expect($type1)->toBe($type2);
        });

        it('handles mixed content detection', function () {
            $field = CmCustomField::factory()->create();

            // Email addresses might contain @ but should be text
            expect($field->detectDataType('user@example.com'))->toBe(CustomFieldTypes::Text);

            // URLs should be text
            expect($field->detectDataType('https://example.com'))->toBe(CustomFieldTypes::Text);
        });
    });

    describe('field key patterns', function () {
        it('accepts lowercase field keys', function () {
            $field = CmCustomField::factory()->create(['field_key' => 'department']);

            expect($field->field_key)->toBe('department');
        });

        it('accepts field keys with underscores', function () {
            $field = CmCustomField::factory()->create(['field_key' => 'job_title']);

            expect($field->field_key)->toBe('job_title');
        });

        it('handles field keys with numbers', function () {
            $field = CmCustomField::factory()->create(['field_key' => 'field_123']);

            expect($field->field_key)->toBe('field_123');
        });
    });
});
