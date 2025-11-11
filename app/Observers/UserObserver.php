<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CmSyncService;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    protected CmSyncService $cmSyncService;

    public function __construct(CmSyncService $cmSyncService)
    {
        $this->cmSyncService = $cmSyncService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Only sync if not in bulk import mode
        if (!$this->isBulkOperation()) {
            $this->syncUserToCm($user);
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Skip all sync if in bulk import mode
        if ($this->isBulkOperation()) {
            return;
        }

        // Check if tag-related fields changed (tier, engagement, status)
        // Mark for bulk tag sync instead of syncing immediately
        if ($this->hasTagRelevantChanges($user)) {
            $this->markForTagSync($user);
        }

        // Sync custom fields immediately if they changed
        // These are fast operations (not tags)
        if ($this->hasCustomFieldChanges($user)) {
            $this->syncUserToCm($user, $this->getChangedCmFields($user));
        }
    }

    /**
     * Sync user to Campaign Monitor
     *
     * @param User $user
     * @param array $fieldsToSync Optional specific fields to sync
     */
    protected function syncUserToCm(User $user, array $fieldsToSync = []): void
    {
        // Skip if sync is disabled
        if ($this->isSyncDisabled()) {
            return;
        }

        try {
            $this->cmSyncService->syncUser($user, $fieldsToSync);
        } catch (\Exception $e) {
            Log::error('UserObserver: Failed to sync user to CM', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
            // Don't throw - we don't want to block user operations if CM sync fails
        }
    }

    /**
     * Check if we're in a bulk operation (CSV import, etc.)
     *
     * @return bool
     */
    protected function isBulkOperation(): bool
    {
        // Check if bulk import flag is set
        if (app()->has('cm.bulk_import_active')) {
            return app('cm.bulk_import_active') === true;
        }

        // Check if we're in a console command (artisan imports)
        if (app()->runningInConsole()) {
            // Allow sync for specific commands that should sync
            $allowedCommands = [
                'tinker', // Allow manual tinker operations
            ];

            foreach ($allowedCommands as $cmd) {
                if (str_contains($_SERVER['argv'][1] ?? '', $cmd)) {
                    return false;
                }
            }

            // Skip sync for import commands
            $importCommands = [
                'cm:import',
                'import:csv',
                'import:users',
                'cm:backfill',
            ];

            foreach ($importCommands as $cmd) {
                if (str_contains($_SERVER['argv'][1] ?? '', $cmd)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if CM sync is globally disabled
     *
     * @return bool
     */
    protected function isSyncDisabled(): bool
    {
        return config('campaign-monitor.disable_auto_sync', false);
    }

    /**
     * Check if any CM-relevant fields have changed
     *
     * @param User $user
     * @return bool
     */
    protected function hasCmRelevantChanges(User $user): bool
    {
        $cmFields = [
            'email',
            'fullname',
            'tier',
            'organization_id',
            'permission_to_track',
        ];

        foreach ($cmFields as $field) {
            if ($user->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of CM fields that changed
     *
     * @param User $user
     * @return array
     */
    protected function getChangedCmFields(User $user): array
    {
        $changedFields = [];

        // Map Laravel fields to CM fields
        $fieldMap = [
            'email' => null, // Email is standard, not a custom field
            'fullname' => null, // Name is standard
            'tier' => 'tier',
            'organization_id' => 'organization_name',
            'permission_to_track' => null, // Standard field
            'engagement_score' => 'engagement_score',
            'total_opens' => 'total_opens',
            'total_clicks' => 'total_clicks',
            'total_bounces' => 'total_bounces',
            'last_email_opened_at' => 'last_email_opened_at',
            'last_email_clicked_at' => 'last_email_clicked_at',
            'last_activity_at' => 'last_activity_at',
        ];

        foreach ($fieldMap as $laravelField => $cmField) {
            if ($user->wasChanged($laravelField) && $cmField !== null) {
                $changedFields[] = $cmField;
            }
        }

        // If no custom fields changed but standard fields did, sync user_id at minimum
        if (empty($changedFields) && $this->hasCmRelevantChanges($user)) {
            $changedFields = ['user_id', 'tier', 'organization_name'];
        }

        return $changedFields;
    }

    /**
     * Check if tag-related fields changed
     * These fields affect permanent tags (tier, engagement, status)
     *
     * @param User $user
     * @return bool
     */
    protected function hasTagRelevantChanges(User $user): bool
    {
        $tagFields = [
            'tier',                  // Affects [Tier] tag
            'engagement_score',      // Affects [Engagement] tag
            'cm_status',            // Affects [Status] tag
            'last_activity_at',     // May affect engagement category
        ];

        foreach ($tagFields as $field) {
            if ($user->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if custom field values changed
     * These are fast to sync individually
     *
     * @param User $user
     * @return bool
     */
    protected function hasCustomFieldChanges(User $user): bool
    {
        $customFields = [
            'organization_name',
            'total_opens',
            'total_clicks',
            'total_bounces',
            'last_email_opened_at',
            'last_email_clicked_at',
            'permission_to_track',
        ];

        foreach ($customFields as $field) {
            if ($user->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark user for tag sync (bulk processed by scheduled command)
     * This avoids API call storms when many users change at once
     *
     * @param User $user
     */
    protected function markForTagSync(User $user): void
    {
        try {
            // Use saveQuietly to avoid triggering this observer again
            $user->updateQuietly([
                'cm_tags_need_sync' => true,
            ]);

            Log::debug('UserObserver: Marked user for tag sync', [
                'user_id' => $user->id,
                'changed_fields' => array_keys($user->getChanges()),
            ]);
        } catch (\Exception $e) {
            Log::error('UserObserver: Failed to mark user for tag sync', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
            // Don't throw - we don't want to block user operations
        }
    }
}
