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
        // Find "Default Organization"
        $defaultOrganization = \App\Models\Organization::where('name', 'Default Organization')->first();

        // Get all users with this domain
        $users = \App\Models\User::where('domain_id', $domain->id)->get();

        foreach ($users as $user) {
            // Remove domain association
            $user->domain_id = null;

            // Fallback to Default Organization if user has no other organization
            // or if they were in an organization associated with this domain
            if ($defaultOrganization && !$user->organization_id) {
                $user->organization_id = $defaultOrganization->id;
            }

            $user->save();
        }

        Log::info('Domain deleted - users unlinked and moved to Default Organization', [
            'domain' => $domain->domain,
            'users_count' => $users->count()
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
        // Find "Default Organization"
        $defaultOrganization = \App\Models\Organization::where('name', 'Default Organization')->first();

        // Get all users with this domain
        $users = \App\Models\User::where('domain_id', $domain->id)->get();

        foreach ($users as $user) {
            // Remove domain association
            $user->domain_id = null;

            // Fallback to Default Organization if user has no other organization
            if ($defaultOrganization && !$user->organization_id) {
                $user->organization_id = $defaultOrganization->id;
            }

            $user->save();
        }
    }
}
