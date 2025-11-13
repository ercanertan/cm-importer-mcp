<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * Redirect users to their appropriate dashboard based on their role.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Super Admin gets platform-wide dashboard
        if ($user->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        // Org Admin gets organization dashboard
        if ($user->isOrgAdmin()) {
            return redirect()->route('org.dashboard');
        }

        // Regular users get personal dashboard
        return redirect()->route('user.dashboard');
    }
}
