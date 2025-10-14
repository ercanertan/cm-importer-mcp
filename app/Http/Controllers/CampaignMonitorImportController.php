<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignMonitorImportRequest;
use App\Jobs\ImportCampaignMonitorCsvJob;
use App\Services\CampaignMonitorImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignMonitorImportController extends Controller
{
    protected $importService;

    public function __construct(CampaignMonitorImportService $importService)
    {
        $this->importService = $importService;
    }

    public function index()
    {
        $recentImports = $this->importService->getImportHistory();

        return view('campaign-monitor.import', [
            'recentImports' => $recentImports
        ]);
    }

    public function upload(CampaignMonitorImportRequest $request)
    {
        try {
            $file = $request->file('csv_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('campaign-monitor-imports', $filename, 'local');
            $fullPath = Storage::path($filePath);

            $validation = $this->importService->validateCsvFile($fullPath);

            if (!$validation['valid']) {
                return redirect()->back()
                    ->withErrors(['csv_file' => $validation['error']]);
            }

            $preview = $this->importService->previewCsv($fullPath);

            return view('campaign-monitor.import', [
                'uploadedFile' => $filePath,
                'preview' => $preview,
                'recentImports' => $this->importService->getImportHistory()
            ]);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['csv_file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string'
        ]);

        try {
            $filePath = Storage::path($request->file_path);

            if (config('campaign-monitor.queue_enabled', false)) {
                ImportCampaignMonitorCsvJob::dispatch($filePath);

                return redirect()->route('campaign-monitor.import')
                    ->with('success', 'Import job has been queued and will be processed in the background.');
            } else {
                // Create import log entry and store file path in log
                $log = $this->importService->createImportLogForFile($filePath, $request->file_path);

                // Redirect to Livewire progress component
                return redirect()->route('campaign-monitor.import-progress', [
                    'importId' => $log->id
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['import' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $import = $this->importService->getImportById($id);

        if (!$import) {
            abort(404);
        }

        return view('campaign-monitor.import-detail', [
            'import' => $import
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string'
        ]);

        try {
            $filePath = Storage::path($request->file_path);
            $preview = $this->importService->previewCsv($filePath, 10);

            return response()->json($preview);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function status($id)
    {
        $import = $this->importService->getImportById($id);

        if (!$import) {
            return response()->json(['error' => 'Import not found'], 404);
        }

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'progress_percentage' => $import->progress_percentage,
            'processed_rows' => $import->processed_rows,
            'total_rows' => $import->total_rows,
            'created_count' => $import->created_count,
            'updated_count' => $import->updated_count,
            'failed_count' => $import->failed_count,
            'duration' => $import->duration,
            'started_at' => $import->started_at,
            'completed_at' => $import->completed_at,
            'error_details' => $import->error_details
        ]);
    }

    public function progress($importId)
    {
        $import = $this->importService->getImportById($importId);

        if (!$import) {
            return redirect()->route('campaign-monitor.import')
                ->withErrors(['import' => 'Import not found']);
        }

        // Return Livewire component embedded in a simple view
        return view('campaign-monitor.import-progress-wrapper', [
            'importId' => $importId,
            'import' => $import
        ]);
    }

    public function startImport($importId)
    {
        $import = $this->importService->getImportById($importId);

        if (!$import || $import->status !== 'pending') {
            return response()->json(['error' => 'Import not found or already started'], 400);
        }

        try {
            $fullPath = Storage::path($import->storage_path);

            // Mark as processing
            $import->update(['status' => 'processing']);

            // Detect PHP binary
            $phpPath = file_exists('/usr/bin/php') ? '/usr/bin/php' : PHP_BINARY;
            if (str_contains($phpPath, 'fpm')) {
                $phpPath = str_replace('-fpm', '', $phpPath);
            }

            $logFile = storage_path('logs/import-' . $importId . '.log');
            $command = sprintf(
                'cd %s && nohup %s artisan campaign-monitor:process-import %s %d > %s 2>&1 &',
                escapeshellarg(base_path()),
                escapeshellarg($phpPath),
                escapeshellarg($fullPath),
                $importId,
                escapeshellarg($logFile)
            );

            // Execute in background
            exec($command);

            \Log::info('Started import via controller', [
                'import_id' => $importId,
                'command' => $command
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            $import->markAsFailed(['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
