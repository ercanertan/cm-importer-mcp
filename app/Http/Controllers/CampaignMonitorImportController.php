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
            // If file type selection is disabled, default to 'active'
            $fileType = config('campaign-monitor.enable_file_type_selection', false)
                ? $request->input('file_type', 'active')
                : 'active';
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
                'fileType' => $fileType,
                'recentImports' => $this->importService->getImportHistory()
            ]);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['csv_file' => 'Error processing file: ' . $e->getMessage()]);
        }
    }

    public function import(Request $request)
    {
        $validationRules = [
            'file_path' => 'required|string',
        ];

        // Only require file_type validation if the feature is enabled
        if (config('campaign-monitor.enable_file_type_selection', false)) {
            $validationRules['file_type'] = 'required|string|in:all,active,bounced,deleted,unsubscribed';
        }

        $request->validate($validationRules);

        try {
            $filePath = Storage::path($request->file_path);
            // If file type selection is disabled, default to 'active'
            $fileType = config('campaign-monitor.enable_file_type_selection', false)
                ? $request->input('file_type', 'active')
                : 'active';

            if (config('campaign-monitor.queue_enabled', false)) {
                // Create import log before dispatching job (no storage_path for queue imports)
                $log = $this->importService->createImportLogForFile($filePath, null, $fileType);

                // Dispatch job with log ID
                ImportCampaignMonitorCsvJob::dispatch($filePath, $fileType, $log->id);

                return redirect()->route('campaign-monitor.import')
                    ->with('success', 'Import job has been queued and will be processed in the background.');
            } else {
                // Create import log entry and store file path in log
                $log = $this->importService->createImportLogForFile($filePath, $request->file_path, $fileType);

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

        // Eager load the user relationship
        $import->load('user');

        // Get all existing custom fields from before this import
        $existingFieldKeys = \App\Models\CmCustomField::where('created_at', '<', $import->created_at)
            ->pluck('field_key')
            ->toArray();

        // Identify newly detected fields
        $detectedFields = array_filter($import->custom_fields_detected ?? [], fn($field) => !empty($field));
        $newFields = array_values(array_filter(array_diff($detectedFields, $existingFieldKeys), fn($field) => !empty($field)));
        $existingFields = array_values(array_filter(array_intersect($detectedFields, $existingFieldKeys), fn($field) => !empty($field)));

        return view('campaign-monitor.import-detail', [
            'import' => $import,
            'newCustomFields' => $newFields,
            'existingCustomFields' => $existingFields
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
