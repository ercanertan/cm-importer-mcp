<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Livewire\Admin\CustomFields\CustomFieldManager;
use App\Services\CustomFieldImportService;
use Livewire\Livewire;

// Clear any existing custom fields
\App\Models\CmCustomField::query()->delete();

echo "=== Debugging Full JSON Import Flow ===\n";

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
    // Step 1: Test the import service directly
    echo "1. Testing CustomFieldImportService directly...\n";
    $importService = new CustomFieldImportService();

    $validatedFields = $importService->parseJsonData(json_encode($jsonFields));
    echo "   Fields parsed: " . count($validatedFields) . "\n";

    $previewData = $importService->generatePreview($validatedFields);
    echo "   Preview generated:\n";
    echo "     - New: " . count($previewData['new']) . "\n";
    echo "     - Updated: " . count($previewData['updated']) . "\n";
    echo "     - Duplicates: " . count($previewData['duplicates']) . "\n";
    echo "     - Invalid: " . count($previewData['invalid']) . "\n";

    if (!empty($previewData['new'])) {
        echo "     - First new field index: " . $previewData['new'][0]['index'] . "\n";
    }

    // Step 2: Test applyChanges directly
    echo "\n2. Testing applyChanges directly...\n";
    $selectedIndices = array_column($previewData['new'], 'index');
    echo "   Selected indices: " . json_encode($selectedIndices) . "\n";

    $results = $importService->applyChanges($previewData, $selectedIndices);
    echo "   Apply results:\n";
    echo "     - Created: " . $results['created'] . "\n";
    echo "     - Updated: " . $results['updated'] . "\n";
    echo "     - Skipped: " . $results['skipped'] . "\n";
    echo "     - Errors: " . count($results['errors']) . "\n";

    // Clear database for next test
    \App\Models\CmCustomField::query()->delete();

    // Step 3: Test full Livewire flow with debugging
    echo "\n3. Testing full Livewire flow...\n";
    $livewire = Livewire::test(CustomFieldManager::class);

    echo "   a. Opening JSON modal...\n";
    $livewire->call('openJsonModal');

    echo "   b. Setting JSON input...\n";
    $livewire->set('jsonInput', json_encode($jsonFields));

    echo "   c. Processing JSON input...\n";
    $livewire->call('processJsonInput');

    // Check state after processing
    $previewData = $livewire->get('previewData');
    $selectedIndices = $livewire->get('selectedPreviewIndices');
    echo "   d. After processJsonInput:\n";
    echo "      - Preview new count: " . count($previewData['new'] ?? []) . "\n";
    echo "      - Selected indices: " . json_encode($selectedIndices) . "\n";

    echo "   e. Applying changes...\n";
    $livewire->call('applyJsonChanges');

    // Check final state
    $showModal = $livewire->get('showPreviewModal');
    echo "   f. After applyJsonChanges:\n";
    echo "      - showPreviewModal: " . ($showModal ? 'true' : 'false') . "\n";

    echo "   g. Checking database...\n";
    $fieldExists = \App\Models\CmCustomField::where('field_key', 'ImportedField')->exists();
    echo "      - Field exists: " . ($fieldExists ? 'true' : 'false') . "\n";

    echo "\n4. Testing session assertions manually...\n";
    try {
        // Try to get the response object
        $response = $livewire->testResponse;
        if ($response && method_exists($response, 'getSession')) {
            $session = $response->getSession();
            $hasMessage = $session->has('message');
            echo "   Session has message: " . ($hasMessage ? 'true' : 'false') . "\n";
            if ($hasMessage) {
                $message = $session->get('message');
                echo "   Session message: " . $message . "\n";
            }
        } else {
            echo "   Could not access session from test response\n";
        }
    } catch (\Exception $e) {
        echo "   Error checking session: " . $e->getMessage() . "\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";