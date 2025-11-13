<?php

namespace App\Livewire\OrgAdmin;

use App\Models\Organization;
use App\Models\User;
use App\Models\UserProductSubscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Organization Dashboard')]
class Dashboard extends Component
{
    /**
     * Get the current user's organization
     */
    #[Computed]
    public function organization(): ?Organization
    {
        return auth()->user()->primaryOrganization;
    }

    /**
     * Get organization statistics
     */
    #[Computed]
    public function stats(): array
    {
        $org = $this->organization;

        if (!$org) {
            return [
                'total_members' => 0,
                'active_members' => 0,
                'admin_members' => 0,
                'total_subscriptions' => 0,
                'active_subscriptions' => 0,
            ];
        }

        return [
            'total_members' => $org->users()->count(),
            'active_members' => $org->activeUsers()->count(),
            'admin_members' => $org->admins()->count(),
            'total_subscriptions' => UserProductSubscription::whereIn('user_id', $org->activeUsers()->pluck('id'))->count(),
            'active_subscriptions' => UserProductSubscription::whereIn('user_id', $org->activeUsers()->pluck('id'))
                                                             ->where('is_active', true)
                                                             ->count(),
        ];
    }

    /**
     * Get recent team members
     */
    #[Computed]
    public function recentMembers()
    {
        $org = $this->organization;

        if (!$org) {
            return collect();
        }

        return $org->activeUsers()
                  ->orderByPivot('joined_at', 'desc')
                  ->take(5)
                  ->get();
    }

    /**
     * Get top active users (by activity score)
     */
    #[Computed]
    public function topActiveUsers()
    {
        $org = $this->organization;

        if (!$org) {
            return collect();
        }

        return User::inOrganization($org->id)
                  ->where('activity_score_7d', '>', 0)
                  ->orderBy('activity_score_7d', 'desc')
                  ->take(5)
                  ->get();
    }

    /**
     * Get activity summary for the organization
     */
    #[Computed]
    public function activitySummary(): array
    {
        $org = $this->organization;

        if (!$org) {
            return [
                'avg_score_7d' => 0,
                'avg_score_30d' => 0,
                'high_activity_count' => 0,
            ];
        }

        $users = User::inOrganization($org->id)->get();

        return [
            'avg_score_7d' => round($users->avg('activity_score_7d'), 1),
            'avg_score_30d' => round($users->avg('activity_score_30d'), 1),
            'high_activity_count' => $users->where('activity_score_7d', '>=', 70)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.org-admin.dashboard');
    }
}
