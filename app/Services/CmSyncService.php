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
}
