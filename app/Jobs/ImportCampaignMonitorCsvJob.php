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

    public $timeout = 3600;
    public $tries = 1;

    protected $filePath;
    protected $logId;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath, $logId = null)
    {
        $this->filePath = $filePath;
        $this->logId = $logId;
        $this->onQueue(config('campaign-monitor.queue_name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignMonitorImportService $importService): void
    {
        try {
            Log::info('Starting Campaign Monitor CSV import job', [
                'file' => $this->filePath,
                'log_id' => $this->logId
            ]);

            $result = $importService->importFromCsv($this->filePath, $this->logId);

            if ($result['success']) {
                Log::info('Campaign Monitor CSV import job completed successfully', [
                    'file' => $this->filePath,
                    'processed_rows' => $result['log']->processed_rows,
                    'created_count' => $result['log']->created_count,
                    'updated_count' => $result['log']->updated_count,
                    'failed_count' => $result['log']->failed_count
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
}
