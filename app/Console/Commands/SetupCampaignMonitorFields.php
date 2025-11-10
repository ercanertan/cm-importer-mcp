<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupCampaignMonitorFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:setup-fields
                            {--force : Force creation even if fields exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up the 4 core custom fields in Campaign Monitor with admin-friendly labels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up Campaign Monitor custom fields...');
        $this->newLine();

        // Check if API credentials are configured
        if (!config('campaign-monitor.api_key') || !config('campaign-monitor.list_id')) {
            $this->error('Campaign Monitor API credentials not configured!');
            $this->error('Please set CM_API_KEY and CM_LIST_ID in your .env file.');
            return Command::FAILURE;
        }

        // Initialize CM API client
        $auth = ['api_key' => config('campaign-monitor.api_key')];
        $list = new \CS_REST_Lists(config('campaign-monitor.list_id'), $auth);

        // Get existing custom fields
        $this->info('Fetching existing custom fields from Campaign Monitor...');
        $result = $list->get_custom_fields();

        if (!$result->was_successful()) {
            $this->error('Failed to fetch custom fields from Campaign Monitor.');
            $this->error('Error: ' . $result->response);
            return Command::FAILURE;
        }

        $existingFields = collect($result->response)->pluck('Key')->toArray();
        $this->info('Found ' . count($existingFields) . ' existing custom fields.');
        $this->newLine();

        // Create the 4 core fields
        $coreFields = config('campaign-monitor.core_fields');
        $created = 0;
        $skipped = 0;

        foreach ($coreFields as $fieldConfig) {
            $fieldKey = $fieldConfig['key'];
            $fieldName = $fieldConfig['name'];

            // Check if field already exists
            if (in_array($fieldKey, $existingFields) && !$this->option('force')) {
                $this->line("⏭️  Skipped: {$fieldName} ({$fieldKey}) - already exists");
                $skipped++;
                continue;
            }

            // Create the field
            $this->info("Creating: {$fieldName} ({$fieldKey})...");

            $createResult = $list->create_custom_field([
                'FieldName' => $fieldConfig['name'],
                'Key' => $fieldConfig['key'],
                'DataType' => $fieldConfig['data_type'],
                'Options' => [],
                'VisibleInPreferenceCenter' => $fieldConfig['visible_in_preference_center'],
            ]);

            if ($createResult->was_successful()) {
                $this->info("✅ Created: {$fieldName}");
                $created++;
            } else {
                // Check if error is because field already exists
                if (str_contains($createResult->response->Message ?? '', 'already exists')) {
                    $this->line("⏭️  Skipped: {$fieldName} - already exists");
                    $skipped++;
                } else {
                    $this->error("❌ Failed: {$fieldName}");
                    $this->error('Error: ' . ($createResult->response->Message ?? 'Unknown error'));
                }
            }
        }

        $this->newLine();
        $this->info("===================================");
        $this->info("Setup Complete!");
        $this->info("===================================");
        $this->info("Created: {$created} field(s)");
        $this->info("Skipped: {$skipped} field(s)");
        $this->newLine();

        // Display the created fields
        $this->info('Core fields in Campaign Monitor:');
        $this->table(
            ['Field Key', 'Field Name', 'Type', 'Visible in Preference Center'],
            collect($coreFields)->map(function ($field) {
                return [
                    $field['key'],
                    $field['name'],
                    $field['data_type'],
                    $field['visible_in_preference_center'] ? 'Yes' : 'No',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line('1. Run: php artisan cm:backfill-core-fields (to sync existing users)');
        $this->line('2. Run: php artisan cm:setup-segments (to create smart segments)');

        return Command::SUCCESS;
    }
}
