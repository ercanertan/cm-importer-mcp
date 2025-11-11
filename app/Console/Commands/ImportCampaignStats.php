<?php

namespace App\Console\Commands;

use App\Services\CmCampaignStatsService;
use Illuminate\Console\Command;

class ImportCampaignStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cm:import-campaign-stats
                            {--limit= : Limit number of campaigns to import}
                            {--sync-only : Only sync stats for existing campaigns, don\'t import new ones}
                            {--show-summary : Display campaign performance summary after import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import campaign statistics from Campaign Monitor into CDP';

    /**
     * Execute the console command.
     */
    public function handle(CmCampaignStatsService $statsService): int
    {
        if (!$statsService->isConfigured()) {
            $this->error('Campaign Monitor API is not configured.');
            $this->warn('Please set CM_API_KEY and CM_CLIENT_ID in your .env file.');
            return self::FAILURE;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $syncOnly = $this->option('sync-only');
        $showSummary = $this->option('show-summary');

        try {
            if ($syncOnly) {
                // Only sync existing campaigns
                $this->info('Syncing statistics for existing campaigns...');
                $progressBar = $this->output->createProgressBar();
                $progressBar->start();

                $result = $statsService->syncCampaignStats($limit);

                $progressBar->finish();
                $this->newLine(2);

                $this->info("✓ Campaign stats sync completed!");
                $this->table(
                    ['Metric', 'Count'],
                    [
                        ['Synced', $result['synced']],
                        ['Failed', $result['failed']],
                    ]
                );
            } else {
                // Import new campaigns
                $this->info('Importing campaigns from Campaign Monitor...');

                if ($limit) {
                    $this->info("Limiting import to {$limit} campaigns");
                }

                $progressBar = $this->output->createProgressBar();
                $progressBar->start();

                $result = $statsService->importAllCampaigns($limit);

                $progressBar->finish();
                $this->newLine(2);

                $this->info("✓ Campaign import completed!");
                $this->table(
                    ['Metric', 'Count'],
                    [
                        ['Imported', $result['imported']],
                        ['Skipped', $result['skipped']],
                        ['Failed', $result['failed']],
                    ]
                );
            }

            // Show summary if requested
            if ($showSummary) {
                $this->newLine();
                $this->displayCampaignSummary($statsService);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to import campaign stats: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Display campaign performance summary
     */
    protected function displayCampaignSummary(CmCampaignStatsService $statsService): void
    {
        $this->info('=== Campaign Performance Summary ===');

        $summary = $statsService->getCampaignSummary();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Campaigns', number_format($summary['total_campaigns'])],
                ['Total Recipients', number_format($summary['total_recipients'])],
                ['Total Opens', number_format($summary['total_opens'])],
                ['Total Clicks', number_format($summary['total_clicks'])],
                ['Average Open Rate', round($summary['avg_open_rate'], 2) . '%'],
                ['Average Click Rate', round($summary['avg_click_rate'], 2) . '%'],
                ['High Engagement Campaigns', $summary['high_engagement_campaigns']],
                ['Medium Engagement Campaigns', $summary['medium_engagement_campaigns']],
                ['Low Engagement Campaigns', $summary['low_engagement_campaigns']],
            ]
        );
    }
}
