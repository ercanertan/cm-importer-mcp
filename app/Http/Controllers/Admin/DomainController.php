<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    /**
     * Display a list of all domains with statistics
     */
    public function index()
    {
        $domains = Domain::with('organizations')
            ->withCount('users')
            ->orderBy('users_count', 'desc')
            ->paginate(50);

        return view('admin.domains.index', compact('domains'));
    }

    /**
     * Store a newly created domain
     */
    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string|unique:domains,domain',
            'organization_id' => 'nullable|exists:organizations,id'
        ]);

        DB::beginTransaction();
        try {
            $domain = Domain::create([
                'domain' => strtolower(trim($request->domain))
            ]);

            // If organization is provided, associate it
            if ($request->organization_id) {
                $organization = Organization::find($request->organization_id);
                $domain->organizations()->attach($organization->id);

                // Trigger user assignment
                $result = $domain->assignUsersToOrganization($organization);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Domain created and {$result} users assigned",
                    'domain' => $domain->load('organizations'),
                    'assigned_count' => $result
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Domain created successfully',
                'domain' => $domain
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create domain', [
                'error' => $e->getMessage(),
                'domain' => $request->domain
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually sync a domain - scan database and assign users
     */
    public function sync($id)
    {
        try {
            $domain = Domain::findOrFail($id);

            $result = $domain->assignUsersFromDomain();

            Log::info('Domain manually synced', [
                'domain' => $domain->domain,
                'assigned_count' => $result['assigned_count'],
                'total_users' => $result['total_users']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced successfully. {$result['assigned_count']} users updated out of {$result['total_users']} total.",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync domain', [
                'domain_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Associate a domain with an organization and assign users
     */
    public function associateOrganization(Request $request, $id)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id'
        ]);

        DB::beginTransaction();
        try {
            $domain = Domain::findOrFail($id);
            $organization = Organization::findOrFail($request->organization_id);

            // Associate domain with organization
            $assignedCount = $domain->assignUsersToOrganization($organization);

            DB::commit();

            Log::info('Domain associated with organization', [
                'domain' => $domain->domain,
                'organization' => $organization->name,
                'assigned_count' => $assignedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$assignedCount} users assigned to {$organization->name}",
                'assigned_count' => $assignedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to associate domain with organization', [
                'domain_id' => $id,
                'organization_id' => $request->organization_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to associate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove association between domain and organization
     */
    public function dissociateOrganization(Request $request, $id)
    {
        $request->validate([
            'organization_id' => 'required|exists:organizations,id'
        ]);

        try {
            $domain = Domain::findOrFail($id);
            $domain->organizations()->detach($request->organization_id);

            return response()->json([
                'success' => true,
                'message' => 'Organization dissociated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to dissociate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get domain statistics
     */
    public function show($id)
    {
        try {
            $domain = Domain::with(['organizations', 'users'])
                ->withCount('users')
                ->findOrFail($id);

            // Get user breakdown by organization
            $usersByOrganization = DB::table('users')
                ->select('organization_id', DB::raw('count(*) as count'))
                ->where('domain_id', $id)
                ->groupBy('organization_id')
                ->get();

            return response()->json([
                'success' => true,
                'domain' => $domain,
                'users_by_organization' => $usersByOrganization
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domain: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all domains - useful for bulk operations
     */
    public function syncAll()
    {
        try {
            $domains = Domain::all();
            $totalAssigned = 0;
            $results = [];

            foreach ($domains as $domain) {
                $result = $domain->assignUsersFromDomain();
                $totalAssigned += $result['assigned_count'];
                $results[] = [
                    'domain' => $domain->domain,
                    'assigned' => $result['assigned_count'],
                    'total' => $result['total_users']
                ];
            }

            Log::info('All domains synced', [
                'total_domains' => $domains->count(),
                'total_assigned' => $totalAssigned
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced {$domains->count()} domains. {$totalAssigned} users updated.",
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync all domains', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync domains: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a domain
     */
    public function destroy($id)
    {
        try {
            $domain = Domain::findOrFail($id);
            $domainName = $domain->domain;
            $domain->delete();

            return response()->json([
                'success' => true,
                'message' => "Domain '{$domainName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete domain: ' . $e->getMessage()
            ], 500);
        }
    }
}
