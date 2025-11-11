<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CampaignTagService;
use Illuminate\Http\Request;

class CampaignManagerController extends Controller
{
    protected CampaignTagService $campaignTagService;

    public function __construct(CampaignTagService $campaignTagService)
    {
        $this->campaignTagService = $campaignTagService;
    }

    /**
     * Display the campaign management interface
     */
    public function index()
    {
        return view('admin.campaigns.index');
    }

    /**
     * Get statistics for all active campaign tags
     */
    public function getActiveTagsStats()
    {
        try {
            $stats = $this->campaignTagService->getAllTagsStats();

            return response()->json([
                'success' => true,
                'tags' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for a specific campaign tag
     */
    public function getTagStats(Request $request)
    {
        $request->validate([
            'tag' => 'required|string'
        ]);

        try {
            $stats = $this->campaignTagService->getTagStats($request->tag);

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear a specific campaign tag
     */
    public function clearTag(Request $request)
    {
        $request->validate([
            'tag' => 'required|string'
        ]);

        try {
            $count = $this->campaignTagService->clearTag($request->tag);

            return response()->json([
                'success' => true,
                'message' => "Cleared tag from {$count} users",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview users matching query criteria
     */
    public function previewQuery(Request $request)
    {
        $request->validate([
            'filters' => 'required|array',
        ]);

        try {
            $query = \App\Models\User::query();
            $filters = $request->filters;

            // Apply tier filter
            if (!empty($filters['tier'])) {
                $query->where('tier', $filters['tier']);
            }

            // Apply engagement score filter
            if (!empty($filters['min_engagement'])) {
                $query->where('engagement_score', '>=', $filters['min_engagement']);
            }
            if (!empty($filters['max_engagement'])) {
                $query->where('engagement_score', '<=', $filters['max_engagement']);
            }

            // Apply status filter
            if (!empty($filters['cm_status'])) {
                $query->where('cm_status', $filters['cm_status']);
            }

            // Apply last activity filter
            if (!empty($filters['last_activity_days'])) {
                $date = now()->subDays($filters['last_activity_days']);
                $query->where('last_activity_at', '>=', $date);
            }

            // Apply organization filter
            if (!empty($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }

            $count = $query->count();
            $preview = $query->limit(5)->get(['id', 'name', 'email', 'tier', 'engagement_score', 'cm_status']);

            return response()->json([
                'success' => true,
                'count' => $count,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a campaign and tag matching users
     */
    public function createCampaign(Request $request)
    {
        $request->validate([
            'campaign_name' => 'required|string|max:255',
            'filters' => 'required|array',
        ]);

        try {
            $query = \App\Models\User::query();
            $filters = $request->filters;

            // Apply same filters as preview
            if (!empty($filters['tier'])) {
                $query->where('tier', $filters['tier']);
            }
            if (!empty($filters['min_engagement'])) {
                $query->where('engagement_score', '>=', $filters['min_engagement']);
            }
            if (!empty($filters['max_engagement'])) {
                $query->where('engagement_score', '<=', $filters['max_engagement']);
            }
            if (!empty($filters['cm_status'])) {
                $query->where('cm_status', $filters['cm_status']);
            }
            if (!empty($filters['last_activity_days'])) {
                $date = now()->subDays($filters['last_activity_days']);
                $query->where('last_activity_at', '>=', $date);
            }
            if (!empty($filters['organization_id'])) {
                $query->where('organization_id', $filters['organization_id']);
            }

            $result = $this->campaignTagService->tagUsersFromQuery(
                $query,
                $request->campaign_name
            );

            return response()->json([
                'success' => true,
                'message' => "Campaign created successfully! Tagged {$result['tagged']} users.",
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
