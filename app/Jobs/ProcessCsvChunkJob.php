<?php

namespace App\Jobs;

use App\Models\CmImportLog;
use App\Services\CampaignMonitorImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes per chunk
    public $tries = 2;
    public $maxExceptions = 2;

    protected $chunkData;
    protected $headers;
    protected $logId;
    protected $chunkNumber;
    protected $totalChunks;

    /**
     * Create a new job instance.
     */
    public function __construct($chunkData, $headers, $logId, $chunkNumber, $totalChunks)
    {
        $this->chunkData = $chunkData;
        $this->headers = $headers;
        $this->logId = $logId;
        $this->chunkNumber = $chunkNumber;
        $this->totalChunks = $totalChunks;
        $this->onQueue(config('campaign-monitor.queue_name', 'imports'));
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignMonitorImportService $importService): void
    {
        try {
            // Optimize for chunk processing
            ini_set('memory_limit', '512M');

            Log::info("Processing CSV chunk {$this->chunkNumber}/{$this->totalChunks}", [
                'log_id' => $this->logId,
                'chunk_size' => count($this->chunkData),
                'memory_limit' => ini_get('memory_limit')
            ]);

            $startTime = microtime(true);

            // Process the chunk using PDO optimizations
            $log = CmImportLog::find($this->logId);
            if (!$log) {
                throw new \Exception("Import log not found: {$this->logId}");
            }

            $result = $importService->processChunkData($this->chunkData, $this->headers, $log);
            $duration = microtime(true) - $startTime;

            if ($result['success']) {
                $recordsPerSecond = count($this->chunkData) / max($duration, 0.001);

                Log::info("CSV chunk {$this->chunkNumber}/{$this->totalChunks} completed", [
                    'log_id' => $this->logId,
                    'duration_seconds' => round($duration, 2),
                    'processed_rows' => $result['processed'],
                    'created_count' => $result['created'],
                    'updated_count' => $result['updated'],
                    'failed_count' => $result['failed'],
                    'records_per_second' => round($recordsPerSecond, 0),
                    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
                ]);

                // Check if this is the last chunk
                $this->checkIfImportComplete($log);

            } else {
                Log::error("CSV chunk {$this->chunkNumber}/{$this->totalChunks} failed", [
                    'log_id' => $this->logId,
                    'error' => $result['error']
                ]);
            }

        } catch (\Exception $e) {
            Log::error("CSV chunk {$this->chunkNumber}/{$this->totalChunks} exception", [
                'log_id' => $this->logId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    protected function checkIfImportComplete($log)
    {
        // Update chunk completion status
        $completedChunks = $log->completed_chunks + 1;
        $log->update(['completed_chunks' => $completedChunks]);

        // If all chunks are complete, mark import as completed
        if ($completedChunks >= $this->totalChunks) {
            $log->markAsCompleted();

            Log::info("All CSV chunks completed for import", [
                'log_id' => $this->logId,
                'total_chunks' => $this->totalChunks,
                'total_processed' => $log->processed_rows
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CSV chunk {$this->chunkNumber}/{$this->totalChunks} failed permanently", [
            'log_id' => $this->logId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        if ($this->logId) {
            $log = CmImportLog::find($this->logId);
            if ($log) {
                \DB::table('cm_import_logs')->where('id', $this->logId)->increment('failed_chunks');
                $log->refresh(); // Reload from database

                // If too many chunks failed, mark entire import as failed
                if ($log->failed_chunks > ($this->totalChunks * 0.1)) { // 10% failure threshold
                    $log->markAsFailed([
                        'error' => "Too many chunk failures: {$exception->getMessage()}",
                        'failed_at' => now()->toISOString()
                    ]);
                }
            }
        }
    }
}