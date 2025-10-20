<?php

namespace App\Console\Commands;

use App\Jobs\RevalidateConditionalOrganizationsJob;
use App\Models\Organization;
use App\Models\SyncLog;
use Illuminate\Console\Command;

class RevalidateConditionalMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:revalidate-conditional
                            {--organization= : Specific organization ID to revalidate}
                            {--dry-run : Show what would change without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revalidate conditional organization memberships and remove users who no longer qualify (preserves manual assignments)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizationId = $this->option('organization');
        $dryRun = $this->option('dry-run');

        // Validate organization if specified
        if ($organizationId) {
            $organization = Organization::find($organizationId);

            if (!$organization) {
                $this->error("Organization with ID {$organizationId} not found.");
                return 1;
            }

            if (empty($organization->conditional_rules) || empty($organization->conditional_rules['conditions'])) {
                $this->error("Organization '{$organization->name}' does not have conditional rules.");
                return 1;
            }

            $this->info("Revalidating organization: {$organization->name}");
        } else {
            $this->info('Revalidating ALL conditional organizations...');
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No actual changes will be made');
        }

        // Create sync log
        $syncLog = SyncLog::create([
            'type' => 'revalidate_conditional',
            'status' => 'pending',
            'metadata' => [
                'organization_id' => $organizationId,
                'dry_run' => $dryRun,
                'initiated_by' => 'cli',
            ],
        ]);

        $this->info("Sync log created: ID {$syncLog->id}");

        // Dispatch job
        $job = new RevalidateConditionalOrganizationsJob(
            $syncLog->id,
            $organizationId ? (int)$organizationId : null,
            $dryRun
        );

        // Run synchronously for CLI feedback
        $job->handle();

        // Refresh to get latest status
        $syncLog->refresh();

        if ($syncLog->status === 'completed') {
            $this->newLine();
            $this->info('✓ Revalidation completed successfully');

            $metadata = $syncLog->metadata ?? [];
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Organizations Processed', $metadata['total_organizations'] ?? 0],
                    ['Users Removed (auto-assigned)', $metadata['total_removed'] ?? 0],
                    ['Manual Assignments Preserved', $metadata['total_preserved'] ?? 0],
                    ['Users Reassigned to Default', $metadata['total_reassigned'] ?? 0],
                ]
            );

            if ($dryRun) {
                $this->newLine();
                $this->warn('This was a DRY RUN. No actual changes were made.');
                $this->info('Run without --dry-run to apply these changes.');
            }

            return 0;
        } else {
            $this->error('✗ Revalidation failed');
            $this->error('Error: ' . $syncLog->error_message);
            return 1;
        }
    }
}
