<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Campaign Manager</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Create and manage one-off campaigns with targeted user segments</p>
    </div>

    <!-- Success/Error Messages -->
    @if (session()->has('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-green-600 dark:text-green-400 mr-3 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-green-800 dark:text-green-300">Success</h3>
                    <p class="mt-1 text-sm text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <div class="flex items-start">
                <svg class="h-5 w-5 text-red-600 dark:text-red-400 mr-3 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error</h3>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Tabs -->
    <flux:tabs wire:model="selectedTab">
        <flux:tab name="create" icon="plus-circle">Tag Users (CDP)</flux:tab>
        <flux:tab name="active" icon="tag">Active Tags (CDP)</flux:tab>
        <flux:tab name="helper" icon="sparkles">Quick Tag (CDP)</flux:tab>
        <flux:tab name="backfill" icon="arrow-path">Backfill & Sync</flux:tab>
        <flux:tab name="stats" icon="chart-bar">CM Campaigns</flux:tab>
    </flux:tabs>

    <!-- Create Campaign Tab -->
    <div x-show="$wire.selectedTab === 'create'" x-cloak>
        <!-- Info Panel -->
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-blue-900 dark:text-blue-300">How This Works</p>
                    <ul class="mt-2 text-xs text-blue-800 dark:text-blue-400 space-y-1">
                        <li>1️⃣ <strong>Select users in CDP</strong> - Use filters to find your target audience in this Laravel app</li>
                        <li>2️⃣ <strong>Tag users locally</strong> - Selected users get tagged with <code class="px-1 py-0.5 bg-blue-100 dark:bg-blue-900 rounded">temp_campaign_tag</code> in database</li>
                        <li>3️⃣ <strong>Sync to Campaign Monitor</strong> - Tags sync to CM automatically (scheduled job runs every 15 min)</li>
                        <li>4️⃣ <strong>Create segment in CM</strong> - Go to Campaign Monitor → Create segment where <code class="px-1 py-0.5 bg-blue-100 dark:bg-blue-900 rounded">temp_campaign_tag = "your_campaign_name"</code></li>
                        <li>5️⃣ <strong>Send campaign in CM</strong> - Use CM's interface to create and send the email campaign</li>
                        <li>6️⃣ <strong>Clean up</strong> - Come back here and click "Clear" to remove the tag and free it for next campaign</li>
                    </ul>
                </div>
            </div>
        </div>

        <flux:card>
            <flux:heading>CDP Segment Builder</flux:heading>
            <flux:subheading>Create a tagged user segment in your Customer Data Platform (not in Campaign Monitor)</flux:subheading>

            <form wire:submit="createCampaign" class="space-y-6 mt-6">
                <!-- Campaign Name -->
                <flux:input
                    wire:model="campaignName"
                    label="Campaign Name"
                    placeholder="e.g., Premium Users Re-engagement"
                    required
                />

                <!-- Filter Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Tier Filter -->
                    <flux:select wire:model.live="tier" label="User Tier" placeholder="Any tier">
                        <option value="">Any tier</option>
                        <option value="free">Free</option>
                        <option value="paid_pro">Paid Pro</option>
                        <option value="paid_premium">Paid Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </flux:select>

                    <!-- Engagement Score Min -->
                    <flux:input
                        wire:model.live="minEngagement"
                        type="number"
                        min="0"
                        max="100"
                        label="Min Engagement Score"
                        placeholder="0-100"
                    />

                    <!-- Engagement Score Max -->
                    <flux:input
                        wire:model.live="maxEngagement"
                        type="number"
                        min="0"
                        max="100"
                        label="Max Engagement Score"
                        placeholder="0-100"
                    />

                    <!-- CM Status -->
                    <flux:select wire:model.live="cmStatus" label="Campaign Monitor Status">
                        <option value="">Any status</option>
                        <option value="active">Active</option>
                        <option value="unsubscribed">Unsubscribed</option>
                        <option value="bounced">Bounced</option>
                    </flux:select>

                    <!-- Last Activity -->
                    <flux:input
                        wire:model.live="lastActivityDays"
                        type="number"
                        min="1"
                        label="Active in Last X Days"
                        placeholder="e.g., 30"
                    />

                    <!-- Organization Filter -->
                    <flux:select wire:model.live="organizationId" label="Organization" placeholder="Any organization">
                        <option value="">Any organization</option>
                        @foreach($this->organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <flux:button wire:click.prevent="previewQuery" variant="outline" icon="eye">
                        Preview Matches (in CDP)
                    </flux:button>

                    <flux:button type="submit" variant="primary" icon="tag">
                        Tag Users in CDP Database
                    </flux:button>
                </div>

                <!-- Next Steps Info -->
                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded text-xs text-amber-800 dark:text-amber-400">
                    <strong>⚠️ After tagging:</strong> Users are tagged in your database only. Tags will sync to Campaign Monitor automatically within 15 minutes. Then create a segment in CM and send your campaign there.
                </div>

                <!-- Preview Results -->
                @if($showPreview)
                    <flux:card class="mt-6 bg-blue-50 dark:bg-blue-900/20">
                        <div class="space-y-4">
                            <div>
                                <flux:heading>Preview Results</flux:heading>
                                <flux:subheading>{{ number_format($previewCount) }} users match your filters</flux:subheading>
                            </div>

                            @if(count($previewUsers) > 0)
                                <div class="space-y-2">
                                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Sample of first 5 users:</p>
                                    <div class="space-y-1">
                                        @foreach($previewUsers as $user)
                                            <div class="text-sm p-2 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700">
                                                <span class="font-medium">{{ $user->fullname }}</span>
                                                <span class="text-zinc-500">({{ $user->email }})</span>
                                                <span class="text-zinc-400">-</span>
                                                <span class="text-blue-600 dark:text-blue-400">{{ ucfirst($user->tier ?? 'N/A') }}</span>
                                                <span class="text-zinc-400">-</span>
                                                <span class="text-green-600 dark:text-green-400">{{ $user->engagement_score ?? 0 }}% engaged</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </flux:card>
                @endif
            </form>
        </flux:card>
    </div>

    <!-- Active Campaigns Tab -->
    <div x-show="$wire.selectedTab === 'active'" x-cloak>
        <!-- Info Panel -->
        <div class="mb-4 p-4 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-purple-600 dark:text-purple-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-purple-900 dark:text-purple-300">Active CDP Tags</p>
                    <p class="mt-1 text-xs text-purple-800 dark:text-purple-400">
                        These are user segments tagged in your CDP database. <strong>They are NOT campaigns in Campaign Monitor.</strong> To send an email:
                    </p>
                    <ol class="mt-2 text-xs text-purple-800 dark:text-purple-400 space-y-1 list-decimal list-inside">
                        <li>Wait 15 minutes for tags to sync to CM (or manually trigger sync)</li>
                        <li>Go to Campaign Monitor → Create new segment</li>
                        <li>Filter by: <code class="px-1 py-0.5 bg-purple-100 dark:bg-purple-900 rounded">temp_campaign_tag = "campaign_name"</code></li>
                        <li>Create & send campaign in CM interface</li>
                        <li>Come back here and click "Clear" to remove the tag</li>
                    </ol>
                </div>
            </div>
        </div>

        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading>Active CDP User Tags</flux:heading>
                    <flux:subheading>Tagged user segments ready to sync to Campaign Monitor</flux:subheading>
                </div>
                <flux:button wire:click="loadActiveCampaigns" variant="outline" icon="arrow-path" size="sm">
                    Refresh
                </flux:button>
            </div>

            @if(count($activeCampaigns) > 0)
                <div class="space-y-3">
                    @foreach($activeCampaigns as $tag => $stats)
                        <div class="p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-white dark:bg-zinc-800">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <flux:badge variant="info" icon="tag">{{ $stats['campaign_name'] }}</flux:badge>
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $tag }}</span>
                                    </div>

                                    <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Total Users</p>
                                            <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($stats['user_count']) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Created</p>
                                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ \Carbon\Carbon::parse($stats['created_at'])->diffForHumans() }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Avg Engagement</p>
                                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ number_format($stats['avg_engagement'], 1) }}%</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Top Tier</p>
                                            <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ ucfirst($stats['top_tier']) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 ml-4">
                                    <flux:button
                                        wire:click="viewCampaignStats('{{ $tag }}')"
                                        variant="outline"
                                        size="sm"
                                        icon="chart-bar"
                                    >
                                        Details
                                    </flux:button>
                                    <flux:button
                                        wire:click="clearCampaign('{{ $tag }}')"
                                        wire:confirm="Are you sure you want to clear this campaign tag from all users?"
                                        variant="danger"
                                        size="sm"
                                        icon="trash"
                                    >
                                        Clear
                                    </flux:button>
                                </div>
                            </div>

                            <!-- Campaign Details Modal -->
                            @if($selectedCampaign === $tag && $campaignStats)
                                <div class="mt-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                    <flux:heading size="sm" class="mb-3">Campaign Statistics</flux:heading>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- By Tier -->
                                        <div>
                                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Users by Tier</p>
                                            @foreach($campaignStats['by_tier'] as $tier => $count)
                                                <div class="flex justify-between text-sm py-1">
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ ucfirst($tier) }}</span>
                                                    <span class="font-medium text-zinc-900 dark:text-white">{{ number_format($count) }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        <!-- By Status -->
                                        <div>
                                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-2">Users by Status</p>
                                            @foreach($campaignStats['by_cm_status'] as $status => $count)
                                                <div class="flex justify-between text-sm py-1">
                                                    <span class="text-zinc-700 dark:text-zinc-300">{{ ucfirst($status) }}</span>
                                                    <span class="font-medium text-zinc-900 dark:text-white">{{ number_format($count) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <flux:button
                                        wire:click="$set('selectedCampaign', null)"
                                        variant="ghost"
                                        size="sm"
                                        icon="x-mark"
                                        class="mt-3"
                                    >
                                        Close
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <flux:card class="bg-zinc-50 dark:bg-zinc-900">
                    <div class="text-center py-8">
                        <flux:icon.tag class="mx-auto h-12 w-12 text-zinc-400" />
                        <flux:heading size="lg" class="mt-4">No active campaigns</flux:heading>
                        <flux:subheading class="mt-2">Create your first campaign to get started</flux:subheading>
                        <flux:button wire:click="$set('selectedTab', 'create')" variant="primary" class="mt-4">
                            Create Campaign
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </flux:card>
    </div>

    <!-- Quick Actions Tab -->
    <div x-show="$wire.selectedTab === 'helper'" x-cloak>
        <flux:card>
            <flux:heading>Quick Actions (CDP Tagging)</flux:heading>
            <flux:subheading>Pre-built templates to tag users in CDP - same as "Create Campaign" tab but with fewer filters</flux:subheading>

            <form wire:submit="createHelperCampaign" class="space-y-6 mt-6">
                <!-- Helper Type -->
                <flux:select wire:model.live="helperType" label="Campaign Type">
                    <option value="highly_engaged">Highly Engaged Users</option>
                    <option value="disengaged">Disengaged Users (Re-engagement)</option>
                </flux:select>

                <!-- Conditional Fields for Highly Engaged -->
                @if($helperType === 'highly_engaged')
                    <flux:select wire:model="helperTier" label="User Tier">
                        <option value="free">Free</option>
                        <option value="paid_pro">Paid Pro</option>
                        <option value="paid_premium">Paid Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </flux:select>

                    <flux:input
                        wire:model="helperThreshold"
                        type="number"
                        min="0"
                        max="100"
                        label="Minimum Engagement Score"
                        description="Tag users with engagement score above this threshold"
                    />
                @endif

                <!-- Conditional Fields for Disengaged -->
                @if($helperType === 'disengaged')
                    <flux:input
                        wire:model="helperThreshold"
                        type="number"
                        min="1"
                        label="Inactive Days Threshold"
                        description="Tag users inactive for more than this many days"
                    />
                @endif

                <!-- Campaign Name -->
                <flux:input
                    wire:model="helperCampaignName"
                    label="Campaign Name"
                    placeholder="e.g., Premium Engaged Users Nov 2024"
                    required
                />

                <!-- Submit Button -->
                <flux:button type="submit" variant="primary" icon="check">
                    Create Quick Campaign
                </flux:button>
            </form>

            <!-- Helper Info -->
            <flux:card class="mt-6 bg-blue-50 dark:bg-blue-900/20">
                <flux:heading size="sm">How Quick Actions Work</flux:heading>
                <div class="mt-3 text-sm text-zinc-700 dark:text-zinc-300 space-y-2">
                    <p><strong>Highly Engaged Users:</strong> Tags users of a specific tier with engagement scores above your threshold.</p>
                    <p><strong>Disengaged Users:</strong> Tags users who haven't been active recently for re-engagement campaigns.</p>
                </div>
            </flux:card>
        </flux:card>
    </div>

    <!-- Backfill & Sync Tab -->
    <div x-show="$wire.selectedTab === 'backfill'" x-cloak>
        <flux:card>
            <flux:heading>Backfill & Data Synchronization</flux:heading>
            <flux:subheading>Bulk operations for historical data and synchronization</flux:subheading>

            <div class="mt-6 space-y-6">
                <!-- Sync All Users to CM -->
                <div class="p-6 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Sync All Users to Campaign Monitor</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Creates or updates all user records in Campaign Monitor. This includes:
                            </p>
                            <ul class="mt-2 ml-4 text-sm text-zinc-600 dark:text-zinc-400 list-disc space-y-1">
                                <li>Creates subscribers for users without cm_subscriber_id</li>
                                <li>Updates all 13 custom fields (tier, engagement, org, etc.)</li>
                                <li>Syncs user status (active, unsubscribed, bounced)</li>
                                <li>Processes in background to avoid timeouts</li>
                            </ul>
                            <div class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800">
                                <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                    <strong>Note:</strong> This can take several minutes for large user bases. Progress will be tracked in background jobs.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <flux:button
                            wire:click="syncAllUsersToCm"
                            wire:confirm="This will sync all users to Campaign Monitor. Continue?"
                            variant="primary"
                            icon="cloud-arrow-up"
                        >
                            Sync All Users to CM
                        </flux:button>
                    </div>
                </div>

                <!-- Recalculate Engagement Scores -->
                <div class="p-6 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Recalculate Engagement Scores</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Recalculates engagement scores (0-100) for all users based on:
                            </p>
                            <ul class="mt-2 ml-4 text-sm text-zinc-600 dark:text-zinc-400 list-disc space-y-1">
                                <li><strong>Open Rate (40 pts):</strong> Based on email opens vs sends</li>
                                <li><strong>Click Rate (40 pts):</strong> Based on email clicks vs sends</li>
                                <li><strong>Recency (20 pts):</strong> Based on last email opened date</li>
                            </ul>
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                Use this after importing historical email engagement data or to refresh scores.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <flux:button
                            wire:click="recalculateEngagementScores"
                            wire:confirm="This will recalculate engagement scores for all users. Continue?"
                            variant="primary"
                            icon="calculator"
                        >
                            Recalculate Engagement Scores
                        </flux:button>
                    </div>
                </div>

                <!-- Sync Permanent Tags -->
                <div class="p-6 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Sync Permanent Tags for All Users</h3>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Marks all active users for tag synchronization. This updates permanent tags in Campaign Monitor:
                            </p>
                            <ul class="mt-2 ml-4 text-sm text-zinc-600 dark:text-zinc-400 list-disc space-y-1">
                                <li><strong>[Tier]</strong> tags: Free, Paid Pro, Paid Premium, Enterprise</li>
                                <li><strong>[Engagement]</strong> tags: Based on current engagement score</li>
                                <li><strong>[Status]</strong> tags: Active, Unsubscribed, Bounced</li>
                            </ul>
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                Users will be synced by the scheduled command (runs every 10 minutes). Processes in batches of 1000.
                            </p>
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                <p class="text-sm text-blue-800 dark:text-blue-300">
                                    <strong>Efficient:</strong> Uses bulk API calls (99.8% reduction vs individual syncs)
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <flux:button
                            wire:click="syncAllPermanentTags"
                            wire:confirm="This will mark all active users for tag sync. Continue?"
                            variant="primary"
                            icon="tag"
                        >
                            Sync All Permanent Tags
                        </flux:button>
                    </div>
                </div>

                <!-- Manual Sync Command -->
                <div class="p-6 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Manual CLI Commands</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        For advanced operations, you can run these commands directly:
                    </p>
                    <div class="mt-4 space-y-3">
                        <div class="p-3 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700">
                            <code class="text-sm text-zinc-900 dark:text-zinc-100">php artisan cm:sync-tags --limit=1000</code>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Process tag sync queue immediately</p>
                        </div>
                        <div class="p-3 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700">
                            <code class="text-sm text-zinc-900 dark:text-zinc-100">php artisan cm:setup-fields</code>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Create/update 13 custom fields in Campaign Monitor</p>
                        </div>
                        <div class="p-3 bg-white dark:bg-zinc-800 rounded border border-zinc-200 dark:border-zinc-700">
                            <code class="text-sm text-zinc-900 dark:text-zinc-100">php artisan tinker</code>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Advanced operations via Laravel console</p>
                        </div>
                    </div>
                </div>

                <!-- Current Status -->
                <div class="p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300">Current System Status</h3>
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-blue-600 dark:text-blue-400">Total Users</p>
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ number_format(\App\Models\User::count()) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 dark:text-blue-400">Synced to CM</p>
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ number_format(\App\Models\User::whereNotNull('cm_subscriber_id')->count()) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-blue-600 dark:text-blue-400">Pending Tag Sync</p>
                            <p class="text-2xl font-bold text-blue-900 dark:text-blue-300">{{ number_format(\App\Models\User::where('cm_tags_need_sync', true)->count()) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Campaign Statistics Tab -->
    <div x-show="$wire.selectedTab === 'stats'" x-cloak x-init="$wire.checkCmConnection()">
        <!-- Info Panel -->
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-green-900 dark:text-green-300">Real Campaign Monitor Campaigns</p>
                    <p class="mt-1 text-xs text-green-800 dark:text-green-400">
                        <strong>This is different from the other tabs!</strong> These are actual email campaigns that were created and sent in Campaign Monitor.
                        Import them here to view performance statistics (opens, clicks, etc.) in your CDP for analysis.
                    </p>
                </div>
            </div>
        </div>

        <flux:card>
            <div class="flex justify-between items-center mb-6">
                <div>
                    <flux:heading>Campaign Monitor Campaign Statistics</flux:heading>
                    <flux:subheading>Import and view performance metrics from actual CM email campaigns (not CDP tags)</flux:subheading>
                </div>
                <div class="flex gap-2">
                    <flux:button wire:click="checkCmConnection" variant="ghost" icon="signal" size="sm">
                        Test Connection
                    </flux:button>
                    <flux:button wire:click="syncCampaignStats" variant="ghost" icon="arrow-path" :disabled="$cmConnectionStatus !== 'connected'">
                        Sync Stats
                    </flux:button>
                    <flux:button wire:click="importCampaignStats" wire:loading.attr="disabled" variant="primary" icon="arrow-down-tray" :disabled="$cmConnectionStatus !== 'connected'">
                        <span wire:loading.remove wire:target="importCampaignStats">Import Campaigns</span>
                        <span wire:loading wire:target="importCampaignStats">Importing...</span>
                    </flux:button>
                </div>
            </div>

            <!-- Connection Status Indicator -->
            <div class="mb-6">
                @if($cmConnectionStatus === 'checking')
                    <div class="flex items-center gap-2 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <svg class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-blue-900 dark:text-blue-300">Checking Campaign Monitor Connection...</p>
                            <p class="text-xs text-blue-700 dark:text-blue-400">Verifying API credentials and connectivity</p>
                        </div>
                    </div>
                @elseif($cmConnectionStatus === 'connected')
                    <div class="flex items-center gap-2 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-green-900 dark:text-green-300">✓ Connected to Campaign Monitor</p>
                            <p class="text-xs text-green-700 dark:text-green-400">API connection active and ready to import campaigns</p>
                        </div>
                        <flux:badge color="green" size="sm">Live</flux:badge>
                    </div>
                @elseif($cmConnectionStatus === 'error')
                    <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                        <div class="flex items-start gap-2">
                            <svg class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-red-900 dark:text-red-300">Campaign Monitor Connection Error</p>
                                <p class="text-xs text-red-700 dark:text-red-400 mt-1">{{ $cmConnectionError }}</p>
                                <div class="mt-3">
                                    <flux:button wire:click="checkCmConnection" size="sm" variant="ghost" icon="arrow-path">
                                        Retry Connection
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Summary Stats -->
            @if($this->campaignSummary['total_campaigns'] > 0)
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Campaigns</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->campaignSummary['total_campaigns']) }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Recipients</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->campaignSummary['total_recipients']) }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Opens</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->campaignSummary['total_opens']) }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Total Clicks</p>
                        <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($this->campaignSummary['total_clicks']) }}</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Avg Open Rate</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->campaignSummary['avg_open_rate'] }}%</p>
                    </div>
                    <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">Avg Click Rate</p>
                        <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->campaignSummary['avg_click_rate'] }}%</p>
                    </div>
                </div>

                <!-- Filters -->
                <div class="flex gap-2 mb-6">
                    <flux:button wire:click="$set('statsFilter', 'all')" size="sm" :variant="$statsFilter === 'all' ? 'primary' : 'ghost'">
                        All
                    </flux:button>
                    <flux:button wire:click="$set('statsFilter', 'high')" size="sm" :variant="$statsFilter === 'high' ? 'primary' : 'ghost'">
                        High Engagement
                    </flux:button>
                    <flux:button wire:click="$set('statsFilter', 'medium')" size="sm" :variant="$statsFilter === 'medium' ? 'primary' : 'ghost'">
                        Medium Engagement
                    </flux:button>
                    <flux:button wire:click="$set('statsFilter', 'low')" size="sm" :variant="$statsFilter === 'low' ? 'primary' : 'ghost'">
                        Low Engagement
                    </flux:button>
                </div>

                <!-- Campaign List -->
                <div class="space-y-4">
                    @forelse($this->cmCampaigns as $campaign)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 hover:border-zinc-300 dark:hover:border-zinc-600 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $campaign->name }}</h3>
                                        @php
                                            $engagementColors = [
                                                'high' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                                'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                                'low' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                                            ];
                                        @endphp
                                        <span class="px-2 py-1 text-xs rounded-full {{ $engagementColors[$campaign->engagement_level] }}">
                                            {{ ucfirst($campaign->engagement_level) }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-1">{{ $campaign->subject }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500">
                                        Sent: {{ $campaign->sent_at?->format('M d, Y g:i A') ?? 'N/A' }}
                                    </p>
                                </div>
                                <flux:button wire:click="viewCmCampaign({{ $campaign->id }})" size="sm" variant="ghost" icon="eye">
                                    View Details
                                </flux:button>
                            </div>

                            <!-- Quick Stats Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Recipients</p>
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($campaign->total_recipients) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Opens</p>
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($campaign->unique_opens) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Clicks</p>
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($campaign->unique_clicks) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Open Rate</p>
                                    <p class="text-sm font-semibold text-green-600 dark:text-green-400">{{ $campaign->open_rate }}%</p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Click Rate</p>
                                    <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">{{ $campaign->click_rate }}%</p>
                                </div>
                                <div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Bounces</p>
                                    <p class="text-sm font-semibold text-red-600 dark:text-red-400">{{ number_format($campaign->total_bounces) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">No campaigns imported yet</h3>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Click "Import Campaigns" to get started</p>
                        </div>
                    @endforelse
                </div>

                <!-- Pagination -->
                @if($this->cmCampaigns->hasPages())
                    <div class="mt-6">
                        {{ $this->cmCampaigns->links() }}
                    </div>
                @endif
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-zinc-400 dark:text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">No campaign statistics available</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Import campaigns from Campaign Monitor to view statistics</p>
                    <div class="mt-6">
                        <flux:button wire:click="importCampaignStats" variant="primary" icon="arrow-down-tray">
                            Import Campaigns from Campaign Monitor
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:card>

        <!-- Campaign Details Modal -->
        @if($selectedCmCampaign)
            <flux:modal wire:model="selectedCmCampaign" variant="flyout" class="max-w-2xl">
                <flux:heading>{{ $selectedCmCampaign->name }}</flux:heading>
                <flux:subheading>Campaign Details & Statistics</flux:subheading>

                <div class="space-y-6 mt-6">
                    <!-- Campaign Info -->
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Campaign Information</h4>
                        <dl class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Subject</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ $selectedCmCampaign->subject }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Sent Date</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ $selectedCmCampaign->sent_at?->format('M d, Y g:i A') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">From</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ $selectedCmCampaign->from_name }} ({{ $selectedCmCampaign->from_email }})</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Reply To</dt>
                                <dd class="text-sm text-zinc-900 dark:text-white">{{ $selectedCmCampaign->reply_to }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Performance Metrics -->
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Performance Metrics</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Total Recipients</p>
                                <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_recipients) }}</p>
                            </div>
                            <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Engagement Level</p>
                                <p class="text-2xl font-bold text-zinc-900 dark:text-white">{{ ucfirst($selectedCmCampaign->engagement_level) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Engagement Stats -->
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Engagement Statistics</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Total Opens</p>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_opens) }}</p>
                            </div>
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Unique Opens</p>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->unique_opens) }}</p>
                            </div>
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Total Clicks</p>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_clicks) }}</p>
                            </div>
                            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3">
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Unique Clicks</p>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->unique_clicks) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Rates -->
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Conversion Rates</h4>
                        <div class="grid grid-cols-4 gap-3">
                            <div class="text-center bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                                <p class="text-xs text-green-600 dark:text-green-400">Open Rate</p>
                                <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ $selectedCmCampaign->open_rate }}%</p>
                            </div>
                            <div class="text-center bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                                <p class="text-xs text-blue-600 dark:text-blue-400">Click Rate</p>
                                <p class="text-xl font-bold text-blue-700 dark:text-blue-300">{{ $selectedCmCampaign->click_rate }}%</p>
                            </div>
                            <div class="text-center bg-red-50 dark:bg-red-900/20 rounded-lg p-3">
                                <p class="text-xs text-red-600 dark:text-red-400">Bounce Rate</p>
                                <p class="text-xl font-bold text-red-700 dark:text-red-300">{{ $selectedCmCampaign->bounce_rate }}%</p>
                            </div>
                            <div class="text-center bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3">
                                <p class="text-xs text-orange-600 dark:text-orange-400">Unsub Rate</p>
                                <p class="text-xl font-bold text-orange-700 dark:text-orange-300">{{ $selectedCmCampaign->unsubscribe_rate }}%</p>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Additional Statistics</h4>
                        <dl class="grid grid-cols-3 gap-4 text-sm">
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Bounces</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_bounces) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Unsubscribes</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_unsubscribes) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Spam</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->total_spam_complaints) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Forwards</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->forwards) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Likes</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->likes) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-zinc-500 dark:text-zinc-400">Mentions</dt>
                                <dd class="text-sm font-semibold text-zinc-900 dark:text-white">{{ number_format($selectedCmCampaign->mentions) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Web Version Links -->
                    @if($selectedCmCampaign->web_version_url)
                        <div>
                            <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-2">Links</h4>
                            <div class="flex gap-2">
                                <flux:button href="{{ $selectedCmCampaign->web_version_url }}" target="_blank" variant="ghost" icon="globe-alt" size="sm">
                                    View Web Version
                                </flux:button>
                                @if($selectedCmCampaign->web_version_text_url)
                                    <flux:button href="{{ $selectedCmCampaign->web_version_text_url }}" target="_blank" variant="ghost" icon="document-text" size="sm">
                                        View Text Version
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Sync Info -->
                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            Last synced: {{ $selectedCmCampaign->stats_last_synced_at?->diffForHumans() ?? 'Never' }}
                        </p>
                    </div>
                </div>

                <flux:button wire:click="closeCmCampaignModal" class="mt-6">Close</flux:button>
            </flux:modal>
        @endif
    </div>
</div>
