<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

// Include Campaign Monitor SDK files
if (file_exists(base_path('vendor/campaignmonitor/createsend-php/csrest_lists.php'))) {
    require_once base_path('vendor/campaignmonitor/createsend-php/csrest_lists.php');
}

class CampaignMonitorService
{
    private $listClient;
    private ?string $apiKey;
    private ?string $listId;
    private bool $isAvailable;

    public function __construct()
    {
        $this->apiKey = config('app.campign_monitor.cm_api_key');
        $this->listId = config('app.campign_monitor.list_id');

        // Check if Campaign Monitor SDK is available
        $this->isAvailable = class_exists('CS_REST_Lists');

        if (!$this->isAvailable) {
            Log::warning('Campaign Monitor SDK not found - functionality disabled');
            return;
        }

        // Only create list client if API key and list ID are available
        if ($this->apiKey && $this->listId) {
            $this->listClient = new \CS_REST_Lists($this->listId, ['api_key' => $this->apiKey]);
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

            if (!$this->listId) {
                throw new Exception('Campaign Monitor List ID is not configured');
            }

            // Get custom fields directly from the configured list
            $customFieldsResult = $this->listClient->get_custom_fields();

            if (!$customFieldsResult->was_successful()) {
                $error = $this->formatApiError($customFieldsResult);
                throw new Exception('Unable to retrieve custom fields from Campaign Monitor list: ' . $error);
            }

            if (!isset($customFieldsResult->response) || !is_array($customFieldsResult->response)) {
                throw new Exception('Invalid custom fields response format from Campaign Monitor API');
            }

            if (empty($customFieldsResult->response)) {
                Log::info('No custom fields found in Campaign Monitor list', [
                    'list_id' => $this->listId
                ]);
            }

            return $this->formatCustomFields($customFieldsResult->response);

        } catch (Exception $e) {
            Log::error('Failed to fetch custom fields from Campaign Monitor', [
                'error' => $e->getMessage(),
                'list_id' => $this->listId
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

            if (!$this->listId) {
                return false;
            }

            // Test connection by trying to get custom fields from the list
            $result = $this->listClient->get_custom_fields();
            return $result->was_successful();
        } catch (Exception $e) {
            Log::error('Campaign Monitor connection test failed', [
                'error' => $e->getMessage(),
                'list_id' => $this->listId
            ]);
            return false;
        }
    }

    /**
     * Format API error messages for better user understanding
     */
    private function formatApiError($result): string
    {
        if (isset($result->response->Code) && isset($result->response->Message)) {
            $code = $result->response->Code;
            $message = $result->response->Message;

            // Common error codes with user-friendly messages
            switch ($code) {
                case 100:
                    return "Invalid API Key - The API key provided is not valid. Please check your CM_API_KEY in the .env file.";
                case 101:
                    return "Invalid Client ID - The client ID is not valid. Please check your configuration.";
                case 102:
                    return "Invalid List ID - The list ID '{$this->listId}' is not valid. Please check your CREATESEND_LIST_ID.";
                case 103:
                    return "Permission Denied - The API key doesn't have permission to access this list.";
                case 104:
                    return "List Not Found - No list found with ID '{$this->listId}'. Please verify the list ID.";
                case 105:
                    return "Account Inactive - Your Campaign Monitor account is inactive.";
                case 106:
                    return "API Limit Exceeded - You've exceeded the API rate limit. Please try again later.";
                case 107:
                    return "List Inactive - The list '{$this->listId}' is inactive.";
                case 120:
                    return "Invalid OAuth Token - The OAuth token is invalid or expired.";
                case 121:
                    return "OAuth Token Expired - The OAuth token has expired.";
                case 122:
                    return "OAuth Token Revoked - The OAuth token has been revoked.";
                case 123:
                    return "Invalid OAuth Scope - The OAuth token doesn't have the required scope.";
                case 124:
                    return "OAuth Grant Expired - The OAuth grant has expired.";
                case 125:
                    return "OAuth Invalid Request - The OAuth request is invalid.";
                case 126:
                    return "OAuth Unsupported Grant Type - The OAuth grant type is not supported.";
                case 127:
                    return "OAuth Invalid Client - The OAuth client credentials are invalid.";
                case 200:
                    return "Server Error - Campaign Monitor is experiencing technical difficulties. Please try again later.";
                case 201:
                    return "Maintenance Mode - Campaign Monitor is currently down for maintenance.";
                case 202:
                    return "Service Unavailable - Campaign Monitor service is temporarily unavailable.";
                default:
                    return "Campaign Monitor API Error (Code {$code}): {$message}";
            }
        }

        // Fallback for other error formats
        if (is_string($result->response)) {
            return $result->response;
        }

        if (is_object($result->response)) {
            $errorData = json_decode(json_encode($result->response), true);
            if (isset($errorData['Message'])) {
                return $errorData['Message'];
            }
            if (isset($errorData['error'])) {
                return $errorData['error'];
            }
        }

        return json_encode($result->response);
    }

    /**
     * Get detailed list information
     */
    public function getListDetails(): array
    {
        try {
            if (!$this->isAvailable || !$this->listId) {
                return [];
            }

            // Try to get list details (this might not be available in all SDK versions)
            $result = $this->listClient->get();

            if ($result->was_successful() && isset($result->response)) {
                return [
                    'name' => $result->response->Title ?? 'Unknown List',
                    'id' => $result->response->ListID ?? $this->listId,
                    'created_date' => $result->response->CreatedDate ?? 'Unknown',
                    'member_count' => $result->response->MemberCount ?? 0,
                    'active_member_count' => $result->response->ActiveMemberCount ?? 0,
                    'web_form_url' => $result->response->WebFormURL ?? null,
                    'confirmed_opt_in' => $result->response->ConfirmedOptIn ?? false,
                ];
            }

            // Fallback to basic info
            return [
                'name' => "List {$this->listId}",
                'id' => $this->listId,
                'member_count' => 'Unknown',
                'created_date' => 'Unknown',
            ];

        } catch (Exception $e) {
            Log::warning('Failed to get list details', [
                'error' => $e->getMessage(),
                'list_id' => $this->listId
            ]);

            return [
                'name' => "List {$this->listId}",
                'id' => $this->listId,
                'error' => 'Unable to retrieve list details: ' . $e->getMessage(),
            ];
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
            'list_details' => [],
        ];

        try {
            if (!$this->isAvailable) {
                $status['error'] = 'Campaign Monitor SDK is not available';
                return $status;
            }

            $status['configured'] = !empty($this->apiKey) && !empty($this->listId);

            if (!$status['configured']) {
                $missing = [];
                if (empty($this->apiKey)) $missing[] = 'API Key';
                if (empty($this->listId)) $missing[] = 'List ID';
                $status['error'] = 'Missing configuration: ' . implode(', ', $missing);
                return $status;
            }

            // Test connection by trying to get custom fields from the list
            $result = $this->listClient->get_custom_fields();

            if ($result->was_successful()) {
                $status['connected'] = true;

                // Get detailed list information
                $listDetails = $this->getListDetails();
                $status['list_details'] = $listDetails;
                $status['client_name'] = $listDetails['name'] ?? "List {$this->listId}";
            } else {
                // Use the improved error formatting
                $status['error'] = $this->formatApiError($result);
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