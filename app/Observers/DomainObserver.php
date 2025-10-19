<?php

namespace App\Observers;

use App\Jobs\SyncSingleDomainJob;
use App\Models\Domain;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DomainObserver
{
    /**
     * Handle the Domain "created" event.
     * Automatically scan and assign users when a new domain is created
     */
    public function created(Domain $domain): void
    {
        // Dispatch proper background job instead of afterResponse
        dispatch(function () use ($domain) {
            try {
                // Refresh the domain to get latest data including any organizations
                $domain->refresh();

                // If domain has organizations, dispatch background job to sync users
                if ($domain->organizations()->exists()) {
                    // Create sync log entry
                    $syncLog = SyncLog::create([
                        'type' => 'sync_single_domain',
                        'status' => 'pending',
                        'user_id' => null, // System-triggered
                        'total_items' => 0,
                        'processed_items' => 0,
                        'successful_items' => 0,
                        'failed_items' => 0,
                        'metadata' => [
                            'domain_id' => $domain->id,
                            'domain_name' => $domain->domain,
                            'trigger' => 'domain_created_observer',
                        ],
                    ]);

                    // Dispatch proper queue job
                    SyncSingleDomainJob::dispatch($syncLog->id, $domain->id);

                    Log::info('Domain created - sync job dispatched', [
                        'domain' => $domain->domain,
                        'sync_log_id' => $syncLog->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to dispatch sync job for new domain', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage()
                ]);
            }
        })->afterResponse();
    }

    /**
     * Handle the Domain "updated" event.
     */
    public function updated(Domain $domain): void
    {
        // If domain name changed, dispatch background job to reassign users
        if ($domain->wasChanged('domain')) {
            dispatch(function () use ($domain) {
                try {
                    // Create sync log entry
                    $syncLog = SyncLog::create([
                        'type' => 'sync_single_domain',
                        'status' => 'pending',
                        'user_id' => null, // System-triggered
                        'total_items' => 0,
                        'processed_items' => 0,
                        'successful_items' => 0,
                        'failed_items' => 0,
                        'metadata' => [
                            'domain_id' => $domain->id,
                            'domain_name' => $domain->domain,
                            'trigger' => 'domain_updated_observer',
                        ],
                    ]);

                    // Dispatch proper queue job
                    SyncSingleDomainJob::dispatch($syncLog->id, $domain->id);

                    Log::info('Domain updated - sync job dispatched', [
                        'domain' => $domain->domain,
                        'sync_log_id' => $syncLog->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch sync job after domain update', [
                        'domain' => $domain->domain,
                        'error' => $e->getMessage()
                    ]);
                }
            })->afterResponse();
        }
    }

    /**
     * Handle the Domain "deleted" event.
     */
    public function deleted(Domain $domain): void
    {
        // Use bulk update instead of looping - MUCH faster!
        $defaultOrganization = \App\Models\Organization::where('name', 'Default Organization')->first();

        // Bulk update: Remove domain_id for all users with this domain
        DB::table('users')
            ->where('domain_id', $domain->id)
            ->update(['domain_id' => null]);

        // Bulk update: Set Default Organization for users without organization_id
        if ($defaultOrganization) {
            DB::table('users')
                ->where('domain_id', $domain->id)
                ->whereNull('organization_id')
                ->update(['organization_id' => $defaultOrganization->id]);
        }

        $affectedCount = DB::table('users')
            ->where('domain_id', $domain->id)
            ->count();

        Log::info('Domain deleted - users unlinked via bulk update', [
            'domain' => $domain->domain,
            'users_count' => $affectedCount
        ]);
    }
}
