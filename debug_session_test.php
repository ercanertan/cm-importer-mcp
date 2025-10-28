<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Livewire\Admin\CustomFields\CustomFieldManager;
use Livewire\Livewire;

// Clear any existing custom fields
\App\Models\CmCustomField::query()->delete();

echo "=== Testing JSON Import Session Behavior ===\n";

$jsonFields = [
    [
        'FieldName' => 'Imported Field',
        'Key' => '[ImportedField]',
        'DataType' => 'Text',
        'FieldOptions' => [],
        'VisibleInPreferenceCenter' => true,
    ]
];

try {
    // Create the Livewire component
    $livewire = Livewire::test(CustomFieldManager::class);

    echo "1. Opening JSON modal...\n";
    $livewire->call('openJsonModal');

    echo "2. Setting JSON input...\n";
    $livewire->set('jsonInput', json_encode($jsonFields));

    echo "3. Processing JSON input...\n";
    $livewire->call('processJsonInput');

    echo "4. Applying changes...\n";
    $livewire->call('applyJsonChanges');

    echo "5. Checking showPreviewModal state...\n";
    $showModal = $livewire->get('showPreviewModal');
    echo "   showPreviewModal: " . ($showModal ? 'true' : 'false') . "\n";

    echo "6. Checking session messages...\n";
    try {
        // Try to access session through testable methods
        $hasSession = $livewire->hasSession('message');
        echo "   Has session message: " . ($hasSession ? 'true' : 'false') . "\n";

        if ($hasSession) {
            $message = $livewire->getSession('message');
            echo "   Session message: " . $message . "\n";
        }
    } catch (\Exception $e) {
        echo "   Error checking session: " . $e->getMessage() . "\n";
    }

    echo "7. Checking database for created field...\n";
    $fieldExists = \App\Models\CmCustomField::where('field_key', 'ImportedField')->exists();
    echo "   Field exists: " . ($fieldExists ? 'true' : 'false') . "\n";

    if ($fieldExists) {
        $field = \App\Models\CmCustomField::where('field_key', 'ImportedField')->first();
        echo "   Field data: " . json_encode([
            'field_name' => $field->field_name,
            'field_key' => $field->field_key,
            'data_type' => $field->data_type
        ]) . "\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";