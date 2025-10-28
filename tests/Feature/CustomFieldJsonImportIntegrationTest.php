<?php

use App\Enums\CustomFieldTypes;
use App\Livewire\Admin\CustomFields\CustomFieldManager;
use App\Models\CmCustomField;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

describe('CustomField JSON Import - Integration Tests', function () {
    test('complete workflow for importing new fields', function () {
        $jsonContent = json_encode([
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
                'FieldOptions' => ['Engineering', 'Sales', 'Marketing'],
                'VisibleInPreferenceCenter' => false,
            ]
        ]);

        // Start the import process
        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->assertSet('showCampaignMonitorModal', true)
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('showCampaignMonitorModal', true) // Modal stays open in new workflow
            ->assertSet('cmShowPreview', true);

        // Verify preview data
        $previewData = $livewire->get('previewData');
        expect($previewData['statistics']['total'])->toBe(2);
        expect($previewData['statistics']['new'])->toBe(2);
        expect($previewData['statistics']['updated'])->toBe(0);
        expect($previewData['statistics']['duplicates'])->toBe(0);
        expect($previewData['statistics']['invalid'])->toBe(0);

        // Apply the import
        $livewire->call('applyJsonChanges')
            ->assertSet('cmShowPreview', false);


        // Verify fields were created in database
        expect(CmCustomField::where('field_key', 'JobTitle')->exists())->toBeTrue();
        expect(CmCustomField::where('field_key', 'Department')->exists())->toBeTrue();

        $jobTitle = CmCustomField::where('field_key', 'JobTitle')->first();
        expect($jobTitle->field_name)->toBe('Job Title');
        expect($jobTitle->data_type)->toBe(CustomFieldTypes::Text);
        expect($jobTitle->is_user_editable)->toBeTrue();
        expect($jobTitle->external_key)->toBe('[JobTitle]');

        $department = CmCustomField::where('field_key', 'Department')->first();
        expect($department->field_name)->toBe('Department');
        expect($department->data_type)->toBe(CustomFieldTypes::MultiSelectOne);
        expect($department->is_user_editable)->toBeFalse();
        expect($department->options)->toBe(['Engineering', 'Sales', 'Marketing']);
        expect($department->external_key)->toBe('[Department]');
    });

    test('complete workflow for updating existing fields', function () {
        // Create existing fields
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'options' => [],
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        CmCustomField::create([
            'field_key' => 'jobtitle',
            'field_name' => 'Job Title',
            'data_type' => CustomFieldTypes::Text,
            'options' => [],
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $jsonContent = json_encode([
            [
                'FieldName' => 'Department Updated',
                'Key' => '[department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => ['Engineering', 'Sales'],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Job Title Enhanced',
                'Key' => '[jobtitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        // Import and update
        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true)
            ->call('applyJsonChanges');

        // Verify updates
        $department = CmCustomField::where('field_key', 'department')->first();
        expect($department->field_name)->toBe('Department Updated');
        expect($department->data_type)->toBe(CustomFieldTypes::MultiSelectOne);
        expect($department->is_user_editable)->toBeTrue();
        expect($department->options)->toBe(['Engineering', 'Sales']);

        $jobTitle = CmCustomField::where('field_key', 'jobtitle')->first();
        expect($jobTitle->field_name)->toBe('Job Title Enhanced');
        expect($jobTitle->is_user_editable)->toBeTrue();
    });

    test('complete workflow with mixed new, updated, and duplicate fields', function () {
        // Create existing fields
        $existingField = CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Existing Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $duplicateField = CmCustomField::create([
            'field_key' => 'duplicate',
            'field_name' => 'Duplicate Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'external_key' => '[DuplicateExternal]',
        ]);

        $jsonContent = json_encode([
            // New field
            [
                'FieldName' => 'Brand New Field',
                'Key' => '[BrandNew]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Update existing field
            [
                'FieldName' => 'Existing Field Updated',
                'Key' => '[existing]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Exact duplicate (same external key)
            [
                'FieldName' => 'Duplicate Field',
                'Key' => '[DuplicateExternal]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            // Duplicate by name only
            [
                'FieldName' => 'Duplicate Field',
                'Key' => '[DifferentKey]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        // Process import
        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true);

        // Verify preview statistics
        $previewData = $livewire->get('previewData');
        expect($previewData['statistics']['total'])->toBe(4);
        expect($previewData['statistics']['new'])->toBe(1);
        expect($previewData['statistics']['updated'])->toBe(1);
        expect($previewData['statistics']['duplicates'])->toBe(2);
        expect($previewData['statistics']['invalid'])->toBe(0);

        // Apply import (should only process new and updated)
        $livewire->call('applyJsonChanges');

        // Verify results
        expect(CmCustomField::where('field_key', 'BrandNew')->exists())->toBeTrue();

        $existingField->refresh();
        expect($existingField->field_name)->toBe('Existing Field Updated');
        expect($existingField->is_user_editable)->toBeTrue();

        // Duplicates should remain unchanged
        expect(CmCustomField::where('field_key', 'duplicate')->count())->toBe(1);
    });

    test('error handling workflow with invalid JSON', function () {
        $invalidJsonContent = json_encode([
            [
                'FieldName' => 'Valid Field',
                'Key' => '[ValidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => '', // Invalid empty name
                'Key' => '[InvalidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Invalid Key Format',
                'Key' => 'InvalidKey', // Missing brackets
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $invalidJsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true);

        // Verify preview with errors
        $previewData = $livewire->get('previewData');
        expect($previewData['statistics']['total'])->toBe(3);
        expect($previewData['statistics']['new'])->toBe(1);
        expect($previewData['statistics']['invalid'])->toBe(2);

        // Should still be able to import the valid field
        $livewire->call('applyJsonChanges');

        expect(CmCustomField::where('field_key', 'ValidField')->exists())->toBeTrue();
        expect(CmCustomField::count())->toBe(1); // Only the valid field should be created
    });

    test('selective import workflow', function () {
        $jsonContent = json_encode([
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
            ],
            [
                'FieldName' => 'Field 3',
                'Key' => '[Field3]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        $livewire = Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true);

        // Initially all should be selected
        $selectedIndices = $livewire->get('selectedPreviewIndices');
        expect($selectedIndices)->toHaveCount(3);

        // Deselect the second field
        $livewire->call('togglePreviewSelection', 1);
        $selectedIndices = $livewire->get('selectedPreviewIndices');
        expect($selectedIndices)->toHaveCount(2);
        expect($selectedIndices)->not->toContain(1);

        // Import only selected fields
        $livewire->call('applyJsonChanges');

        // Verify only selected fields were created
        expect(CmCustomField::where('field_key', 'Field1')->exists())->toBeTrue();
        expect(CmCustomField::where('field_key', 'Field2')->exists())->toBeFalse();
        expect(CmCustomField::where('field_key', 'Field3')->exists())->toBeTrue();
    });

    test('validation prevents empty import', function () {
        $jsonContent = json_encode([
            [
                'FieldName' => 'Test Field',
                'Key' => '[Test]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true)
            ->set('selectedPreviewIndices', []) // Clear all selections
            ->call('applyJsonChanges')
            ->assertSet('cmShowPreview', true); // Modal should stay open

        // Verify no fields were created
        expect(CmCustomField::count())->toBe(0);
    });

    test('workflow preserves existing field relationships', function () {
        // Create field with values
        $field = CmCustomField::create([
            'field_key' => 'testfield',
            'field_name' => 'Test Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        // Create some custom field values to test relationship preservation
        $user = User::factory()->create();
        \App\Models\CmCustomFieldValue::create([
            'user_id' => $user->id,
            'cm_custom_field_id' => $field->id,
            'value' => 'Test Value',
        ]);

        $jsonContent = json_encode([
            [
                'FieldName' => 'Test Field Updated',
                'Key' => '[testfield]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        // Update the field
        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->call('applyJsonChanges');

        // Verify field was updated but relationships preserved
        $field->refresh();
        expect($field->field_name)->toBe('Test Field Updated');
        expect($field->is_user_editable)->toBeTrue();
        expect($field->customFieldValues()->count())->toBe(1);
        expect($field->customFieldValues()->first()->value)->toBe('Test Value');
    });

    test('workflow handles MultiSelect fields with complex options', function () {
        $jsonContent = json_encode([
            [
                'FieldName' => 'Skills',
                'Key' => '[Skills]',
                'DataType' => 'MultiSelectMany',
                'FieldOptions' => [
                    'PHP',
                    'Laravel',
                    'JavaScript',
                    'Vue.js',
                    'Docker',
                    'AWS'
                ],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => 'Experience Level',
                'Key' => '[Experience]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => [
                    'Junior',
                    'Mid-Level',
                    'Senior',
                    'Lead',
                    'Principal'
                ],
                'VisibleInPreferenceCenter' => false,
            ]
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput')
            ->assertSet('cmShowPreview', true)
            ->call('applyJsonChanges');

        // Verify complex options were preserved
        $skills = CmCustomField::where('field_key', 'Skills')->first();
        expect($skills->field_name)->toBe('Skills');
        expect($skills->data_type)->toBe(CustomFieldTypes::MultiSelectMany);
        expect($skills->options)->toBe([
            'PHP',
            'Laravel',
            'JavaScript',
            'Vue.js',
            'Docker',
            'AWS'
        ]);

        $experience = CmCustomField::where('field_key', 'Experience')->first();
        expect($experience->field_name)->toBe('Experience Level');
        expect($experience->data_type)->toBe(CustomFieldTypes::MultiSelectOne);
        expect($experience->options)->toBe([
            'Junior',
            'Mid-Level',
            'Senior',
            'Lead',
            'Principal'
        ]);
    });

    test('workflow validates required MultiSelect options', function () {
        $jsonContent = json_encode([
            [
                'FieldName' => 'Department',
                'Key' => '[Department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => [], // Empty options - should fail
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        Livewire::test(CustomFieldManager::class)
            ->call('openCampaignMonitorModal')
            ->set('jsonInput', $jsonContent)
            ->call('processJsonInput');

        // Verify no fields were created
        expect(CmCustomField::count())->toBe(0);
    });
});