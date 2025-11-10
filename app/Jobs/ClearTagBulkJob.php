<?php

namespace App\Jobs;

use App\Models\User;
use CS_REST_Subscribers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Bulk clear campaign tags from Campaign Monitor and local database
 *
 * This job clears temp_campaign_tag values after a campaign has been sent.
 * It syncs the cleared tags to Campaign Monitor and updates the local database.
 * Processes users in batches of 1000 for optimal performance.
 */
class ClearTagBulkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $campaignTag,
        public ?string $listId = null,
        public bool $syncToCampaignMonitor = true
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Get all users with this campaign tag
        $users = User::where('temp_campaign_tag', $this->campaignTag)->get();

        if ($users->isEmpty()) {
            Log::info('ClearTagBulkJob: No users found with campaign tag', [
                'campaign_tag' => $this->campaignTag,
            ]);

            return;
        }

        $totalUsers = $users->count();

        Log::info('ClearTagBulkJob: Starting tag cleanup', [
            'campaign_tag' => $this->campaignTag,
            'total_users' => $totalUsers,
            'sync_to_cm' => $this->syncToCampaignMonitor,
        ]);

        // Clear tags in local database first
        $clearedCount = User::where('temp_campaign_tag', $this->campaignTag)
            ->update(['temp_campaign_tag' => null]);

        Log::info('ClearTagBulkJob: Cleared tags in local database', [
            'campaign_tag' => $this->campaignTag,
            'cleared_count' => $clearedCount,
        ]);

        // Sync to Campaign Monitor if requested
        if ($this->syncToCampaignMonitor) {
            $this->syncClearToCampaignMonitor($users);
        }

        Log::info('ClearTagBulkJob: Completed tag cleanup', [
            'campaign_tag' => $this->campaignTag,
            'total_users' => $totalUsers,
            'cleared_count' => $clearedCount,
        ]);
    }

    /**
     * Sync cleared tags to Campaign Monitor
     */
    protected function syncClearToCampaignMonitor($users): void
    {
        $listId = $this->listId ?? config('campaign-monitor.list_id');

        if (! $listId) {
            Log::error('ClearTagBulkJob: Campaign Monitor list ID not configured');

            return;
        }

        // Prepare subscriber data for CM Import API (clearing the tag)
        $subscribers = $users->map(function ($user) {
            return [
                'EmailAddress' => $user->email,
                'Name' => $user->fullname,
                'CustomFields' => [
                    [
                        'Key' => 'user_id',
                        'Value' => (string) $user->id,
                        'Clear' => false,
                    ],
                    [
                        'Key' => 'organization_name',
                        'Value' => $user->organization?->name ?? '',
                        'Clear' => false,
                    ],
                    [
                        'Key' => 'tier',
                        'Value' => $user->tier ?? 'free',
                        'Clear' => false,
                    ],
                    [
                        'Key' => 'temp_campaign_tag',
                        'Value' => '',
                        'Clear' => true, // Clear the field
                    ],
                ],
                'Resubscribe' => false,
                'RestartSubscriptionBasedAutoresponders' => false,
                'ConsentToTrack' => $user->permission_to_track ? 'Yes' : 'No',
            ];
        })->toArray();

        // Chunk into batches of 1000 for CM API
        $chunks = array_chunk($subscribers, 1000);

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach ($chunks as $index => $chunk) {
            try {
                $result = $this->importSubscribers($listId, $chunk);

                $totalSuccess += $result['success'];
                $totalFailed += $result['failed'];

                Log::info('ClearTagBulkJob: Batch synced to CM', [
                    'batch' => $index + 1,
                    'total_batches' => count($chunks),
                    'campaign_tag' => $this->campaignTag,
                    'success' => $result['success'],
                    'failed' => $result['failed'],
                ]);
            } catch (\Exception $e) {
                Log::error('ClearTagBulkJob: Failed to sync batch to CM', [
                    'batch' => $index + 1,
                    'campaign_tag' => $this->campaignTag,
                    'error' => $e->getMessage(),
                ]);

                throw $e; // Re-throw to trigger retry
            }
        }

        Log::info('ClearTagBulkJob: Completed CM sync', [
            'campaign_tag' => $this->campaignTag,
            'total_users' => $users->count(),
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'batches' => count($chunks),
        ]);
    }

    /**
     * Import subscribers to Campaign Monitor to clear tags
     */
    protected function importSubscribers(string $listId, array $subscribers): array
    {
        $subscribersApi = new CS_REST_Subscribers(
            $listId,
            ['api_key' => config('campaign-monitor.api_key')]
        );

        $result = $subscribersApi->import($subscribers);

        if (! $result->was_successful()) {
            $errorMessage = is_object($result->response)
                ? json_encode($result->response)
                : $result->response;

            throw new \Exception("Failed to import subscribers: {$errorMessage}");
        }

        // Parse results
        $totalSuccess = count($result->response->TotalNewSubscribers ?? []);
        $totalSuccess += count($result->response->TotalExistingSubscribers ?? []);
        $totalFailed = count($result->response->FailureDetails ?? []);

        return [
            'success' => $totalSuccess,
            'failed' => $totalFailed,
            'duplicates' => count($result->response->DuplicateEmailsInSubmission ?? []),
        ];
    }
}
