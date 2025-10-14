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
                // Create import log entry
                $log = $this->importService->createImportLogForFile($filePath);

                // Return view with import progress UI
                return view('campaign-monitor.import-progress', [
                    'importId' => $log->id,
                    'filePath' => $request->file_path
                ]);
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['import' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Stream import progress using Server-Sent Events
     */
    public function streamImport(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'import_id' => 'required|integer'
        ]);

        return response()->stream(function () use ($request) {
            $filePath = Storage::path($request->file_path);
            $importId = $request->import_id;

            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable nginx buffering

            // Callback for progress updates
            $progressCallback = function ($data) {
                echo "data: " . json_encode($data) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            try {
                // Run import with progress callback
                $result = $this->importService->importFromCsvWithProgress(
                    $filePath,
                    $importId,
                    $progressCallback
                );

                // Send final result
                echo "data: " . json_encode([
                    'type' => 'complete',
                    'success' => $result['success'],
                    'message' => $result['message'] ?? 'Import completed',
                    'log' => [
                        'processed_rows' => $result['log']->processed_rows,
                        'created_count' => $result['log']->created_count,
                        'updated_count' => $result['log']->updated_count,
                        'failed_count' => $result['log']->failed_count,
                    ]
                ]) . "\n\n";

            } catch (\Exception $e) {
                echo "data: " . json_encode([
                    'type' => 'error',
                    'message' => $e->getMessage()
                ]) . "\n\n";
            }

            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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
}
