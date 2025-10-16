<?php

namespace App\Observers;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;

class DomainObserver
{
    /**
     * Handle the Domain "created" event.
     * Automatically scan and assign users when a new domain is created
     */
    public function created(Domain $domain): void
    {
        // Defer the assignment to avoid blocking the creation
        dispatch(function () use ($domain) {
            try {
                // Refresh the domain to get latest data including any organizations
                $domain->refresh();

                // If domain has organizations, assign users
                if ($domain->organizations()->exists()) {
                    $result = $domain->assignUsersFromDomain();

                    Log::info('Domain created - users automatically assigned', [
                        'domain' => $domain->domain,
                        'assigned_count' => $result['assigned_count'],
                        'total_users' => $result['total_users']
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to auto-assign users to new domain', [
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
        // If domain name changed, reassign users
        if ($domain->wasChanged('domain')) {
            dispatch(function () use ($domain) {
                try {
                    $result = $domain->assignUsersFromDomain();

                    Log::info('Domain updated - users reassigned', [
                        'domain' => $domain->domain,
                        'assigned_count' => $result['assigned_count'],
                        'total_users' => $result['total_users']
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to reassign users after domain update', [
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
        // Optionally: Set domain_id to null for all users with this domain
        \App\Models\User::where('domain_id', $domain->id)
            ->update(['domain_id' => null]);

        Log::info('Domain deleted - users unlinked', [
            'domain' => $domain->domain
        ]);
    }

    /**
     * Handle the Domain "restored" event.
     */
    public function restored(Domain $domain): void
    {
        // When a domain is restored, reassign users
        dispatch(function () use ($domain) {
            try {
                $result = $domain->assignUsersFromDomain();

                Log::info('Domain restored - users reassigned', [
                    'domain' => $domain->domain,
                    'assigned_count' => $result['assigned_count'],
                    'total_users' => $result['total_users']
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to reassign users after domain restore', [
                    'domain' => $domain->domain,
                    'error' => $e->getMessage()
                ]);
            }
        })->afterResponse();
    }

    /**
     * Handle the Domain "force deleted" event.
     */
    public function forceDeleted(Domain $domain): void
    {
        // Permanently remove domain references
        \App\Models\User::where('domain_id', $domain->id)
            ->update(['domain_id' => null]);
    }
}
