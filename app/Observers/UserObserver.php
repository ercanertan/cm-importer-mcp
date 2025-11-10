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
        // Only sync if not in bulk import mode
        if ($this->isBulkOperation()) {
            return;
        }

        // Only sync if CM-relevant fields changed
        if ($this->hasCmRelevantChanges($user)) {
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
}
