<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgMembership
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures the authenticated user is a member of the organization
     * they're trying to access. Super admins bypass this check.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Super admins can access all organizations
        if ($request->user()->isSuperAdmin()) {
            return $next($request);
        }

        // Get organization from route parameter
        $organizationId = $request->route('organization')?->id ?? $request->route('organizationId');

        if (!$organizationId) {
            // If no specific organization in route, check if user belongs to their primary org
            if (!$request->user()->organization_id) {
                abort(403, 'No organization assigned to user.');
            }

            return $next($request);
        }

        $organization = Organization::find($organizationId);

        if (!$organization) {
            abort(404, 'Organization not found.');
        }

        if (!$request->user()->canAccessOrganization($organization)) {
            abort(403, 'You do not have access to this organization.');
        }

        return $next($request);
    }
}
