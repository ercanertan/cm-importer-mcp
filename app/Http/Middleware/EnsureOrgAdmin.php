<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect()->route('login');
        }

        // Super admins can access org admin features
        // Org admins can access their own org features
        if (!$request->user()->isSuperAdmin() && !$request->user()->isOrgAdmin()) {
            abort(403, 'Organization Admin access required.');
        }

        return $next($request);
    }
}
