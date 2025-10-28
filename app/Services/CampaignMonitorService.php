<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

// Include Campaign Monitor SDK files
if (file_exists(base_path('vendor/campaignmonitor/createsend-php/csrest_clients.php'))) {
    require_once base_path('vendor/campaignmonitor/createsend-php/csrest_clients.php');
}
if (file_exists(base_path('vendor/campaignmonitor/createsend-php/csrest_lists.php'))) {
    require_once base_path('vendor/campaignmonitor/createsend-php/csrest_lists.php');
}

class CampaignMonitorService
{
    private $client;
    private ?string $apiKey;
    private ?string $clientId;
    private bool $isAvailable;

    public function __construct()
    {
        $this->apiKey = config('app.campign_monitor.cm_api_key');
        $this->clientId = config('app.campign_monitor.client_id');

        // Check if Campaign Monitor SDK is available
        $this->isAvailable = class_exists('CS_REST_Clients') && class_exists('CS_REST_Lists');

        if (!$this->isAvailable) {
            Log::warning('Campaign Monitor SDK not found - functionality disabled');
            return;
        }

        // Only create client if API key is available
        if ($this->apiKey) {
            $this->client = new \CS_REST_Clients($this->clientId, ['api_key' => $this->apiKey]);
        }
    }

    /**
     * Fetch custom fields from Campaign Monitor
     */
    public function fetchCustomFields(): array
    {
        try {
            if (!$this->isAvailable) {
                throw new Exception('Campaign Monitor SDK is not available');
            }

            if (!$this->apiKey) {
                throw new Exception('Campaign Monitor API key is not configured');
            }

            if (!$this->clientId) {
                throw new Exception('Campaign Monitor Client ID is not configured');
            }

            // Get all lists for the client first
            $listsResult = $this->client->get_lists();

            if (!$listsResult->was_successful()) {
                $error = is_string($listsResult->response) ? $listsResult->response : json_encode($listsResult->response);
                throw new Exception('Unable to retrieve lists from Campaign Monitor API: ' . ($error ?? 'Unknown error'));
            }

            if (!isset($listsResult->response) || !is_array($listsResult->response)) {
                throw new Exception('Invalid lists response format from Campaign Monitor API');
            }

            $allCustomFields = [];
            $processedKeys = []; // Track to avoid duplicates across lists

            // Get custom fields from each list
            foreach ($listsResult->response as $list) {
                if (!isset($list->ListID)) {
                    continue;
                }

                $listClient = new \CS_REST_Lists($list->ListID, ['api_key' => $this->apiKey]);
                $customFieldsResult = $listClient->get_custom_fields();

                if ($customFieldsResult->was_successful() && isset($customFieldsResult->response) && is_array($customFieldsResult->response)) {
                    foreach ($customFieldsResult->response as $field) {
                        // Skip if we've already processed this field key
                        if (isset($field->Key) && in_array($field->Key, $processedKeys)) {
                            continue;
                        }

                        if (isset($field->Key)) {
                            $processedKeys[] = $field->Key;
                            $allCustomFields[] = $field;
                        }
                    }
                } else {
                    Log::warning('Failed to get custom fields from list', [
                        'list_id' => $list->ListID,
                        'error' => $customFieldsResult->response ?? 'Unknown error'
                    ]);
                }
            }

            if (empty($allCustomFields)) {
                Log::info('No custom fields found in any Campaign Monitor lists', [
                    'client_id' => $this->clientId,
                    'lists_count' => count($listsResult->response)
                ]);
            }

            return $this->formatCustomFields($allCustomFields);

        } catch (Exception $e) {
            Log::error('Failed to fetch custom fields from Campaign Monitor', [
                'error' => $e->getMessage(),
                'client_id' => $this->clientId
            ]);
            throw new Exception('Failed to fetch custom fields from Campaign Monitor: ' . $e->getMessage());
        }
    }

    /**
     * Format Campaign Monitor custom fields to our standard format
     */
    private function formatCustomFields(array $fields): array
    {
        return array_map(function ($field) {
            $dataType = $this->mapDataType($field->DataType);
            $options = $this->parseFieldOptions($field->FieldOptions, $dataType);

            return [
                'FieldName' => $field->FieldName,
                'Key' => $field->Key,
                'DataType' => $dataType,
                'FieldOptions' => $options,
                'VisibleInPreferenceCenter' => $field->VisibleInPreferenceCenter ?? false,
            ];
        }, $fields);
    }

    /**
     * Map Campaign Monitor data types to our data types
     */
    private function mapDataType(string $cmDataType): string
    {
        $mapping = [
            'Text' => 'Text',
            'Number' => 'Number',
            'MultiSelectOne' => 'MultiSelectOne',
            'MultiSelectMany' => 'MultiSelectMany',
            'Date' => 'Date',
            'Country' => 'Text', // Map Country to Text as we might not have Country enum
            'US State' => 'Text', // Map US State to Text
            'Canadian Province' => 'Text', // Map Canadian Province to Text
        ];

        return $mapping[$cmDataType] ?? 'Text';
    }

    /**
     * Parse field options based on data type
     */
    private function parseFieldOptions($fieldOptions, string $dataType): array
    {
        // For non-select types, return empty array
        if (!in_array($dataType, ['MultiSelectOne', 'MultiSelectMany'])) {
            return [];
        }

        // Parse the field options (could be string or array)
        if (is_string($fieldOptions)) {
            // Split by common delimiters
            $options = preg_split('/[,;]\s*/', $fieldOptions);
            return array_filter(array_map('trim', $options));
        }

        if (is_array($fieldOptions)) {
            return array_filter($fieldOptions);
        }

        return [];
    }

    /**
     * Test the API connection
     */
    public function testConnection(): bool
    {
        try {
            if (!$this->isAvailable) {
                return false;
            }

            if (!$this->clientId) {
                return false;
            }

            // Test connection by trying to get lists (should work with valid credentials)
            $result = $this->client->get_lists();
            return isset($result->response);
        } catch (Exception $e) {
            Log::error('Campaign Monitor connection test failed', [
                'error' => $e->getMessage(),
                'client_id' => $this->clientId
            ]);
            return false;
        }
    }

    /**
     * Get connection status with details
     */
    public function getConnectionStatus(): array
    {
        $status = [
            'configured' => false,
            'connected' => false,
            'error' => null,
            'client_name' => null,
        ];

        try {
            if (!$this->isAvailable) {
                $status['error'] = 'Campaign Monitor SDK is not available';
                return $status;
            }

            $status['configured'] = !empty($this->apiKey) && !empty($this->clientId);

            if (!$status['configured']) {
                $missing = [];
                if (empty($this->apiKey)) $missing[] = 'API Key';
                if (empty($this->clientId)) $missing[] = 'Client ID';
                $status['error'] = 'Missing configuration: ' . implode(', ', $missing);
                return $status;
            }

            // Test connection by trying to get lists
            $client = new \CS_REST_Clients($this->clientId, ['api_key' => $this->apiKey]);
            $result = $client->get_lists();

            if (isset($result->response)) {
                $status['connected'] = true;
                $status['client_name'] = "Client {$this->clientId}";
            }

        } catch (Exception $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * Get formatted JSON response that matches our import format
     */
    public function getCustomFieldsForImport(): string
    {
        $fields = $this->fetchCustomFields();

        return json_encode([
            'response' => $fields,
            'http_status_code' => 200
        ], JSON_PRETTY_PRINT);
    }
}