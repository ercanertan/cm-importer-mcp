<?php

namespace App\Livewire\User;

use App\Models\Product;
use App\Models\UserProductSubscription;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.user')]
#[Title('My Dashboard')]
class Dashboard extends Component
{
    /**
     * Get user's subscription statistics
     */
    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();

        return [
            'total_subscriptions' => $user->activeProductSubscriptions()->count(),
            'activity_score_7d' => $user->activity_score_7d ?? 0,
            'activity_score_30d' => $user->activity_score_30d ?? 0,
            'last_login' => $user->last_login_at,
            'consent_status' => $user->permission_to_track,
        ];
    }

    /**
     * Get user's active product subscriptions
     */
    #[Computed]
    public function activeSubscriptions()
    {
        return auth()->user()
                    ->productSubscriptions()
                    ->with('product')
                    ->where('is_active', true)
                    ->latest('subscribed_at')
                    ->get();
    }

    /**
     * Get available products user can subscribe to
     */
    #[Computed]
    public function availableProducts()
    {
        $subscribedProductIds = auth()->user()
                                     ->activeProductSubscriptions()
                                     ->pluck('product_id')
                                     ->toArray();

        return Product::where('is_active', true)
                     ->whereNotIn('id', $subscribedProductIds)
                     ->orderBy('sort_order')
                     ->take(5)
                     ->get();
    }

    /**
     * Get recent subscription activity
     */
    #[Computed]
    public function recentActivity()
    {
        return auth()->user()
                    ->productSubscriptions()
                    ->with('product')
                    ->orderBy('updated_at', 'desc')
                    ->take(5)
                    ->get();
    }

    /**
     * Get activity level description
     */
    public function getActivityLevel(int $score): array
    {
        if ($score >= 80) {
            return ['label' => 'Very High', 'color' => 'green'];
        } elseif ($score >= 60) {
            return ['label' => 'High', 'color' => 'blue'];
        } elseif ($score >= 40) {
            return ['label' => 'Medium', 'color' => 'orange'];
        } elseif ($score >= 20) {
            return ['label' => 'Low', 'color' => 'yellow'];
        } else {
            return ['label' => 'Very Low', 'color' => 'gray'];
        }
    }

    public function render()
    {
        return view('livewire.user.dashboard');
    }
}
