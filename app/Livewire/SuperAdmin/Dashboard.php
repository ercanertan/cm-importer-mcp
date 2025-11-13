<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Organization;
use App\Models\Product;
use App\Models\Tier;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.super-admin')]
#[Title('Super Admin Dashboard')]
class Dashboard extends Component
{
    /**
     * Get platform statistics
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total_organizations' => Organization::count(),
            'active_organizations' => Organization::active()->count(),
            'total_users' => User::count(),
            'super_admins' => User::superAdmins()->count(),
            'org_admins' => User::orgAdmins()->count(),
            'regular_users' => User::users()->count(),
            'total_tiers' => Tier::count(),
            'active_tiers' => Tier::where('is_active', true)->count(),
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
        ];
    }

    /**
     * Get recent organizations
     */
    #[Computed]
    public function recentOrganizations()
    {
        return Organization::with('tier')
                          ->latest()
                          ->take(5)
                          ->get();
    }

    /**
     * Get recent users
     */
    #[Computed]
    public function recentUsers()
    {
        return User::with('primaryOrganization')
                  ->latest()
                  ->take(5)
                  ->get();
    }

    /**
     * Get tier distribution
     */
    #[Computed]
    public function tierDistribution()
    {
        return Tier::withCount('organizations')
                  ->where('is_active', true)
                  ->get();
    }

    public function render()
    {
        return view('livewire.super-admin.dashboard');
    }
}
