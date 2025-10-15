<?php

namespace App\Services;

use App\Models\User;
use App\Models\CmCustomField;
use App\Models\CmImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use PDO;

class CampaignMonitorImportService
{
    protected $config;
    protected $log;
    protected $batchCounter = 0;

    public function __construct()
    {
        $this->config = config('campaign-monitor');
    }

    protected function convertToFieldKey($fieldName)
    {
        // Remove spaces and special characters to create a clean field_key
        // Example: "Ellucian Fringe" becomes "EllucianFringe"
        return str_replace(' ', '', $fieldName);
    }

    protected function normalizeHeaders($headers)
    {
        $normalizedHeaders = [];
        $emailColumnNames = ['email', 'Email Address', 'email address', 'Email', 'EMAIL', 'EMAIL ADDRESS'];
        $nameColumnNames = ['name', 'Name', 'fullname', 'Fullname', 'Full Name', 'firstname', 'Firstname', 'First Name', 'lastname', 'Lastname', 'Last Name', 'NAME', 'FULLNAME', 'FIRSTNAME', 'LASTNAME'];

        foreach ($headers as $header) {
            // Remove all quotes (single and double) and trim whitespace
            $trimmedHeader = trim($header);
            $trimmedHeader = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $trimmedHeader);
            $trimmedHeader = str_replace(['"', "'"], '', $trimmedHeader);
            $trimmedHeader = trim($trimmedHeader);

            // Normalize email column names to 'email'
            if (in_array($trimmedHeader, $emailColumnNames)) {
                $normalizedHeaders[] = 'email';
            }
            // Normalize name column names to 'fullname'
            elseif (in_array($trimmedHeader, $nameColumnNames)) {
                $normalizedHeaders[] = 'fullname';
            }
            // Handle Campaign Monitor standard fields
            elseif (strtolower($trimmedHeader) === 'date active') {
                $normalizedHeaders[] = 'cm_subscribed_at';
            }
            elseif (strtolower($trimmedHeader) === 'date joined') {
                // Only use date joined if we haven't seen date active yet
                if (!in_array('cm_subscribed_at', $normalizedHeaders)) {
                    $normalizedHeaders[] = 'cm_subscribed_at';
                } else {
                    $normalizedHeaders[] = 'date_joined'; // Treat as custom field
                }
            }
            elseif (strtolower($trimmedHeader) === 'permission to track') {
                $normalizedHeaders[] = 'permission_to_track';
            }
            else {
                // Convert to field_key format (no spaces) for custom fields
                $normalizedHeaders[] = $this->convertToFieldKey($trimmedHeader);
            }
        }

