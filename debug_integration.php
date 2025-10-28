<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CustomFieldImportService;
use App\Models\CmCustomField;

// Clear any existing custom fields
CmCustomField::query()->delete();

echo "=== Debugging Custom Field Creation ===\n";

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

try {
    $service = new CustomFieldImportService();

    // Parse and validate
    echo "1. Parsing JSON data...\n";
    $fields = $service->parseJsonData($jsonContent);
    echo "   Parsed " . count($fields) . " fields\n";

    // Generate preview
    echo "2. Generating preview...\n";
    $preview = $service->generatePreview($fields);
    echo "   New fields: " . count($preview['new']) . "\n";

    // Apply changes
    echo "3. Applying changes...\n";
    $results = $service->applyChanges($preview, array_column($preview['new'], 'index'));
    echo "   Results: " . json_encode($results) . "\n";

    // Check what was created
    echo "4. Checking created fields...\n";
    $jobTitle = CmCustomField::where('field_key', 'JobTitle')->first();
    if ($jobTitle) {
        echo "   JobTitle field created:\n";
        echo "     field_name: " . $jobTitle->field_name . "\n";
        echo "     field_key: " . $jobTitle->field_key . "\n";
        echo "     data_type: " . $jobTitle->data_type->value . "\n";
        echo "     external_key: " . ($jobTitle->external_key ?? 'NULL') . "\n";
        echo "     is_user_editable: " . ($jobTitle->is_user_editable ? 'true' : 'false') . "\n";
        echo "     is_active: " . ($jobTitle->is_active ? 'true' : 'false') . "\n";
    } else {
        echo "   JobTitle field NOT found!\n";
    }

    $department = CmCustomField::where('field_key', 'Department')->first();
    if ($department) {
        echo "   Department field created:\n";
        echo "     field_name: " . $department->field_name . "\n";
        echo "     field_key: " . $department->field_key . "\n";
        echo "     data_type: " . $department->data_type->value . "\n";
        echo "     external_key: " . ($department->external_key ?? 'NULL') . "\n";
        echo "     is_user_editable: " . ($department->is_user_editable ? 'true' : 'false') . "\n";
        echo "     is_active: " . ($department->is_active ? 'true' : 'false') . "\n";
    } else {
        echo "   Department field NOT found!\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";