<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CustomFieldImportService;

$jsonFields = [
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
    ]
];

$service = new CustomFieldImportService();

// First validate the data
try {
    $validatedFields = $service->parseJsonData(json_encode($jsonFields));
    echo "Parse validation successful\n";
} catch (\Exception $e) {
    echo "Parse validation failed: " . $e->getMessage() . "\n";
    return;
}

// Then generate preview
try {
    $preview = $service->generatePreview($validatedFields);
    echo "Generate preview successful\n";
} catch (\Exception $e) {
    echo "Generate preview failed: " . $e->getMessage() . "\n";
    return;
}

echo "=== Preview Data ===\n";
echo "New fields: " . count($preview['new']) . "\n";
echo "Invalid fields: " . count($preview['invalid']) . "\n";

if (!empty($preview['new'])) {
    echo "First new field name: " . $preview['new'][0]['external']['field_name'] . "\n";
} else {
    echo "No new fields found!\n";
}

if (!empty($preview['invalid'])) {
    echo "First invalid field error: " . $preview['invalid'][0]['error'] . "\n";
} else {
    echo "No invalid fields found!\n";
}

echo "\nStatistics:\n";
print_r($preview['statistics']);