        return $normalizedHeaders;
    }

    protected function findEmailColumn($headers)
    {
        $emailColumnNames = ['email', 'Email Address', 'email address', 'Email', 'EMAIL', 'EMAIL ADDRESS'];

        foreach ($headers as $header) {
            if (in_array(trim($header), $emailColumnNames)) {
                return true;
            }
        }

        return false;
    }


    public function importFromCsv($filePath, $logId = null)
    {
        try {
            // Set unlimited execution time for large imports
            set_time_limit(0);
            ini_set('memory_limit', '1G');

            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("File not found or not readable: {$filePath}");
            }

            // Generate file hash for tracking (but don't prevent duplicates)
            $fileHash = hash_file('sha256', $filePath);

            $this->log = $logId ? CmImportLog::find($logId) : $this->createImportLog(basename($filePath), $fileHash);
            $this->log->markAsStarted();

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception("Cannot open file: {$filePath}");
            }

            $originalHeaders = fgetcsv($handle);
            if (!$originalHeaders) {
                throw new Exception("Cannot read CSV headers");
            }

            $originalHeaders = array_map('trim', $originalHeaders);
            $headers = $this->normalizeHeaders($originalHeaders);
            $totalRows = $this->countCsvRows($filePath) - 1;
            $this->log->update(['total_rows' => $totalRows]);

            $customFields = $this->detectCustomFields($headers);
            $this->log->update(['custom_fields_detected' => $customFields]);

            // Immediately create custom field definitions for all detected fields
            // This ensures fields exist even if all rows have empty values
            $this->ensureCustomFieldsExist($customFields);

            // Use larger batch size for better performance with large files
            $batchSize = $this->config['batch_size'] ?? 500;
            $batch = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    $this->log->incrementFailed();
                    $this->logError("Row {$rowNumber}: Column count mismatch");
                    continue;
                }

                $rowData = array_combine($headers, array_map('trim', $row));

                // Check if row is completely empty (all values are empty)
                if ($this->isEmptyRow($rowData)) {
                    $this->log->incrementFailed();
                    $this->logError("Row {$rowNumber}: Empty row - all fields are empty");
                    continue;
                }

                if (empty($rowData['email'])) {
                    $this->log->incrementFailed();
                    $this->logError("Row {$rowNumber}: Missing email");
                    continue;
                }

                $batch[] = $rowData;

                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $batch = [];

                    // Log progress for large imports every 1000 rows
                    if ($rowNumber % 1000 === 0) {
                        Log::info("Campaign Monitor import progress: {$rowNumber} rows processed");
                    }
                }
            }

            if (!empty($batch)) {
                $this->processBatch($batch);
            }

            // Final counter save to ensure all data is persisted
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

            DB::table('cm_import_logs')
                ->where('id', $this->log->id)
                ->update([
                    'processed_rows' => $this->log->processed_rows,
                    'created_count' => $this->log->created_count,
                    'updated_count' => $this->log->updated_count,
                    'failed_count' => $this->log->failed_count,
                    'memory_peak' => $memoryPeak,
                    'updated_at' => now()
                ]);

            fclose($handle);
            $this->log->markAsCompleted();

            return [
                'success' => true,
                'log' => $this->log,
                'message' => "Import completed successfully. Processed {$this->log->processed_rows} rows."
            ];

        } catch (Exception $e) {
            if ($this->log) {
                $this->log->markAsFailed(['error' => $e->getMessage()]);
            }

            Log::error('Campaign Monitor import failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    protected function processBatch($batch)
    {
        // ALWAYS use PDO for maximum performance
        return $this->processBatchWithPDO($batch);
    }

    protected function processBatchWithPDO($batch)
    {
        try {
            $pdo = DB::getPdo();
            $pdo->beginTransaction();

            // Separate new users from existing ones
            $emails = array_column($batch, 'email');
            $existingUsers = $this->getExistingUsersByEmail($emails);

            $newUsers = [];
            $updateUsers = [];
            $customFieldData = [];

            foreach ($batch as $rowData) {
                $email = $rowData['email'];

                if (isset($existingUsers[$email])) {
                    $updateUsers[] = $this->prepareUserUpdateData($rowData, $existingUsers[$email]);
                } else {
                    $newUsers[] = $this->prepareUserInsertData($rowData);
                }

                // Collect custom field data
                $customFieldData = array_merge($customFieldData, $this->prepareCustomFieldData($rowData, $email));
            }

            // Bulk insert new users
            if (!empty($newUsers)) {
                $this->bulkInsertUsers($pdo, $newUsers);
                $this->log->created_count += count($newUsers);
            }

            // Bulk update existing users
            if (!empty($updateUsers)) {
                $actuallyUpdated = $this->bulkUpdateUsers($pdo, $updateUsers);
                $this->log->updated_count += $actuallyUpdated;
            }

            // Process custom fields
            if (!empty($customFieldData)) {
                $this->bulkInsertCustomFields($pdo, $customFieldData);
            }

            $pdo->commit();

            // Update counters in memory (save every N batches to reduce DB writes)
            $this->log->processed_rows += count($batch);
            $this->batchCounter++;

            // Calculate memory usage every batch (but only save to DB every 10 batches)
            $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

            // Save to database every 10 batches to track progress and memory
            if ($this->batchCounter % 10 === 0) {
                DB::table('cm_import_logs')
                    ->where('id', $this->log->id)
                    ->update([
                        'processed_rows' => $this->log->processed_rows,
                        'created_count' => $this->log->created_count,
                        'updated_count' => $this->log->updated_count,
                        'failed_count' => $this->log->failed_count,
                        'memory_current' => $memoryUsage,
                        'memory_peak' => $memoryPeak,
                        'updated_at' => now()
                    ]);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $this->log->failed_count += count($batch);
            $this->logError("PDO batch processing failed: " . $e->getMessage());
            throw $e;
        }
    }

    protected function processRow($rowData)
    {
        $email = $rowData['email'];

        $user = User::where('email', $email)->first();
        $isNewUser = !$user;

        if ($isNewUser) {
            $user = new User();
            $user->email = $email;
            $fullname = $this->buildFullName($rowData);
            $user->fullname = $fullname ?: '';
            $user->cm_status = $this->determineStatus();

            // Handle subscription date
            if (isset($rowData['cm_subscribed_at']) && !empty($rowData['cm_subscribed_at'])) {
                $user->cm_subscribed_at = $this->parseDate($rowData['cm_subscribed_at']);
            } else {
                $user->cm_subscribed_at = now();
            }

            // Handle unsubscription date
            if (isset($rowData['cm_unsubscribed_at']) && !empty($rowData['cm_unsubscribed_at'])) {
                $user->cm_unsubscribed_at = $this->parseDate($rowData['cm_unsubscribed_at']);
            }

            // Handle permission to track
            if (isset($rowData['permission_to_track'])) {
                $user->permission_to_track = $this->parseBoolean($rowData['permission_to_track']);
            }

            $user->save();
            $this->processCustomFields($user, $rowData);
            return 'created';
        } else {
            $updated = false;

            $newName = $this->buildFullName($rowData);
            if ($newName && $user->fullname !== $newName) {
                $user->fullname = $newName;
                $updated = true;
            }

            if (isset($rowData['cm_subscriber_id']) && $user->cm_subscriber_id !== $rowData['cm_subscriber_id']) {
                $user->cm_subscriber_id = $rowData['cm_subscriber_id'];
                $updated = true;
            }

            // Update status based on file_type (unless file_type is 'all')
            $fileType = $this->log->file_type ?? 'all';
            if ($fileType !== 'all') {
                if ($user->cm_status !== $fileType) {
                    $user->cm_status = $fileType;
                    $updated = true;
                }
            } elseif (isset($rowData['cm_status']) && $user->cm_status !== $rowData['cm_status']) {
                $user->cm_status = $rowData['cm_status'];
                $updated = true;
            }

            // Update subscription date if provided
            if (isset($rowData['cm_subscribed_at']) && !empty($rowData['cm_subscribed_at'])) {
                $newSubscribedAt = $this->parseDate($rowData['cm_subscribed_at']);
                if ($user->cm_subscribed_at != $newSubscribedAt) {
                    $user->cm_subscribed_at = $newSubscribedAt;
                    $updated = true;
                }
            }

            // Update unsubscription date if provided
            if (isset($rowData['cm_unsubscribed_at']) && !empty($rowData['cm_unsubscribed_at'])) {
                $newUnsubscribedAt = $this->parseDate($rowData['cm_unsubscribed_at']);
                if ($user->cm_unsubscribed_at != $newUnsubscribedAt) {
                    $user->cm_unsubscribed_at = $newUnsubscribedAt;
                    $updated = true;
                }
            }

            // Update permission to track if provided
            if (isset($rowData['permission_to_track'])) {
                $newPermissionToTrack = $this->parseBoolean($rowData['permission_to_track']);
                if ($user->permission_to_track != $newPermissionToTrack) {
                    $user->permission_to_track = $newPermissionToTrack;
                    $updated = true;
                }
            }

            if ($updated) {
                $user->save();
            }

            $this->processCustomFields($user, $rowData);
            return $updated ? 'updated' : 'skipped';
        }
    }

    protected function processCustomFields($user, $rowData)
    {
        $standardFields = [
            'email',
            'fullname',
            'cm_subscriber_id',
            'cm_status',
            'cm_subscribed_at',
            'cm_unsubscribed_at',
            'permission_to_track',
            'date_active',
            'date_joined'
        ];

        foreach ($rowData as $fieldKey => $value) {
            // Skip standard fields, empty values, or empty field keys
            if (in_array($fieldKey, $standardFields) || empty($value) || empty(trim($fieldKey))) {
                continue;
            }

            // Generate a readable field_name by adding spaces before capital letters
            // Example: "EllucianFringe" becomes "Ellucian Fringe"
            $fieldName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $fieldKey);

            $field = CmCustomField::firstOrCreate(
                ['field_key' => $fieldKey],
                [
                    'field_name' => $fieldName,
                    'data_type' => $this->detectDataType($value),
                    'is_active' => true
                ]
            );

            $field->updateLastSeen();

            $user->customFieldValues()->updateOrCreate(
                ['cm_custom_field_id' => $field->id],
                ['value' => $value]
            );
        }
    }

    protected function detectCustomFields($headers)
    {
        $standardFields = [
            'email',
            'fullname',
            'cm_subscriber_id',
            'cm_status',
            'cm_subscribed_at',
            'cm_unsubscribed_at',
            'permission_to_track',
            'date_active',
            'date_joined'
        ];

        // Filter out empty strings and whitespace-only values
        $customFields = array_diff($headers, $standardFields);
        $customFields = array_filter($customFields, function($field) {
            return !empty(trim($field));
        });

        return array_values($customFields); // Re-index array
    }

    protected function detectDataType($value)
    {
        if (is_numeric($value)) {
            return 'number';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return 'date';
        }

        if (strpos($value, ',') !== false || strpos($value, ';') !== false) {
            return 'multi_select';
        }

        return 'text';
    }

    protected function countCsvRows($filePath)
    {
        // For large files, use a faster line counting method
        $handle = fopen($filePath, 'r');
        $rowCount = 0;

        // Read in larger chunks for better performance
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            $rowCount += substr_count($chunk, "\n");
        }

        fclose($handle);

        // Subtract 1 for header row if file has content
        return $rowCount > 0 ? $rowCount - 1 : 0;
    }

    protected function createImportLog($filename, $fileHash = null)
    {
        return CmImportLog::create([
            'filename' => $filename,
            'file_hash' => $fileHash,
            'status' => 'pending'
        ]);
    }

    public function createImportLogForFile($filePath, $storagePath = null, $fileType = 'all')
    {
        $fileHash = hash_file('sha256', $filePath);
        return CmImportLog::create([
            'filename' => basename($filePath),
            'file_hash' => $fileHash,
            'storage_path' => $storagePath,
            'file_type' => $fileType,
            'status' => 'pending'
        ]);
    }

    /**
     * Import CSV with progress callback for real-time updates
     * Memory-efficient streaming implementation
     */
    public function importFromCsvWithProgress($filePath, $logId, callable $progressCallback)
    {
        try {
            // Optimize for large files
            set_time_limit(0);
            ini_set('memory_limit', '512M'); // Lower memory limit with streaming
            gc_enable(); // Enable garbage collection

            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("File not found or not readable: {$filePath}");
            }

            $this->log = CmImportLog::find($logId);
            if (!$this->log) {
                throw new Exception("Import log not found");
            }

            $this->log->markAsStarted();

            // Send initial status
            $progressCallback([
                'type' => 'status',
                'message' => 'Opening file...',
                'percentage' => 0
            ]);

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception("Cannot open file: {$filePath}");
            }

            $originalHeaders = fgetcsv($handle);
            if (!$originalHeaders) {
                fclose($handle);
                throw new Exception("Cannot read CSV headers");
            }

            $originalHeaders = array_map('trim', $originalHeaders);
            $headers = $this->normalizeHeaders($originalHeaders);

            // Quick row count
            $progressCallback([
                'type' => 'status',
                'message' => 'Counting rows...',
                'percentage' => 0
            ]);

            $totalRows = $this->countCsvRows($filePath) - 1;
            $this->log->update(['total_rows' => $totalRows]);

            $customFields = $this->detectCustomFields($headers);
            $this->log->update(['custom_fields_detected' => $customFields]);

            // Immediately create custom field definitions for all detected fields
            // This ensures fields exist even if all rows have empty values
            $this->ensureCustomFieldsExist($customFields);

            $progressCallback([
                'type' => 'status',
                'message' => "Processing {$totalRows} rows...",
                'percentage' => 0,
                'total_rows' => $totalRows
            ]);

            // Process in small batches to reduce memory
            $batchSize = 100; // Smaller batches for better progress updates
            $batch = [];
            $rowNumber = 1;
            $lastProgressUpdate = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    $this->log->incrementFailed();
                    continue;
                }

                $rowData = array_combine($headers, array_map('trim', $row));

                if ($this->isEmptyRow($rowData) || empty($rowData['email'])) {
                    $this->log->incrementFailed();
                    continue;
                }

                $batch[] = $rowData;

                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $batch = [];

                    // Force garbage collection every few batches
                    if ($rowNumber % 500 === 0) {
                        gc_collect_cycles();
                    }

                    // Send progress update (every 5% or every 100 rows)
                    $percentage = ($this->log->processed_rows / $totalRows) * 100;
                    if ($percentage - $lastProgressUpdate >= 5 || $this->log->processed_rows % 100 === 0) {
                        $progressCallback([
                            'type' => 'progress',
                            'percentage' => round($percentage, 1),
                            'processed_rows' => $this->log->processed_rows,
                            'total_rows' => $totalRows,
                            'created_count' => $this->log->created_count,
                            'updated_count' => $this->log->updated_count,
                            'failed_count' => $this->log->failed_count,
                            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
                        ]);
                        $lastProgressUpdate = $percentage;
                    }
                }
            }

            // Process remaining batch
            if (!empty($batch)) {
                $this->processBatch($batch);
            }

            // Final counter save to ensure all data is persisted
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

            DB::table('cm_import_logs')
                ->where('id', $this->log->id)
                ->update([
                    'processed_rows' => $this->log->processed_rows,
                    'created_count' => $this->log->created_count,
                    'updated_count' => $this->log->updated_count,
                    'failed_count' => $this->log->failed_count,
                    'memory_peak' => $memoryPeak,
                    'updated_at' => now()
                ]);

            fclose($handle);
            $this->log->markAsCompleted();

            // Final progress update
            $progressCallback([
                'type' => 'progress',
                'percentage' => 100,
                'processed_rows' => $this->log->processed_rows,
                'total_rows' => $totalRows,
                'created_count' => $this->log->created_count,
                'updated_count' => $this->log->updated_count,
                'failed_count' => $this->log->failed_count,
                'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
            ]);

            return [
                'success' => true,
                'log' => $this->log,
                'message' => "Import completed successfully. Processed {$this->log->processed_rows} rows."
            ];

        } catch (Exception $e) {
            if ($this->log) {
                $this->log->markAsFailed(['error' => $e->getMessage()]);
            }

            Log::error('Campaign Monitor import failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    protected function logError($message)
    {
        if ($this->config['log_failed_rows'] ?? true) {
            Log::warning('Campaign Monitor import row error', ['message' => $message]);
        }
    }

    protected function buildFullName($rowData)
    {
        // If we have a 'fullname' field, use it (but only if not empty after trimming)
        if (isset($rowData['fullname']) && !empty(trim($rowData['fullname']))) {
            return trim($rowData['fullname']);
        }

        // Try to build from firstname/lastname
        $firstName = trim($rowData['firstname'] ?? $rowData['First Name'] ?? '');
        $lastName = trim($rowData['lastname'] ?? $rowData['Last Name'] ?? '');

        if (!empty($firstName) || !empty($lastName)) {
            return trim($firstName . ' ' . $lastName);
        }

        // Return null if no name data is available - let the validation handle this
        return null;
    }

    protected function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try various date formats that Campaign Monitor might use
            $formats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'n/j/Y',
                'm/d/Y',
                'd/m/Y',
                'Y/m/d',
                'M j, Y',
                'j M Y',
                'F j, Y',
                'j F Y',
                'Y-m-d\TH:i:s\Z',
                'Y-m-d\TH:i:s.u\Z'
            ];

            foreach ($formats as $format) {
                $date = \DateTime::createFromFormat($format, $dateString);
                if ($date !== false) {
                    return $date->format('Y-m-d H:i:s');
                }
            }

            // Try strtotime as fallback
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to parse date', ['date' => $dateString, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim($value));

        // Campaign Monitor often uses these values
        $trueValues = ['yes', 'true', '1', 'on', 'enabled', 'allow', 'permitted'];
        $falseValues = ['no', 'false', '0', 'off', 'disabled', 'deny', 'denied'];

        if (in_array($value, $trueValues)) {
            return true;
        }

        if (in_array($value, $falseValues)) {
            return false;
        }

        // Default to true if uncertain (for permission to track)
        return true;
    }

    protected function isEmptyRow($rowData)
    {
        // Check if all values in the row are empty or whitespace
        foreach ($rowData as $value) {
            if (!empty(trim($value))) {
                return false;
            }
        }
        return true;
    }

    public function getImportHistory($limit = 10)
    {
        return CmImportLog::recent()->limit($limit)->get();
    }

    public function getImportById($id)
    {
        return CmImportLog::find($id);
    }

    public function validateCsvFile($filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['valid' => false, 'error' => 'File not found or not readable'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['valid' => false, 'error' => 'Cannot open file'];
        }

        $headers = fgetcsv($handle);
        fclose($handle);

        if (!$headers || !$this->findEmailColumn($headers)) {
            return ['valid' => false, 'error' => 'CSV must contain an email column (email, Email Address, etc.)'];
        }

        return ['valid' => true, 'headers' => $headers];
    }

    public function importFromCsvChunked($filePath, $logId = null)
    {
        try {
            // Memory optimizations for large files
            set_time_limit(0);
            ini_set('memory_limit', '512M');
            gc_enable();

            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("File not found or not readable: {$filePath}");
            }

            // Generate file hash for tracking
            $fileHash = hash_file('sha256', $filePath);

            $this->log = $logId ? CmImportLog::find($logId) : $this->createImportLog(basename($filePath), $fileHash);
            $this->log->markAsStarted();

            $handle = fopen($filePath, 'r');
            if (!$handle) {
                throw new Exception("Cannot open file: {$filePath}");
            }

            $originalHeaders = fgetcsv($handle);
            if (!$originalHeaders) {
                throw new Exception("Cannot read CSV headers");
            }

            $originalHeaders = array_map('trim', $originalHeaders);
            $headers = $this->normalizeHeaders($originalHeaders);
            $totalRows = $this->countCsvRows($filePath) - 1;

            $customFields = $this->detectCustomFields($headers);
            $this->log->update([
                'total_rows' => $totalRows,
                'custom_fields_detected' => $customFields
            ]);

            // Immediately create custom field definitions for all detected fields
            // This ensures fields exist even if all rows have empty values
            $this->ensureCustomFieldsExist($customFields);

            // Process in small batches for memory efficiency
            $batchSize = 100; // Smaller batches for better progress updates
            $batch = [];
            $rowNumber = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count($row) !== count($headers)) {
                    $this->log->incrementFailed();
                    continue;
                }

                $rowData = array_combine($headers, array_map('trim', $row));

                if ($this->isEmptyRow($rowData) || empty($rowData['email'])) {
                    $this->log->incrementFailed();
                    continue;
                }

                $batch[] = $rowData;

                if (count($batch) >= $batchSize) {
                    $this->processBatch($batch);
                    $batch = [];

                    // Garbage collect every 500 rows
                    if ($rowNumber % 500 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            // Process remaining batch
            if (!empty($batch)) {
                $this->processBatch($batch);
            }

            // Final counter save to ensure all data is persisted
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

            DB::table('cm_import_logs')
                ->where('id', $this->log->id)
                ->update([
                    'processed_rows' => $this->log->processed_rows,
                    'created_count' => $this->log->created_count,
                    'updated_count' => $this->log->updated_count,
                    'failed_count' => $this->log->failed_count,
                    'memory_peak' => $memoryPeak,
                    'updated_at' => now()
                ]);

            fclose($handle);
            $this->log->markAsCompleted();

            return [
                'success' => true,
                'log' => $this->log,
                'message' => "Import completed successfully. Processed {$this->log->processed_rows} rows."
            ];

        } catch (Exception $e) {
            if ($this->log) {
                $this->log->markAsFailed(['error' => $e->getMessage()]);
            }

            Log::error('Campaign Monitor import failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }

    public function processChunkData($chunkData, $headers, $log)
    {
        try {
            $processed = 0;
            $created = 0;
            $updated = 0;
            $failed = 0;

            // Use PDO for chunk processing
            $this->log = $log;

            // Process the chunk using existing PDO batch processing
            $this->processBatchWithPDO($chunkData);

            // Update counters based on batch results
            $processed = count($chunkData);
            $created = $this->log->created_count ?? 0;
            $updated = $this->log->updated_count ?? 0;

            // Update log totals using direct DB query to avoid transaction conflicts
            DB::table('cm_import_logs')
                ->where('id', $this->log->id)
                ->increment('processed_rows', $processed);

            return [
                'success' => true,
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed
            ];

        } catch (Exception $e) {
            Log::error('Chunk processing failed', [
                'error' => $e->getMessage(),
                'chunk_size' => count($chunkData)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function determineStatus()
    {
        // If file_type is set and not 'all', use it as the status
        $fileType = $this->log->file_type ?? 'all';

        if ($fileType !== 'all') {
            return $fileType;
        }

        // Otherwise use the default status from config
        return $this->config['default_status'] ?? 'active';
    }

    protected function getExistingUsersByEmail($emails)
    {
        return User::whereIn('email', $emails)
            ->get()
            ->keyBy('email')
            ->toArray();
    }

    protected function prepareUserInsertData($rowData)
    {
        $fullname = $this->buildFullName($rowData);

        // Determine status based on file_type from the import log
        $status = $this->determineStatus();

        return [
            'email' => $rowData['email'],
            'fullname' => $fullname ?: '',
            'cm_status' => $status,
            'cm_subscribed_at' => isset($rowData['cm_subscribed_at']) && !empty($rowData['cm_subscribed_at'])
                ? $this->parseDate($rowData['cm_subscribed_at'])
                : now()->format('Y-m-d H:i:s'),
            'cm_unsubscribed_at' => isset($rowData['cm_unsubscribed_at']) && !empty($rowData['cm_unsubscribed_at'])
                ? $this->parseDate($rowData['cm_unsubscribed_at'])
                : null,
            'permission_to_track' => isset($rowData['permission_to_track'])
                ? $this->parseBoolean($rowData['permission_to_track'])
                : true,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s')
        ];
    }

    protected function prepareUserUpdateData($rowData, $existingUser)
    {
        $updates = [];
        $newName = $this->buildFullName($rowData);

        if ($newName && $existingUser['fullname'] !== $newName) {
            $updates['fullname'] = $newName;
        }

        if (isset($rowData['cm_subscriber_id']) && $existingUser['cm_subscriber_id'] !== $rowData['cm_subscriber_id']) {
            $updates['cm_subscriber_id'] = $rowData['cm_subscriber_id'];
        }

        // Update status based on file_type (unless file_type is 'all')
        $fileType = $this->log->file_type ?? 'all';
        if ($fileType !== 'all') {
            if ($existingUser['cm_status'] !== $fileType) {
                $updates['cm_status'] = $fileType;
            }
        } elseif (isset($rowData['cm_status']) && $existingUser['cm_status'] !== $rowData['cm_status']) {
            $updates['cm_status'] = $rowData['cm_status'];
        }

        if (!empty($updates)) {
            $updates['email'] = $rowData['email'];
            $updates['updated_at'] = now()->format('Y-m-d H:i:s');
        }

        return $updates;
    }

    protected function prepareCustomFieldData($rowData, $email)
    {
        $standardFields = [
            'email', 'fullname', 'cm_subscriber_id', 'cm_status',
            'cm_subscribed_at', 'cm_unsubscribed_at', 'permission_to_track',
            'date_active', 'date_joined'
        ];

        $customData = [];
        foreach ($rowData as $fieldKey => $value) {
            // Skip standard fields, empty values, or empty field keys
            if (in_array($fieldKey, $standardFields) || empty($value) || empty(trim($fieldKey))) {
                continue;
            }

            $customData[] = [
                'email' => $email,
                'field_key' => $fieldKey,
                'value' => $value,
                'data_type' => $this->detectDataType($value)
            ];
        }

        return $customData;
    }

    protected function bulkInsertUsers($pdo, $users)
    {
        if (empty($users)) return;

        // MySQL has a limit on placeholders (~65535).
        // With 8 columns per row, we can safely insert ~8000 rows at once
        // Use configurable chunk size to be safe
        $chunkSize = $this->config['pdo_chunk_size'] ?? 500;
        $chunks = array_chunk($users, $chunkSize);

        foreach ($chunks as $chunk) {
            $sql = "INSERT INTO users (email, fullname, cm_status, cm_subscribed_at, cm_unsubscribed_at, permission_to_track, created_at, updated_at) VALUES ";
            $values = [];
            $params = [];

            foreach ($chunk as $user) {
                $values[] = "(?, ?, ?, ?, ?, ?, ?, ?)";
                $params = array_merge($params, [
                    $user['email'],
                    $user['fullname'],
                    $user['cm_status'],
                    $user['cm_subscribed_at'],
                    $user['cm_unsubscribed_at'],
                    $user['permission_to_track'] ? 1 : 0,
                    $user['created_at'],
                    $user['updated_at']
                ]);
            }

            $sql .= implode(', ', $values);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    protected function bulkUpdateUsers($pdo, $updates)
    {
        $actuallyUpdated = 0;

        foreach ($updates as $update) {
            if (count($update) <= 2) continue; // Only email and updated_at

            $setParts = [];
            $params = [];

            foreach ($update as $field => $value) {
                if ($field === 'email') continue;
                $setParts[] = "{$field} = ?";
                $params[] = $value;
            }

            if (!empty($setParts)) {
                $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE email = ?";
                $params[] = $update['email'];

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $actuallyUpdated++;
            }
        }

        return $actuallyUpdated;
    }

    protected function bulkInsertCustomFields($pdo, $customFieldData)
    {
        if (empty($customFieldData)) return;

        // First, ensure custom field definitions exist
        $fieldKeys = array_unique(array_column($customFieldData, 'field_key'));
        $this->ensureCustomFieldsExist($fieldKeys);

        // Get user IDs and field IDs for the values
        $emails = array_unique(array_column($customFieldData, 'email'));
        $userMap = $this->getUserIdsByEmail($emails);
        $fieldMap = $this->getCustomFieldIdsByKey($fieldKeys);

        // Prepare data for bulk insert
        $insertData = [];
        $now = now()->format('Y-m-d H:i:s');

        foreach ($customFieldData as $data) {
            if (!isset($userMap[$data['email']]) || !isset($fieldMap[$data['field_key']])) {
                continue;
            }

            $insertData[] = [
                $userMap[$data['email']],
                $fieldMap[$data['field_key']],
                $data['value'],
                $now,
                $now
            ];
        }

        if (empty($insertData)) return;

        // Chunk the data to avoid placeholder limit
        // With 5 columns per row, we can use larger chunks than users table
        $chunkSize = ($this->config['pdo_chunk_size'] ?? 500) * 2;
        $chunks = array_chunk($insertData, $chunkSize);

        foreach ($chunks as $chunk) {
            $sql = "INSERT INTO cm_custom_field_values (user_id, cm_custom_field_id, value, created_at, updated_at) VALUES ";
            $values = [];
            $params = [];

            foreach ($chunk as $row) {
                $values[] = "(?, ?, ?, ?, ?)";
                $params = array_merge($params, $row);
            }

            $sql .= implode(', ', $values);
            $sql .= " ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    protected function ensureCustomFieldsExist($fieldKeys)
    {
        foreach ($fieldKeys as $fieldKey) {
            // Skip empty or whitespace-only field keys
            if (empty(trim($fieldKey))) {
                continue;
            }

            // Generate a readable field_name by adding spaces before capital letters
            // Example: "EllucianFringe" becomes "Ellucian Fringe"
            $fieldName = preg_replace('/([a-z])([A-Z])/', '$1 $2', $fieldKey);

            CmCustomField::firstOrCreate(
                ['field_key' => $fieldKey],
                [
                    'field_name' => $fieldName,
                    'data_type' => 'text',
                    'is_active' => true
                ]
            );
        }
    }

    protected function getUserIdsByEmail($emails)
    {
        return User::whereIn('email', $emails)
            ->pluck('id', 'email')
            ->toArray();
    }

    protected function getCustomFieldIdsByKey($fieldKeys)
    {
        return CmCustomField::whereIn('field_key', $fieldKeys)
            ->pluck('id', 'field_key')
            ->toArray();
    }

    public function previewCsv($filePath, $rows = 5)
    {
        $validation = $this->validateCsvFile($filePath);

        if (!$validation['valid']) {
            return $validation;
        }

        $handle = fopen($filePath, 'r');
        $originalHeaders = fgetcsv($handle);
        $originalHeaders = array_map('trim', $originalHeaders);
        $normalizedHeaders = $this->normalizeHeaders($originalHeaders);
        $preview = [];

        $count = 0;
        while (($row = fgetcsv($handle)) !== false && $count < $rows) {
            // Skip rows with column count mismatch
            if (count($row) !== count($normalizedHeaders)) {
                continue;
            }

            $rowData = array_combine($normalizedHeaders, array_map('trim', $row));

            // Skip empty rows
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $preview[] = $rowData;
            $count++;
        }

        fclose($handle);

        return [
            'valid' => true,
            'headers' => $normalizedHeaders,
            'original_headers' => $originalHeaders,
            'preview' => $preview,
            'custom_fields' => $this->detectCustomFields($normalizedHeaders)
        ];
    }
}