<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Livewire\Admin\CustomFields\CustomFieldManager;
use Livewire\Livewire;

// Clear any existing custom fields
\App\Models\CmCustomField::query()->delete();

echo "=== Testing Livewire Session Assertions ===\n";

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
    $livewire = Livewire::test(CustomFieldManager::class)
        ->call('openJsonModal')
        ->set('jsonInput', json_encode($jsonFields))
        ->call('processJsonInput')
        ->call('applyJsonChanges');

    echo "1. Component executed successfully\n";

    // Test different session assertion methods
    echo "2. Testing session assertions...\n";

    try {
        $livewire->assertSessionHas('message');
        echo "   assertSessionHas('message') passed\n";
    } catch (\Exception $e) {
        echo "   assertSessionHas('message') failed: " . $e->getMessage() . "\n";
    }

    try {
        $livewire->assertSessionHas('message', 'Import completed: 1 created, 0 updated, 0 skipped.');
        echo "   assertSessionHas with exact message passed\n";
    } catch (\Exception $e) {
        echo "   assertSessionHas with exact message failed: " . $e->getMessage() . "\n";
    }

    try {
        $livewire->assertSessionMissing('message');
        echo "   assertSessionMissing passed (unexpected)\n";
    } catch (\Exception $e) {
        echo "   assertSessionMissing failed (expected): " . $e->getMessage() . "\n";
    }

    echo "3. Test completed\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";