<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EngagementMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateEngagementScoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public bool $markForSync = false
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(EngagementMetricsService $engagementService): void
    {
        Log::info('Starting engagement score recalculation for all users', [
            'mark_for_sync' => $this->markForSync,
        ]);

        $processed = 0;
        $updated = 0;
        $errors = 0;

        User::chunk(500, function ($users) use ($engagementService, &$processed, &$updated, &$errors) {
            foreach ($users as $user) {
                try {
                    $oldScore = $user->engagement_score;
                    $newScore = $engagementService->calculateEngagementScore($user);

                    $user->engagement_score = $newScore;

                    // Only mark for sync if score changed and flag is set
                    if ($this->markForSync && $oldScore !== $newScore) {
                        $user->cm_tags_need_sync = true;
                    }

                    $user->save();
                    $updated++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Error recalculating engagement score', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processed++;
            }
        });

        Log::info('Engagement score recalculation completed', [
            'processed' => $processed,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }
}
