<?php

namespace App\Observers;

use App\Models\Organization;
use App\Jobs\BulkSyncOrganizationUsersJob;
use Illuminate\Support\Facades\Log;

class OrganizationObserver
{
    /**
     * Handle the Organization "updated" event.
     */
    public function updated(Organization $organization): void
    {
        // Check if tier changed
        if (!$organization->wasChanged('tier')) {
            return;
        }

        // Get affected users count
        $affectedUsersCount = $organization->users()->count();

        if ($affectedUsersCount === 0) {
            return;
        }

        Log::info('Organization tier changed', [
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'old_tier' => $organization->getOriginal('tier'),
            'new_tier' => $organization->tier,
            'affected_users' => $affectedUsersCount,
        ]);

        // If small number of users, could sync immediately
        // But to be safe, always treat as bulk operation
        if ($affectedUsersCount <= config('campaign-monitor.sync_threshold', 10)) {
            // Small organization - sync immediately in background
            $this->syncUsersInBackground($organization);
        } else {
            // Large organization - queue bulk sync job
            $this->queueBulkSync($organization);
        }
    }

    /**
     * Sync users in background (for small organizations)
     */
    protected function syncUsersInBackground(Organization $organization): void
    {
        // Set bulk flag to prevent individual UserObserver syncs
        app()->instance('cm.bulk_import_active', true);

        try {
            // Update all users' tier field
            $organization->users()->update([
                'tier' => $organization->tier
            ]);

            // Queue a job to sync these users to CM
            BulkSyncOrganizationUsersJob::dispatch($organization->id, 'tier_change');

        } finally {
            app()->forgetInstance('cm.bulk_import_active');
        }
    }

    /**
     * Queue bulk sync job (for large organizations)
     */
    protected function queueBulkSync(Organization $organization): void
    {
        // Set bulk flag to prevent individual UserObserver syncs
        app()->instance('cm.bulk_import_active', true);

        try {
            // Update all users' tier field
            $organization->users()->update([
                'tier' => $organization->tier
            ]);

            // Queue bulk sync job with delay to allow DB updates to complete
            BulkSyncOrganizationUsersJob::dispatch($organization->id, 'tier_change')
                ->delay(now()->addMinutes(1));

            Log::info('Queued bulk sync for organization tier change', [
                'organization_id' => $organization->id,
                'affected_users' => $organization->users()->count(),
            ]);

        } finally {
            app()->forgetInstance('cm.bulk_import_active');
        }
    }

    /**
     * Handle the Organization "created" event.
     */
    public function created(Organization $organization): void
    {
        // Nothing to sync - no users yet
    }
}
