<?php

namespace App\Livewire\CampaignMonitor;

use App\Models\CmImportLog;
use App\Services\CampaignMonitorImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ImportProgress extends Component
{
    public $importId;
    public $import;
    public $isProcessing = false;
    public $isComplete = false;
    public $hasError = false;
    public $errorMessage = '';

    public function mount($importId)
    {
        $this->importId = $importId;
        $this->import = CmImportLog::find($importId);

        if (!$this->import) {
            $this->hasError = true;
            $this->errorMessage = 'Import log not found';
            return;
        }

        // Check if import is already completed or failed
        if (in_array($this->import->status, ['completed', 'failed'])) {
            $this->isComplete = true;
            $this->isProcessing = false;

            if ($this->import->status === 'failed') {
                $this->hasError = true;
                $this->errorMessage = $this->import->error_details['error'] ?? 'Import failed';
            }
            return;
        }

        // Check if import is being processed by queue (no storage_path means queue import)
        if (!$this->import->storage_path) {
            // This is a queue import, just show the progress
            $this->isProcessing = ($this->import->status === 'processing' || $this->import->status === 'pending');
            return;
        }

        // Check if import is already processing (non-queue)
        if ($this->import->status === 'processing') {
            $this->isProcessing = true;
            return;
        }

        // Import will auto-start via wire:init if status is pending (non-queue only)
    }

    public function startImport()
    {
        if ($this->isProcessing || $this->isComplete || $this->hasError) {
            return;
        }

        if ($this->import->status !== 'pending') {
            return;
        }

        $this->isProcessing = true;

        // Start the import in background (no queue required)
        try {
            $fullPath = Storage::path($this->import->storage_path);

            // Mark as processing immediately
            $this->import->update(['status' => 'processing']);
            $this->isProcessing = true;

            // Use shell_exec with nohup for true background execution
            // Detect PHP binary: use /usr/bin/php in production, Herd in local
            $phpPath = file_exists('/usr/bin/php') ? '/usr/bin/php' : PHP_BINARY;

            // If PHP_BINARY is php-fpm (common with Herd), find the CLI version
            if (str_contains($phpPath, 'fpm')) {
                $phpPath = str_replace('-fpm', '', $phpPath);
            }

            $logFile = storage_path('logs/import-' . $this->importId . '.log');
            $command = sprintf(
                'cd %s && nohup %s artisan campaign-monitor:process-import %s %d > %s 2>&1 &',
                escapeshellarg(base_path()),
                escapeshellarg($phpPath),
                escapeshellarg($fullPath),
                $this->importId,
                escapeshellarg($logFile)
            );

            // Execute command without waiting for output (faster)
            exec($command);

            \Log::info('Started import process', [
                'import_id' => $this->importId,
                'command' => $command
            ]);

        } catch (\Exception $e) {
            $this->hasError = true;
            $this->errorMessage = $e->getMessage();
            if ($this->import) {
                $this->import->markAsFailed(['error' => $e->getMessage()]);
            }
        }
    }

    public function refreshImport()
    {
        $this->import = CmImportLog::find($this->importId);

        if ($this->import) {
            $this->isComplete = in_array($this->import->status, ['completed', 'failed']);
            $this->isProcessing = $this->import->status === 'processing';

            if ($this->import->status === 'failed') {
                $this->hasError = true;
                $this->errorMessage = $this->import->error_details['error'] ?? 'Import failed';
            }
        }
    }

    public function getProgressPercentageProperty()
    {
        return $this->import ? $this->import->progress_percentage : 0;
    }

    public function render()
    {
        return view('livewire.campaign-monitor.import-progress');
    }
}
