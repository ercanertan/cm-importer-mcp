<?php

namespace App\Jobs;

use App\Models\User;
use CS_REST_Subscribers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Bulk sync campaign tags to Campaign Monitor
 *
 * This job syncs temp_campaign_tag values to Campaign Monitor using the Import API.
 * Processes users in batches of 1000 for optimal performance.
 */
class TagUsersBulkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $userIds,
        public string $campaignTag,
        public ?string $listId = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $listId = $this->listId ?? config('campaign-monitor.list_id');

        if (! $listId) {
            Log::error('TagUsersBulkJob: Campaign Monitor list ID not configured');

            return;
        }

        // Process users in chunks of 1000 (CM API limit)
        $users = User::whereIn('id', $this->userIds)->get();

        if ($users->isEmpty()) {
            Log::warning('TagUsersBulkJob: No users found for IDs', [
                'user_ids_count' => count($this->userIds),
                'campaign_tag' => $this->campaignTag,
            ]);

            return;
        }

        // Prepare subscriber data for CM Import API
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
                        'Value' => $this->campaignTag,
                        'Clear' => false,
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

                Log::info('TagUsersBulkJob: Batch imported to CM', [
                    'batch' => $index + 1,
                    'total_batches' => count($chunks),
                    'campaign_tag' => $this->campaignTag,
                    'success' => $result['success'],
                    'failed' => $result['failed'],
                ]);
            } catch (\Exception $e) {
                Log::error('TagUsersBulkJob: Failed to import batch to CM', [
                    'batch' => $index + 1,
                    'campaign_tag' => $this->campaignTag,
                    'error' => $e->getMessage(),
                ]);

                throw $e; // Re-throw to trigger retry
            }
        }

        Log::info('TagUsersBulkJob: Completed bulk tag sync to CM', [
            'campaign_tag' => $this->campaignTag,
            'total_users' => count($this->userIds),
            'total_success' => $totalSuccess,
            'total_failed' => $totalFailed,
            'batches' => count($chunks),
        ]);
    }

    /**
     * Import subscribers to Campaign Monitor
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
