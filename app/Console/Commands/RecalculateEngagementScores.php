<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EngagementMetricsService;
use Illuminate\Console\Command;

class RecalculateEngagementScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:recalculate-engagement
                            {--user-id= : Recalculate for a specific user ID}
                            {--batch-size=500 : Number of users to process per batch}
                            {--mark-for-sync : Mark users for CM tag sync after recalculation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate engagement scores for all users or a specific user';

    /**
     * Execute the console command.
     */
    public function handle(EngagementMetricsService $engagementService): int
    {
        $userId = $this->option('user-id');
        $batchSize = (int) $this->option('batch-size');
        $markForSync = $this->option('mark-for-sync');

        // Single user recalculation
        if ($userId) {
            return $this->recalculateSingleUser($userId, $engagementService, $markForSync);
        }

        // Batch recalculation for all users
        return $this->recalculateAllUsers($engagementService, $batchSize, $markForSync);
    }

    /**
     * Recalculate engagement score for a single user
     */
    protected function recalculateSingleUser(int $userId, EngagementMetricsService $engagementService, bool $markForSync): int
    {
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return self::FAILURE;
        }

        $this->info("Recalculating engagement score for user: {$user->email}");

        $score = $engagementService->calculateEngagementScore($user);
        $user->engagement_score = $score;

        if ($markForSync) {
            $user->cm_tags_need_sync = true;
        }

        $user->save();

        $this->info("✓ Engagement score updated to: {$score}%");

        if ($markForSync) {
            $this->info("✓ User marked for CM tag sync");
        }

        return self::SUCCESS;
    }

    /**
     * Recalculate engagement scores for all users in batches
     */
    protected function recalculateAllUsers(EngagementMetricsService $engagementService, int $batchSize, bool $markForSync): int
    {
        $totalUsers = User::count();

        if ($totalUsers === 0) {
            $this->info('No users found to process.');
            return self::SUCCESS;
        }

        $this->info("Starting engagement score recalculation for {$totalUsers} users...");
        $this->info("Batch size: {$batchSize}");

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        $processed = 0;
        $updated = 0;
        $errors = 0;

        User::chunk($batchSize, function ($users) use ($engagementService, $markForSync, $progressBar, &$processed, &$updated, &$errors) {
            foreach ($users as $user) {
                try {
                    $oldScore = $user->engagement_score;
                    $newScore = $engagementService->calculateEngagementScore($user);

                    $user->engagement_score = $newScore;

                    // Only mark for sync if score changed and flag is set
                    if ($markForSync && $oldScore !== $newScore) {
                        $user->cm_tags_need_sync = true;
                    }

                    $user->save();

                    $updated++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("Error processing user {$user->id}: {$e->getMessage()}");
                }

                $processed++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Recalculation Complete ===');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users', $totalUsers],
                ['Processed', $processed],
                ['Updated Successfully', $updated],
                ['Errors', $errors],
            ]
        );

        if ($markForSync) {
            $markedCount = User::where('cm_tags_need_sync', true)->count();
            $this->info("✓ {$markedCount} users marked for CM tag sync");
            $this->info("Run 'php artisan cm:sync-tags' to sync permanent tags to Campaign Monitor");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
