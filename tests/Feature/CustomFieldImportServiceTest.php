<?php

use App\Enums\CustomFieldTypes;
use App\Models\CmCustomField;
use App\Services\CustomFieldImportService;

describe('CustomFieldImportService - JSON Validation', function () {
    test('it parses valid JSON data successfully', function () {
        $service = new CustomFieldImportService();
        $validJson = json_encode([
            [
                'FieldName' => 'Job Title',
                'Key' => '[JobTitle]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        $result = $service->parseJsonData($validJson);

        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        expect($result[0]['FieldName'])->toBe('Job Title');
        expect($result[0]['Key'])->toBe('[JobTitle]');
        expect($result[0]['DataType'])->toBe('Text');
        expect($result[0]['FieldOptions'])->toBe([]);
        expect($result[0]['VisibleInPreferenceCenter'])->toBe(true);
    });

    test('it rejects malformed JSON', function () {
        $service = new CustomFieldImportService();
        $malformedJson = '{"FieldName": "Job Title", "Key": "[JobTitle]"'; // Missing closing brace

        expect(fn() => $service->parseJsonData($malformedJson))
            ->toThrow(\InvalidArgumentException::class, 'Invalid JSON format');
    });

    test('it rejects non-array JSON', function () {
        $service = new CustomFieldImportService();
        $nonArrayJson = '{"FieldName": "Job Title"}';

        expect(fn() => $service->parseJsonData($nonArrayJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it rejects empty array', function () {
        $service = new CustomFieldImportService();
        $emptyArrayJson = '[]';

        expect(fn() => $service->parseJsonData($emptyArrayJson))
            ->toThrow(\InvalidArgumentException::class, 'JSON must be a non-empty array of field objects');
    });

    test('it validates required fields', function () {
        $service = new CustomFieldImportService();
        $incompleteJson = json_encode([
            [
                'FieldName' => 'Job Title',
                // Missing Key, DataType, FieldOptions, VisibleInPreferenceCenter
            ]
        ]);

        expect(fn() => $service->parseJsonData($incompleteJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it validates field name is not empty', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => '',
                'Key' => '[Test]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it validates key format with brackets', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Test Field',
                'Key' => 'TestKey', // Missing brackets
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class, 'Key must be in brackets format like [JobTitle]');
    });

    test('it validates valid data types', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Test Field',
                'Key' => '[Test]',
                'DataType' => 'InvalidType',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it validates FieldOptions is an array', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Test Field',
                'Key' => '[Test]',
                'DataType' => 'Text',
                'FieldOptions' => 'not an array',
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it validates VisibleInPreferenceCenter is boolean', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Test Field',
                'Key' => '[Test]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => 'not a boolean',
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class);
    });

    test('it validates MultiSelect fields have non-empty options', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Department',
                'Key' => '[Department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => [], // Empty options for MultiSelect
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class, 'must have non-empty FieldOptions');
    });

    test('it validates MultiSelectMany fields have non-empty options', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Skills',
                'Key' => '[Skills]',
                'DataType' => 'MultiSelectMany',
                'FieldOptions' => [], // Empty options for MultiSelectMany
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class, 'must have non-empty FieldOptions');
    });

    test('it allows empty options for non-MultiSelect fields', function () {
        $service = new CustomFieldImportService();
        $validJson = json_encode([
            [
                'FieldName' => 'Description',
                'Key' => '[Description]',
                'DataType' => 'Text',
                'FieldOptions' => [], // Empty options for Text field is OK
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($validJson))
            ->not->toThrow(\Exception::class);
    });

    test('it processes multiple valid fields', function () {
        $service = new CustomFieldImportService();
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

        $result = $service->parseJsonData($validJson);

        expect($result)->toHaveCount(2);
        expect($result[0]['FieldName'])->toBe('Job Title');
        expect($result[1]['FieldName'])->toBe('Department');
    });

    test('it provides helpful error messages with field context', function () {
        $service = new CustomFieldImportService();
        $invalidJson = json_encode([
            [
                'FieldName' => 'Valid Field',
                'Key' => '[ValidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ],
            [
                'FieldName' => '',
                'Key' => '[InvalidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ]);

        expect(fn() => $service->parseJsonData($invalidJson))
            ->toThrow(\InvalidArgumentException::class, "Validation error for field '' at index 1");
    });
});

describe('CustomFieldImportService - Preview Generation', function () {
    test('it generates preview for new fields', function () {
        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'New Field',
                'Key' => '[NewField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['new'])->toHaveCount(1);
        expect($preview['updated'])->toHaveCount(0);
        expect($preview['duplicates'])->toHaveCount(0);
        expect($preview['invalid'])->toHaveCount(0);
        expect($preview['statistics']['new'])->toBe(1);
        expect($preview['statistics']['total'])->toBe(1);
        expect($preview['new'][0]['external']['field_name'])->toBe('New Field');
        expect($preview['new'][0]['external']['field_key'])->toBe('NewField');
    });

    test('it detects updates for existing fields with differences', function () {
        // Create existing field
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Department Updated',
                'Key' => '[department]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['new'])->toHaveCount(0);
        expect($preview['updated'])->toHaveCount(1);
        expect($preview['duplicates'])->toHaveCount(0);
        expect($preview['invalid'])->toHaveCount(0);
        expect($preview['statistics']['updated'])->toBe(1);
        expect($preview['updated'][0]['differences']['field_name']['old'])->toBe('Department');
        expect($preview['updated'][0]['differences']['field_name']['new'])->toBe('Department Updated');
    });

    test('it detects duplicates for identical fields', function () {
        // Create existing field
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Department',
                'Key' => '[department]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => false,
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['new'])->toHaveCount(0);
        expect($preview['updated'])->toHaveCount(0);
        expect($preview['duplicates'])->toHaveCount(1);
        expect($preview['invalid'])->toHaveCount(0);
        expect($preview['statistics']['duplicates'])->toBe(1);
        expect($preview['duplicates'][0]['reason'])->toBe('No differences detected');
    });

    test('it detects duplicate field names with different keys', function () {
        // Create existing field
        CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Department', // Same name
                'Key' => '[Department]', // Different key
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['duplicates'])->toHaveCount(1);
        expect($preview['duplicates'][0]['reason'])->toBe('Duplicate field name (different key)');
    });

    test('it detects exact duplicates by field key', function () {
        // Create existing field with same field_key
        CmCustomField::create([
            'field_key' => 'jobtitle',
            'field_name' => 'Job Title',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false, // Make sure this matches the test data
        ]);

        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Job Title',
                'Key' => '[jobtitle]', // Same field key after removing brackets
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => false, // Make it identical to existing field
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['duplicates'])->toHaveCount(1);
        expect($preview['duplicates'][0]['reason'])->toBe('No differences detected');
    });

    test('it handles valid fields in preview generation', function () {
        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Valid Field',
                'Key' => '[ValidField]',
                'DataType' => 'Text',
                'FieldOptions' => [],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $preview = $service->generatePreview($fields);

        expect($preview['new'])->toHaveCount(1);
        expect($preview['invalid'])->toHaveCount(0);
        expect($preview['statistics']['new'])->toBe(1);
        expect($preview['statistics']['invalid'])->toBe(0);
        expect($preview['new'][0]['external']['field_name'])->toBe('Valid Field');
    });

    test('it calculates differences correctly', function () {
        $existingField = CmCustomField::create([
            'field_key' => 'department',
            'field_name' => 'Department',
            'data_type' => CustomFieldTypes::Text,
            'options' => ['Engineering'],
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $service = new CustomFieldImportService();
        $fields = [
            [
                'FieldName' => 'Department Updated',
                'Key' => '[department]',
                'DataType' => 'MultiSelectOne',
                'FieldOptions' => ['Engineering', 'Sales'],
                'VisibleInPreferenceCenter' => true,
            ]
        ];

        $preview = $service->generatePreview($fields);
        $differences = $preview['updated'][0]['differences'];

        expect($differences['field_name']['old'])->toBe('Department');
        expect($differences['field_name']['new'])->toBe('Department Updated');
        expect($differences['data_type']['old'])->toBe('Text');
        expect($differences['data_type']['new'])->toBe('MultiSelectOne');
        expect($differences['options']['old'])->toBe(['Engineering']);
        expect($differences['options']['new'])->toBe(['Engineering', 'Sales']);
        expect($differences['is_user_editable']['old'])->toBe(false);
        expect($differences['is_user_editable']['new'])->toBe(true);
    });

    test('it handles mixed scenarios', function () {
        // Create existing field for update
        CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Existing Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        // Create existing field for duplicate
        CmCustomField::create([
            'field_key' => 'duplicate',
            'field_name' => 'Duplicate Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
        ]);

        $service = new CustomFieldImportService();
        $fields = [
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
            // Duplicate field (same field key)
            [
                'FieldName' => 'Duplicate Field',
                'Key' => '[duplicate]', // Same field key as existing
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

        $preview = $service->generatePreview($fields);

        expect($preview['statistics']['total'])->toBe(4);
        expect($preview['statistics']['new'])->toBe(2);
        expect($preview['statistics']['updated'])->toBe(2);
        expect($preview['statistics']['duplicates'])->toBe(0);
        expect($preview['statistics']['invalid'])->toBe(0);
    });
});

describe('CustomFieldImportService - Apply Changes', function () {
    test('it creates new fields', function () {
        $service = new CustomFieldImportService();
        $preview = [
            'new' => [
                [
                    'external' => [
                        'field_key' => 'newfield',
                        'field_name' => 'New Field',
                        'data_type' => 'Text',
                        'options' => [],
                        'is_user_editable' => true,
                        'is_active' => true,
                        'external_key' => '[NewField]',
                        'last_seen_at' => now(),
                    ],
                    'index' => 0,
                ]
            ],
            'updated' => [],
        ];

        $results = $service->applyChanges($preview, [0]);

        expect($results['created'])->toBe(1);
        expect($results['updated'])->toBe(0);
        expect($results['skipped'])->toBe(0);
        expect($results['errors'])->toHaveCount(0);

        expect(CmCustomField::where('field_key', 'newfield')->exists())->toBeTrue();
    });

    test('it updates existing fields', function () {
        $existingField = CmCustomField::create([
            'field_key' => 'existing',
            'field_name' => 'Existing Field',
            'data_type' => CustomFieldTypes::Text,
            'is_active' => true,
            'is_user_editable' => false,
        ]);

        $service = new CustomFieldImportService();
        $preview = [
            'new' => [],
            'updated' => [
                [
                    'external' => [
                        'field_key' => 'existing',
                        'field_name' => 'Existing Field Updated',
                        'data_type' => 'Text',
                        'options' => [],
                        'is_user_editable' => true,
                        'is_active' => true,
                        'external_key' => '[existing]',
                        'last_seen_at' => now(),
                    ],
                    'existing' => $existingField,
                    'index' => 0,
                ]
            ],
        ];

        $results = $service->applyChanges($preview, [0]);

        expect($results['created'])->toBe(0);
        expect($results['updated'])->toBe(1);
        expect($results['skipped'])->toBe(0);
        expect($results['errors'])->toHaveCount(0);

        $existingField->refresh();
        expect($existingField->field_name)->toBe('Existing Field Updated');
        expect($existingField->is_user_editable)->toBeTrue();
    });

    test('it processes all valid items when no indices provided', function () {
        $service = new CustomFieldImportService();
        $preview = [
            'new' => [
                [
                    'external' => [
                        'field_key' => 'field1',
                        'field_name' => 'Field 1',
                        'data_type' => 'Text',
                        'options' => [],
                        'is_user_editable' => false,
                        'is_active' => true,
                        'external_key' => '[Field1]',
                        'last_seen_at' => now(),
                    ],
                    'index' => 0,
                ],
                [
                    'external' => [
                        'field_key' => 'field2',
                        'field_name' => 'Field 2',
                        'data_type' => 'Text',
                        'options' => [],
                        'is_user_editable' => false,
                        'is_active' => true,
                        'external_key' => '[Field2]',
                        'last_seen_at' => now(),
                    ],
                    'index' => 1,
                ]
            ],
            'updated' => [],
        ];

        $results = $service->applyChanges($preview);

        expect($results['created'])->toBe(2);
        expect(CmCustomField::where('field_key', 'field1')->exists())->toBeTrue();
        expect(CmCustomField::where('field_key', 'field2')->exists())->toBeTrue();
    });

    test('it applies changes without errors', function () {
        $service = new CustomFieldImportService();
        $preview = [
            'new' => [
                [
                    'external' => [
                        'field_key' => 'test_field',
                        'field_name' => 'Test Field',
                        'data_type' => 'Text',
                        'options' => [],
                        'is_user_editable' => false,
                        'is_active' => true,
                        'last_seen_at' => now(),
                    ],
                    'index' => 0,
                ]
            ],
            'updated' => [],
        ];

        $results = $service->applyChanges($preview, [0]);

        expect($results['created'])->toBe(1);
        expect($results['errors'])->toHaveCount(0);
    });
});

describe('CustomFieldImportService - Helper Methods', function () {
    test('it maps data types correctly', function () {
        $service = new CustomFieldImportService();

        expect($service->mapDataType('Text'))->toBe('Text');
        expect($service->mapDataType('Number'))->toBe('Number');
        expect($service->mapDataType('Date'))->toBe('Date');
        expect($service->mapDataType('MultiSelectOne'))->toBe('MultiSelectOne');
        expect($service->mapDataType('MultiSelectMany'))->toBe('MultiSelectMany');
        expect($service->mapDataType('Country'))->toBe('Country');
        expect($service->mapDataType('InvalidType'))->toBe('Text'); // Default fallback
    });

    test('it removes brackets from keys correctly', function () {
        $service = new CustomFieldImportService();

        expect($service->removeBrackets('[JobTitle]'))->toBe('JobTitle');
        expect($service->removeBrackets('[Complex.Name]'))->toBe('Complex.Name');
        expect($service->removeBrackets('JobTitle]'))->toBe('JobTitle');
        expect($service->removeBrackets('[JobTitle'))->toBe('JobTitle');
        expect($service->removeBrackets('JobTitle'))->toBe('JobTitle');
    });

    test('it returns validation rules', function () {
        $service = new CustomFieldImportService();
        $rules = $service->getValidationRules();

        expect($rules)->toBeArray();
        expect($rules)->toHaveKey('FieldName');
        expect($rules)->toHaveKey('Key');
        expect($rules)->toHaveKey('DataType');
        expect($rules)->toHaveKey('FieldOptions');
        expect($rules)->toHaveKey('VisibleInPreferenceCenter');
        expect($rules['FieldName'])->toContain('required');
        expect($rules['Key'])->toContain('required');
        expect($rules['DataType'])->toContain('required');
        expect($rules['FieldOptions'])->toContain('present');
        expect($rules['VisibleInPreferenceCenter'])->toContain('required');
    });

    test('it returns example format', function () {
        $service = new CustomFieldImportService();
        $example = $service->getExampleFormat();

        expect($example)->toBeString();
        $exampleData = json_decode($example, true);

        expect($exampleData)->toBeArray();
        expect($exampleData[0])->toHaveKeys(['FieldName', 'Key', 'DataType', 'FieldOptions', 'VisibleInPreferenceCenter']);
        expect($exampleData[0]['FieldName'])->toBe('Job Title');
        expect($exampleData[0]['Key'])->toBe('[JobTitle]');
        expect($exampleData[0]['DataType'])->toBe('Text');
        expect($exampleData[0]['FieldOptions'])->toBeArray();
        expect($exampleData[0]['VisibleInPreferenceCenter'])->toBe(true);
    });

    test('it processes field data correctly', function () {
        $service = new CustomFieldImportService();
        $field = [
            'FieldName' => 'Test Field',
            'Key' => '[TestField]',
            'DataType' => 'MultiSelectOne',
            'FieldOptions' => ['Option1', 'Option2'],
            'VisibleInPreferenceCenter' => true,
        ];

        $processed = $service->processFieldData($field);

        expect($processed['field_key'])->toBe('TestField');
        expect($processed['field_name'])->toBe('Test Field');
        expect($processed['data_type'])->toBe('MultiSelectOne');
        expect($processed['options'])->toBe(['Option1', 'Option2']);
        expect($processed['is_user_editable'])->toBe(true);
        expect($processed['is_active'])->toBe(true);
        expect($processed['last_seen_at'])->toBeInstanceOf(\Carbon\Carbon::class);
    });
});