<?php

namespace App\Services;

use App\Models\User;
use App\Models\CmCustomField;
use App\Models\CmImportLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CampaignMonitorImportService
{
    protected $config;
    protected $log;

    public function __construct()
    {
        $this->config = config('campaign-monitor');
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
                $normalizedHeaders[] = $trimmedHeader;
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

            $this->log = $logId ? CmImportLog::find($logId) : $this->createImportLog(basename($filePath));
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
        DB::transaction(function () use ($batch) {
            foreach ($batch as $rowData) {
                try {
                    $this->processRow($rowData);
                    $this->log->incrementProcessed();
                } catch (Exception $e) {
                    $this->log->incrementFailed();
                    $this->logError("Failed to process row: " . $e->getMessage());
                }
            }
        });
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
            $user->cm_status = $this->config['default_status'] ?? 'active';

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
            $this->log->incrementCreated();
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

            if (isset($rowData['cm_status']) && $user->cm_status !== $rowData['cm_status']) {
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
                $this->log->incrementUpdated();
            }
        }

        $this->processCustomFields($user, $rowData);
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
            if (in_array($fieldKey, $standardFields) || empty($value)) {
                continue;
            }

            $field = CmCustomField::firstOrCreate(
                ['field_key' => $fieldKey],
                [
                    'field_name' => ucwords(str_replace('_', ' ', $fieldKey)),
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
        return array_diff($headers, $standardFields);
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

    protected function createImportLog($filename)
    {
        return CmImportLog::create([
            'filename' => $filename,
            'status' => 'pending'
        ]);
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