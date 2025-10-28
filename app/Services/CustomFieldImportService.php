<?php

namespace App\Services;

use App\Enums\CustomFieldTypes;
use App\Models\CmCustomField;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomFieldImportService
{
    /**
     * Process fields and generate preview comparison from Campaign Monitor API
     */
    public function generatePreview(array $externalFields): array
    {
        $preview = [
            'new' => [],
            'updated' => [],
            'duplicates' => [],
            'invalid' => [],
            'statistics' => [
                'total' => count($externalFields),
                'new' => 0,
                'updated' => 0,
                'duplicates' => 0,
                'invalid' => 0,
            ]
        ];

        $existingFields = CmCustomField::all();
        $existingByKeyName = $existingFields->keyBy('field_key');
        $existingByName = $existingFields->keyBy('field_name');

        foreach ($externalFields as $index => $field) {
            try {
                // Validate the field
                $this->validateField($field, $index);

                $cleanKey = $this->removeBrackets($field['Key']);
                $processedField = $this->processFieldData($field);

                // Check for key matches (field_key) - this is the primary matching logic
                if ($existingByKeyName->has($cleanKey)) {
                    $existingField = $existingByKeyName->get($cleanKey);
                    $differences = $this->calculateDifferences($existingField, $processedField);

                    if (!empty($differences)) {
                        $preview['updated'][] = [
                            'external' => $processedField,
                            'existing' => $existingField,
                            'differences' => $differences,
                            'index' => $index,
                        ];
                        $preview['statistics']['updated']++;
                    } else {
                        $preview['duplicates'][] = [
                            'external' => $processedField,
                            'existing' => $existingField,
                            'reason' => 'No differences detected',
                            'index' => $index,
                        ];
                        $preview['statistics']['duplicates']++;
                    }
                    continue;
                }

                // Check for name matches (field_name)
                if ($existingByName->has($field['FieldName'])) {
                    $existingField = $existingByName->get($field['FieldName']);
                    $preview['duplicates'][] = [
                        'external' => $processedField,
                        'existing' => $existingField,
                        'reason' => 'Duplicate field name (different key)',
                        'index' => $index,
                    ];
                    $preview['statistics']['duplicates']++;
                    continue;
                }

                // New field
                $preview['new'][] = [
                    'external' => $processedField,
                    'index' => $index,
                ];
                $preview['statistics']['new']++;

            } catch (\Exception $e) {
                $preview['invalid'][] = [
                    'external' => $field,
                    'error' => $e->getMessage(),
                    'index' => $index,
                ];
                $preview['statistics']['invalid']++;
            }
        }

        return $preview;
    }

    /**
     * Apply the preview changes (create new fields, update existing ones)
     */
    public function applyChanges(array $preview, array $selectedIndices = []): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // If no specific indices selected, process all valid items
        $processIndices = empty($selectedIndices) ?
            array_merge(
                array_column($preview['new'], 'index'),
                array_column($preview['updated'], 'index')
            ) : $selectedIndices;

        foreach ($processIndices as $index) {
            try {
                // Process new fields
                foreach ($preview['new'] as $item) {
                    if ($item['index'] === $index) {
                        $field = CmCustomField::create($item['external']);
                        $results['created']++;
                        break;
                    }
                }

                // Process updated fields
                foreach ($preview['updated'] as $item) {
                    if ($item['index'] === $index) {
                        $item['existing']->update($item['external']);
                        $results['updated']++;
                        break;
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Process external field data into database format
     */
    public function processFieldData(array $field): array
    {
        return [
            'field_key' => $this->removeBrackets($field['Key']),
            'field_name' => $field['FieldName'],
            'data_type' => $this->mapDataType($field['DataType']),
            'options' => $field['FieldOptions'],
            'is_user_editable' => $field['VisibleInPreferenceCenter'],
            'is_active' => true,
            'last_seen_at' => now(),
            'external_key' => $field['Key'],
        ];
    }

    /**
     * Calculate differences between existing and external field
     */
    protected function calculateDifferences(CmCustomField $existing, array $external): array
    {
        $differences = [];

        if ($existing->field_name !== $external['field_name']) {
            $differences['field_name'] = [
                'old' => $existing->field_name,
                'new' => $external['field_name'],
            ];
        }

        if ($existing->data_type->value !== $external['data_type']) {
            $differences['data_type'] = [
                'old' => $existing->data_type->value,
                'new' => $external['data_type'],
            ];
        }

        if ($existing->is_user_editable !== $external['is_user_editable']) {
            $differences['is_user_editable'] = [
                'old' => $existing->is_user_editable,
                'new' => $external['is_user_editable'],
            ];
        }

        $existingOptions = $existing->options ?? [];
        sort($existingOptions);
        $externalOptions = $external['options'] ?? [];
        sort($externalOptions);

        if ($existingOptions !== $externalOptions) {
            $differences['options'] = [
                'old' => $existingOptions,
                'new' => $externalOptions,
            ];
        }

        return $differences;
    }

    /**
     * Map external data type to enum value
     */
    public function mapDataType(string $externalType): string
    {
        // Validate that the external type matches one of our enum values
        $validTypes = array_map(fn($type) => $type->value, CustomFieldTypes::cases());

        if (in_array($externalType, $validTypes)) {
            return $externalType;
        }

        // Default to Text for invalid types (or could throw exception)
        return CustomFieldTypes::Text->value;
    }

    /**
     * Remove brackets from external key
     */
    public function removeBrackets(string $key): string
    {
        return trim($key, '[]');
    }

    /**
     * Validate a single field - throws exception for invalid fields (used in generatePreview)
     */
    public function validateField(array $field, int $index): void
    {
        $validator = Validator::make($field, [
            'FieldName' => 'required|string|max:255',
            'Key' => 'required|string|max:255|regex:/^\[.*\]$/',
            'DataType' => 'required|string|in:' . implode(',', array_map(fn($type) => $type->value, CustomFieldTypes::cases())),
            'FieldOptions' => 'present|array',
            'VisibleInPreferenceCenter' => 'required|boolean',
        ], [
            'Key.regex' => 'Key must be in brackets format like [JobTitle]',
            'FieldOptions.present' => 'FieldOptions must be present and should be an array (use [] for empty)',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $fieldName = isset($field['FieldName']) ? $field['FieldName'] : 'Unknown';
            throw new \InvalidArgumentException("Validation error for field '{$fieldName}' at index {$index}: " . implode(', ', $errors));
        }

        // Additional validation
        if (in_array($field['DataType'], ['MultiSelectOne', 'MultiSelectMany']) && empty($field['FieldOptions'])) {
            throw new \InvalidArgumentException("Field '{$field['FieldName']}' with DataType '{$field['DataType']}' must have non-empty FieldOptions");
        }
    }
}