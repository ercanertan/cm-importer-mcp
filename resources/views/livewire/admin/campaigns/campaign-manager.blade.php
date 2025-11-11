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
        <flux:tab name="create" icon="plus-circle">Create Campaign</flux:tab>
        <flux:tab name="active" icon="tag">Active Campaigns</flux:tab>
        <flux:tab name="helper" icon="sparkles">Quick Actions</flux:tab>
    </flux:tabs>

    <!-- Create Campaign Tab -->
    <div x-show="$wire.selectedTab === 'create'" x-cloak>
        <flux:card>
            <flux:heading>Segment Builder</flux:heading>
            <flux:subheading>Define your target audience using the filters below</flux:subheading>

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
                        Preview Matches
                    </flux:button>

                    <flux:button type="submit" variant="primary" icon="check">
                        Create Campaign & Tag Users
                    </flux:button>
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
        <flux:card>
            <div class="flex items-center justify-between mb-6">
                <div>
                    <flux:heading>Active Campaigns</flux:heading>
                    <flux:subheading>View and manage your active campaign tags</flux:subheading>
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
            <flux:heading>Quick Actions</flux:heading>
            <flux:subheading>Pre-built campaign templates for common scenarios</flux:subheading>

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
</div>
