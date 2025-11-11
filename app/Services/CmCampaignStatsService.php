<?php

namespace App\Services;

use App\Models\CmCampaign;
use CS_REST_Clients;
use CS_REST_Campaigns;
use CS_REST_General;
use Illuminate\Support\Facades\Log;

class CmCampaignStatsService
{
    protected ?string $apiKey;
    protected ?string $clientId;

    public function __construct()
    {
        $this->apiKey = config('campaign-monitor.api_key');
        $this->clientId = config('campaign-monitor.client_id');
    }

    /**
     * Check if Campaign Monitor is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->clientId);
    }

    /**
     * Get all sent campaigns from Campaign Monitor
     *
     * @param int|null $limit Limit number of campaigns to fetch
     * @return array
     */
    public function fetchAllCampaigns(?int $limit = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Campaign Monitor API is not configured');
        }

        try {
            $auth = ['api_key' => $this->apiKey];
            $wrap = new CS_REST_Clients($this->clientId, $auth);

            // Fetch sent campaigns
            $result = $wrap->get_campaigns();

            if (!$result->was_successful()) {
                Log::error('Failed to fetch campaigns from Campaign Monitor', [
                    'http_code' => $result->http_status_code,
                    'response' => $result->response,
                ]);
                throw new \Exception('Failed to fetch campaigns: ' . json_encode($result->response));
            }

            $campaigns = $result->response;

            if ($limit) {
                $campaigns = array_slice($campaigns, 0, $limit);
            }

            return $campaigns;

        } catch (\Exception $e) {
            Log::error('Exception fetching campaigns', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get detailed statistics for a specific campaign
     *
     * @param string $campaignId Campaign Monitor campaign ID
     * @return object|null
     */
    public function fetchCampaignStats(string $campaignId): ?object
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Campaign Monitor API is not configured');
        }

        try {
            $auth = ['api_key' => $this->apiKey];
            $wrap = new CS_REST_Campaigns($campaignId, $auth);

            // Get campaign summary (includes all stats)
            $result = $wrap->get_summary();

            if (!$result->was_successful()) {
                Log::warning('Failed to fetch campaign summary', [
                    'campaign_id' => $campaignId,
                    'http_code' => $result->http_status_code,
                ]);
                return null;
            }

            return $result->response;

        } catch (\Exception $e) {
            Log::error('Exception fetching campaign stats', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Import a single campaign and its stats into the database
     *
     * @param object $campaignData Campaign data from CM API
     * @return CmCampaign|null
     */
    public function importCampaign(object $campaignData): ?CmCampaign
    {
        try {
            // Fetch detailed stats for this campaign
            $stats = $this->fetchCampaignStats($campaignData->CampaignID);

            if (!$stats) {
                Log::warning('Could not fetch stats for campaign, skipping', [
                    'campaign_id' => $campaignData->CampaignID,
                ]);
                return null;
            }

            // Calculate rates
            $totalRecipients = $stats->Recipients ?? 0;
            $openRate = $totalRecipients > 0 ? round(($stats->UniqueOpened / $totalRecipients) * 100, 2) : 0;
            $clickRate = $totalRecipients > 0 ? round(($stats->Clicks / $totalRecipients) * 100, 2) : 0;
            $bounceRate = $totalRecipients > 0 ? round(($stats->Bounced / $totalRecipients) * 100, 2) : 0;
            $unsubscribeRate = $totalRecipients > 0 ? round(($stats->Unsubscribed / $totalRecipients) * 100, 2) : 0;

            // Create or update campaign record
            $campaign = CmCampaign::updateOrCreate(
                ['cm_campaign_id' => $campaignData->CampaignID],
                [
                    'cm_list_id' => $campaignData->ListID ?? null,
                    'name' => $campaignData->Name ?? 'Untitled Campaign',
                    'subject' => $campaignData->Subject ?? null,
                    'from_name' => $campaignData->FromName ?? null,
                    'from_email' => $campaignData->FromEmail ?? null,
                    'reply_to' => $campaignData->ReplyTo ?? null,
                    'sent_at' => isset($campaignData->SentDate) ? \Carbon\Carbon::parse($campaignData->SentDate) : null,
                    'status' => 'sent', // All fetched campaigns are sent
                    'web_version_url' => $campaignData->WebVersionURL ?? null,
                    'web_version_text_url' => $campaignData->WebVersionTextURL ?? null,
                    'total_recipients' => $stats->Recipients ?? 0,
                    'total_opens' => $stats->TotalOpened ?? 0,
                    'unique_opens' => $stats->UniqueOpened ?? 0,
                    'total_clicks' => $stats->Clicks ?? 0,
                    'unique_clicks' => $stats->Clicks ?? 0, // CM doesn't separate unique clicks in summary
                    'total_bounces' => $stats->Bounced ?? 0,
                    'total_unsubscribes' => $stats->Unsubscribed ?? 0,
                    'total_spam_complaints' => $stats->SpamComplaints ?? 0,
                    'forwards' => $stats->Forwards ?? 0,
                    'likes' => $stats->Likes ?? 0,
                    'mentions' => $stats->Mentions ?? 0,
                    'open_rate' => $openRate,
                    'click_rate' => $clickRate,
                    'bounce_rate' => $bounceRate,
                    'unsubscribe_rate' => $unsubscribeRate,
                    'stats_last_synced_at' => now(),
                ]
            );

            return $campaign;

        } catch (\Exception $e) {
            Log::error('Failed to import campaign', [
                'campaign_id' => $campaignData->CampaignID ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Import all campaigns from Campaign Monitor
     *
     * @param int|null $limit Limit number of campaigns to import
     * @return array ['imported' => int, 'failed' => int, 'skipped' => int]
     */
    public function importAllCampaigns(?int $limit = null): array
    {
        $imported = 0;
        $failed = 0;
        $skipped = 0;

        try {
            $campaigns = $this->fetchAllCampaigns($limit);

            Log::info('Starting campaign import', ['total_campaigns' => count($campaigns)]);

            foreach ($campaigns as $campaignData) {
                try {
                    $campaign = $this->importCampaign($campaignData);

                    if ($campaign) {
                        $imported++;
                    } else {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to import individual campaign', [
                        'campaign_id' => $campaignData->CampaignID ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Campaign import completed', [
                'imported' => $imported,
                'failed' => $failed,
                'skipped' => $skipped,
            ]);

        } catch (\Exception $e) {
            Log::error('Campaign import failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    /**
     * Re-sync statistics for existing campaigns
     *
     * @param int|null $limit Limit number of campaigns to sync
     * @return array ['synced' => int, 'failed' => int]
     */
    public function syncCampaignStats(?int $limit = null): array
    {
        $synced = 0;
        $failed = 0;

        $query = CmCampaign::active();

        if ($limit) {
            $query->limit($limit);
        }

        $campaigns = $query->get();

        Log::info('Starting campaign stats sync', ['total_campaigns' => $campaigns->count()]);

        foreach ($campaigns as $campaign) {
            try {
                $stats = $this->fetchCampaignStats($campaign->cm_campaign_id);

                if (!$stats) {
                    $failed++;
                    continue;
                }

                // Update statistics
                $campaign->update([
                    'total_recipients' => $stats->Recipients ?? 0,
                    'total_opens' => $stats->TotalOpened ?? 0,
                    'unique_opens' => $stats->UniqueOpened ?? 0,
                    'total_clicks' => $stats->Clicks ?? 0,
                    'unique_clicks' => $stats->Clicks ?? 0,
                    'total_bounces' => $stats->Bounced ?? 0,
                    'total_unsubscribes' => $stats->Unsubscribed ?? 0,
                    'total_spam_complaints' => $stats->SpamComplaints ?? 0,
                    'forwards' => $stats->Forwards ?? 0,
                    'likes' => $stats->Likes ?? 0,
                    'mentions' => $stats->Mentions ?? 0,
                    'stats_last_synced_at' => now(),
                ]);

                // Recalculate rates
                $campaign->calculateRates();
                $campaign->save();

                $synced++;

            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to sync campaign stats', [
                    'campaign_id' => $campaign->cm_campaign_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Campaign stats sync completed', [
            'synced' => $synced,
            'failed' => $failed,
        ]);

        return [
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Get campaign performance summary
     *
     * @return array
     */
    public function getCampaignSummary(): array
    {
        $campaigns = CmCampaign::active()->get();

        return [
            'total_campaigns' => $campaigns->count(),
            'total_recipients' => $campaigns->sum('total_recipients'),
            'total_opens' => $campaigns->sum('total_opens'),
            'total_clicks' => $campaigns->sum('total_clicks'),
            'avg_open_rate' => $campaigns->avg('open_rate'),
            'avg_click_rate' => $campaigns->avg('click_rate'),
            'high_engagement_campaigns' => $campaigns->where('engagement_level', 'high')->count(),
            'medium_engagement_campaigns' => $campaigns->where('engagement_level', 'medium')->count(),
            'low_engagement_campaigns' => $campaigns->where('engagement_level', 'low')->count(),
        ];
    }
}
