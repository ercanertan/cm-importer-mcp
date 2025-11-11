<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class CmSyncService
{
    protected $auth;
    protected $listId;

    public function __construct()
    {
        $this->auth = ['api_key' => config('campaign-monitor.api_key')];
        $this->listId = config('campaign-monitor.list_id');
    }

    /**
     * Sync a user's core fields to Campaign Monitor
     *
     * @param User $user
     * @param array $fieldsToSync Optional array of specific fields to sync
     * @return bool Success status
     */
    public function syncUser(User $user, array $fieldsToSync = []): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Campaign Monitor API not configured. Skipping sync.');
            return false;
        }

        // If no specific fields provided, sync all core fields
        if (empty($fieldsToSync)) {
            $fieldsToSync = ['user_id', 'organization_name', 'tier'];
        }

        try {
            $subscribers = new \CS_REST_Subscribers($this->listId, $this->auth);

            // Build custom fields array
            $customFields = $this->buildCustomFields($user, $fieldsToSync);

            $subscriberData = [
                'EmailAddress' => $user->email,
                'Name' => $user->fullname ?? '',
                'CustomFields' => $customFields,
                'ConsentToTrack' => $user->permission_to_track ? 'Yes' : 'No',
                'Resubscribe' => true,
            ];

            // Check if user exists in CM
            if ($user->cm_subscriber_id) {
                // Update existing subscriber
                $result = $subscribers->update($user->email, $subscriberData);
            } else {
                // Add new subscriber
                $result = $subscribers->add($subscriberData);

                // Store CM subscriber ID if successful
                if ($result->was_successful()) {
                    $user->update(['cm_subscriber_id' => $result->response]);
                }
            }

            if (!$result->was_successful()) {
                Log::error('Failed to sync user to Campaign Monitor', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $result->response,
                ]);
                return false;
            }

            Log::info('Successfully synced user to Campaign Monitor', [
                'user_id' => $user->id,
                'email' => $user->email,
                'fields_synced' => $fieldsToSync,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception while syncing user to Campaign Monitor', [
                'user_id' => $user->id,
                'email' => $user->email,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build custom fields array for Campaign Monitor
     *
     * @param User $user
     * @param array $fieldsToSync
     * @return array
     */
    protected function buildCustomFields(User $user, array $fieldsToSync): array
    {
        $customFields = [];

        foreach ($fieldsToSync as $fieldKey) {
            $value = $this->getFieldValue($user, $fieldKey);

            if ($value !== null) {
                $customFields[] = [
                    'Key' => $fieldKey,
                    'Value' => $value,
                ];
            }
        }

        return $customFields;
    }

    /**
     * Get the value for a specific field
     *
     * @param User $user
     * @param string $fieldKey
     * @return string|null
     */
    protected function getFieldValue(User $user, string $fieldKey): ?string
    {
        return match ($fieldKey) {
            'user_id' => (string) $user->id,
            'organization_name' => $this->getOrganizationName($user),
            'tier' => $user->tier ?? '',
            'temp_campaign_tag' => '', // Always empty unless specifically set
            default => null,
        };
    }

    /**
     * Get the primary organization name for a user
     *
     * @param User $user
     * @return string
     */
    protected function getOrganizationName(User $user): string
    {
        // Try to get primary organization from pivot
        $primaryOrg = $user->organizations()
            ->wherePivot('is_primary', true)
            ->first();

        if ($primaryOrg) {
            return $primaryOrg->name;
        }

        // Fall back to first organization
        $firstOrg = $user->organizations()->first();
        if ($firstOrg) {
            return $firstOrg->name;
        }

        // Fall back to legacy organization_id relationship
        if ($user->organization) {
            return $user->organization->name;
        }

        return '';
    }

    /**
     * Check if Campaign Monitor is properly configured
     *
     * @return bool
     */
    protected function isConfigured(): bool
    {
        return !empty(config('campaign-monitor.api_key'))
            && !empty(config('campaign-monitor.list_id'));
    }

    /**
     * Sync multiple users in batch
     *
     * @param \Illuminate\Support\Collection $users
     * @param array $fieldsToSync
     * @return array ['success' => int, 'failed' => int]
     */
    public function syncUsers($users, array $fieldsToSync = []): array
    {
        $success = 0;
        $failed = 0;

        foreach ($users as $user) {
            if ($this->syncUser($user, $fieldsToSync)) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Bulk import subscribers to Campaign Monitor
     * Used by TagUsersBulkJob and ClearTagBulkJob
     *
     * @param array $subscribers Array of subscriber data
     * @return array ['success' => int, 'failed' => int, 'duplicates' => int]
     */
    public function bulkImport(array $subscribers): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Campaign Monitor API not configured. Skipping bulk import.');
            return ['success' => 0, 'failed' => count($subscribers), 'duplicates' => 0];
        }

        try {
            $subscribersApi = new \CS_REST_Subscribers($this->listId, $this->auth);
            $result = $subscribersApi->import($subscribers);

            if (!$result->was_successful()) {
                $errorMessage = is_object($result->response)
                    ? json_encode($result->response)
                    : $result->response;

                throw new \Exception("Failed to bulk import subscribers: {$errorMessage}");
            }

            // Parse results
            $totalSuccess = count($result->response->TotalNewSubscribers ?? []);
            $totalSuccess += count($result->response->TotalExistingSubscribers ?? []);
            $totalFailed = count($result->response->FailureDetails ?? []);

            return [
                'success' => $totalSuccess,
                'failed' => $totalFailed,
                'duplicates' => count($result->response->DuplicateEmailsInSubmission ?? []),
            ];
        } catch (\Exception $e) {
            Log::error('Exception during bulk import to Campaign Monitor', [
                'exception' => $e->getMessage(),
                'subscriber_count' => count($subscribers),
            ]);

            return [
                'success' => 0,
                'failed' => count($subscribers),
                'duplicates' => 0,
            ];
        }
    }

    /**
     * Create a segment in Campaign Monitor
     *
     * @param array $segmentData Segment configuration
     * @return string|null Segment ID if successful
     */
    public function createSegment(array $segmentData): ?string
    {
        if (!$this->isConfigured()) {
            Log::warning('Campaign Monitor API not configured. Skipping segment creation.');
            return null;
        }

        try {
            $segments = new \CS_REST_Segments($this->listId, $this->auth);
            $result = $segments->create($segmentData);

            if (!$result->was_successful()) {
                Log::error('Failed to create segment in Campaign Monitor', [
                    'error' => $result->response,
                    'segment_data' => $segmentData,
                ]);
                return null;
            }

            Log::info('Successfully created segment in Campaign Monitor', [
                'segment_id' => $result->response,
                'title' => $segmentData['Title'] ?? 'Unknown',
            ]);

            return $result->response; // Returns segment ID
        } catch (\Exception $e) {
            Log::error('Exception while creating segment in Campaign Monitor', [
                'exception' => $e->getMessage(),
                'segment_data' => $segmentData,
            ]);
            return null;
        }
    }

    /**
     * Create a segment for a campaign tag
     * Convenience method for the Campaign Tag Workflow
     *
     * @param string $campaignTag The campaign tag value
     * @param string $title Human-readable segment title
     * @return string|null Segment ID if successful
     */
    public function createCampaignTagSegment(string $campaignTag, string $title): ?string
    {
        return $this->createSegment([
            'Title' => $title,
            'RuleGroups' => [
                [
                    'Rules' => [
                        [
                            'Subject' => 'temp_campaign_tag',
                            'Clauses' => ['EQUALS', $campaignTag]
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Delete a segment from Campaign Monitor
     *
     * @param string $segmentId The segment ID to delete
     * @return bool Success status
     */
    public function deleteSegment(string $segmentId): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Campaign Monitor API not configured. Skipping segment deletion.');
            return false;
        }

        try {
            $segments = new \CS_REST_Segments($segmentId, $this->auth);
            $result = $segments->delete();

            if (!$result->was_successful()) {
                Log::error('Failed to delete segment from Campaign Monitor', [
                    'segment_id' => $segmentId,
                    'error' => $result->response,
                ]);
                return false;
            }

            Log::info('Successfully deleted segment from Campaign Monitor', [
                'segment_id' => $segmentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception while deleting segment from Campaign Monitor', [
                'segment_id' => $segmentId,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get segment details
     *
     * @param string $segmentId The segment ID
     * @return object|null Segment details if successful
     */
    public function getSegment(string $segmentId): ?object
    {
        if (!$this->isConfigured()) {
            Log::warning('Campaign Monitor API not configured. Skipping segment retrieval.');
            return null;
        }

        try {
            $segments = new \CS_REST_Segments($segmentId, $this->auth);
            $result = $segments->get();

            if (!$result->was_successful()) {
                Log::error('Failed to get segment from Campaign Monitor', [
                    'segment_id' => $segmentId,
                    'error' => $result->response,
                ]);
                return null;
            }

            return $result->response;
        } catch (\Exception $e) {
            Log::error('Exception while getting segment from Campaign Monitor', [
                'segment_id' => $segmentId,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
