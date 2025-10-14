<?php

namespace App\Console\Commands;

use App\Services\CampaignMonitorImportService;
use Illuminate\Console\Command;

class ImportCampaignMonitorCsv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:import {file : CSV file path to import} {--preview : Preview the CSV without importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Campaign Monitor subscribers from a CSV file';

    protected $importService;

    public function __construct(CampaignMonitorImportService $importService)
    {
        parent::__construct();
        $this->importService = $importService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');
        $preview = $this->option('preview');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        if ($preview) {
            return $this->previewCsv($filePath);
        }

        return $this->importCsv($filePath);
    }

    protected function previewCsv($filePath)
    {
        $this->info("Previewing CSV file: {$filePath}");
        $this->newLine();

        $preview = $this->importService->previewCsv($filePath, 10);

        if (!$preview['valid']) {
            $this->error("Invalid CSV: " . $preview['error']);
            return 1;
        }

        $this->table(['Field', 'Type'], collect($preview['headers'])->map(function ($header) use ($preview) {
            $isCustomField = in_array($header, $preview['custom_fields']);
            return [
                $header,
                $isCustomField ? 'Custom Field' : 'Standard Field'
            ];
        })->toArray());

        $this->newLine();
        $this->info("Sample Data:");

        if (!empty($preview['preview'])) {
            $this->table($preview['headers'], array_map('array_values', $preview['preview']));
        }

        $this->newLine();
        $this->info("Custom fields detected: " . (count($preview['custom_fields']) > 0 ? implode(', ', $preview['custom_fields']) : 'None'));

        return 0;
    }

    protected function importCsv($filePath)
    {
        $this->info("Starting import from: {$filePath}");

        $validation = $this->importService->validateCsvFile($filePath);

        if (!$validation['valid']) {
            $this->error("Invalid CSV: " . $validation['error']);
            return 1;
        }

        $confirmed = $this->confirm('Do you want to proceed with the import?');

        if (!$confirmed) {
            $this->info('Import cancelled.');
            return 0;
        }

        $this->withProgressBar(range(1, 100), function () use ($filePath) {
            $result = $this->importService->importFromCsv($filePath);

            if (!$result['success']) {
                $this->newLine();
                $this->error("Import failed: " . $result['error']);
                return 1;
            }

            $this->newLine();
            $this->info("Import completed successfully!");

            $log = $result['log'];
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Rows', $log->total_rows],
                    ['Processed', $log->processed_rows],
                    ['Created', $log->created_count],
                    ['Updated', $log->updated_count],
                    ['Failed', $log->failed_count],
                    ['Duration', $log->duration . ' seconds']
                ]
            );

            if ($log->failed_count > 0) {
                $this->warn("Some rows failed to import. Check the logs for details.");
            }

            return 0;
        });

        return 0;
    }
}
