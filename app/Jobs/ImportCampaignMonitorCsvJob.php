<?php

namespace App\Jobs;

use App\Services\CampaignMonitorImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportCampaignMonitorCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200; // 2 hours for very large files
    public $tries = 1;
    public $maxExceptions = 1;

    protected $filePath;
    protected $fileType;
    protected $logId;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $fileType = 'active', $logId = null)
    {
        $this->filePath = $filePath;
        $this->fileType = $fileType;
        $this->logId = $logId;
        $this->onQueue(config('campaign-monitor.queue_name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignMonitorImportService $importService): void
    {
        try {
            // Optimize memory and performance for large imports
            ini_set('memory_limit', '2G');

            Log::info('Starting Campaign Monitor CSV import job', [
                'file' => $this->filePath,
                'file_type' => $this->fileType,
                'log_id' => $this->logId,
                'queue' => $this->queue,
                'memory_limit' => ini_get('memory_limit')
            ]);

            // Early check for file existence
            if (!file_exists($this->filePath)) {
                Log::error('Campaign Monitor CSV file not found', [
                    'file' => $this->filePath,
                    'log_id' => $this->logId
                ]);
                throw new \Exception("File not found: {$this->filePath}");
            }

            // Create import log if not provided
            if (!$this->logId) {
                $log = $importService->createImportLogForFile($this->filePath, null, $this->fileType);
                $this->logId = $log->id;
            }

            $startTime = microtime(true);

            // Check file size to determine if we should use chunked processing
            $rowCount = $this->estimateRowCount($this->filePath);
            $chunkThreshold = config('campaign-monitor.queue_chunk_size', 5000);

            if ($rowCount > $chunkThreshold) {
                Log::info("Large file detected ({$rowCount} rows), using chunked processing");
                $result = $importService->importFromCsvChunked($this->filePath, $this->logId);
            } else {
                Log::info("Small file detected ({$rowCount} rows), using direct processing");
                $result = $importService->importFromCsv($this->filePath, $this->logId);
            }

            $duration = microtime(true) - $startTime;

            if ($result['success']) {
                $recordsPerSecond = $result['log']->processed_rows / max($duration, 0.001);

                Log::info('Campaign Monitor CSV import job completed successfully', [
                    'file' => $this->filePath,
                    'duration_seconds' => round($duration, 2),
                    'processed_rows' => $result['log']->processed_rows,
                    'created_count' => $result['log']->created_count,
                    'updated_count' => $result['log']->updated_count,
                    'failed_count' => $result['log']->failed_count,
                    'records_per_second' => round($recordsPerSecond, 0),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
                ]);
            } else {
                Log::error('Campaign Monitor CSV import job failed', [
                    'file' => $this->filePath,
                    'error' => $result['error']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Campaign Monitor CSV import job exception', [
                'file' => $this->filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign Monitor CSV import job failed permanently', [
            'file' => $this->filePath,
            'log_id' => $this->logId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->logId) {
            $importService = app(CampaignMonitorImportService::class);
            $log = $importService->getImportById($this->logId);

            if ($log) {
                $log->markAsFailed([
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toISOString()
                ]);
            }
        }
    }

    protected function estimateRowCount($filePath)
    {
        $handle = fopen($filePath, 'r');
        $rowCount = 0;

        // Quick estimate by reading file in chunks
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $rowCount += substr_count($chunk, "\n");
        }

        fclose($handle);
        return max($rowCount - 1, 0); // Subtract 1 for header
    }
}
