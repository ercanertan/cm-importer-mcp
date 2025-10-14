<?php

namespace App\Console\Commands;

use App\Services\CampaignMonitorImportService;
use Illuminate\Console\Command;

class ProcessCampaignMonitorImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign-monitor:process-import {filePath} {importId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process a Campaign Monitor CSV import in the background';

    /**
     * Execute the console command.
     */
    public function handle(CampaignMonitorImportService $importService)
    {
        $filePath = $this->argument('filePath');
        $importId = $this->argument('importId');

        $this->info("Starting import for file: {$filePath}");

        try {
            $result = $importService->importFromCsvChunked($filePath, $importId);

            if ($result['success']) {
                $this->info("Import completed successfully!");
                $this->info("Processed: {$result['log']->processed_rows} rows");
                $this->info("Created: {$result['log']->created_count}");
                $this->info("Updated: {$result['log']->updated_count}");
                $this->info("Failed: {$result['log']->failed_count}");
            } else {
                $this->error("Import failed: {$result['error']}");
            }
        } catch (\Exception $e) {
            $this->error("Import exception: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
