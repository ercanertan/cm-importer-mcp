<div class="space-y-6">
    @if(!$this->organization)
        {{-- No Organization Warning --}}
        <flux:card>
            <div class="py-12 text-center">
                <flux:icon.building class="mx-auto size-16 text-zinc-400 dark:text-zinc-600" />
                <flux:heading size="lg" class="mt-4">No Organization Assigned</flux:heading>
                <flux:subheading class="mt-2">
                    You need to be assigned to an organization to access this dashboard.
                </flux:subheading>
                <flux:button variant="primary" class="mt-4" href="{{ route('profile.edit') }}" wire:navigate>
                    Go to Settings
                </flux:button>
            </div>
        </flux:card>
    @else
        {{-- Page Header --}}
        <flux:header>
            <flux:heading size="xl">{{ $this->organization->name }}</flux:heading>
            <flux:subheading>
                Organization Dashboard
                @if($this->organization->tier)
                    • {{ $this->organization->tier->name }} Plan
                @endif
            </flux:subheading>
        </flux:header>

        {{-- Statistics Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Team Members Stats --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Team Members</div>
                        <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                            {{ number_format($this->stats['active_members']) }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                            {{ $this->stats['admin_members'] }} {{ Str::plural('admin', $this->stats['admin_members']) }}
                        </div>
                    </div>
                    <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                        <flux:icon.users class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>

                @if($this->organization->max_users && $this->organization->max_users > 0)
                    <div class="mt-3">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-zinc-600 dark:text-zinc-400">Usage</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $this->stats['active_members'] }} / {{ $this->organization->max_users }}
                            </span>
                        </div>
                        <div class="h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            @php
                                $usage = ($this->stats['active_members'] / $this->organization->max_users) * 100;
                            @endphp
                            <div class="h-full rounded-full {{ $usage >= 90 ? 'bg-red-500' : ($usage >= 70 ? 'bg-orange-500' : 'bg-blue-500') }}"
                                 style="width: {{ min(100, $usage) }}%"></div>
                        </div>
                    </div>
                @endif
            </flux:card>

            {{-- Subscriptions Stats --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Product Subscriptions</div>
                        <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                            {{ number_format($this->stats['active_subscriptions']) }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                            {{ $this->stats['total_subscriptions'] }} total
                        </div>
                    </div>
                    <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                        <flux:icon.mail class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </flux:card>

            {{-- Activity Stats --}}
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Avg Activity Score</div>
                        <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                            {{ $this->activitySummary['avg_score_7d'] }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                            Last 7 days
                        </div>
                    </div>
                    <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                        <flux:icon.activity class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </flux:card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Recent Team Members --}}
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Recent Team Members</flux:heading>
                    <flux:button variant="ghost" size="sm" :href="route('org.team.members')" wire:navigate>
                        View All
                    </flux:button>
                </div>

                @if($this->recentMembers->count() > 0)
                    <div class="space-y-3">
                        @foreach($this->recentMembers as $member)
                            <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-200 text-sm font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ $member->initials() }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $member->fullname }}
                                        </div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ $member->email }}
                                        </div>
                                    </div>
                                </div>
                                <flux:badge :color="$member->pivot->role === 'admin' ? 'blue' : 'gray'">
                                    {{ ucfirst($member->pivot->role) }}
                                </flux:badge>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                        No team members yet
                    </div>
                @endif
            </flux:card>

            {{-- Top Active Users --}}
            <flux:card>
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">Most Active Users</flux:heading>
                    <flux:button variant="ghost" size="sm" :href="route('org.reports.activity')" wire:navigate>
                        View Report
                    </flux:button>
                </div>

                @if($this->topActiveUsers->count() > 0)
                    <div class="space-y-3">
                        @foreach($this->topActiveUsers as $user)
                            <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-200 text-sm font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                        {{ $user->initials() }}
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $user->fullname }}
                                        </div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                            Score: {{ $user->activity_score_7d }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $user->activeProductsCount }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-500">
                                        subscriptions
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                        No activity data yet
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Quick Actions --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <flux:button variant="primary" :href="route('org.team.invite')" wire:navigate>
                    <flux:icon.user-plus class="size-4" />
                    Invite Team Member
                </flux:button>

                <flux:button variant="primary" :href="route('org.products.subscriptions')" wire:navigate>
                    <flux:icon.mail class="size-4" />
                    Manage Subscriptions
                </flux:button>

                <flux:button variant="primary" :href="route('org.reports.activity')" wire:navigate>
                    <flux:icon.activity class="size-4" />
                    View Activity Report
                </flux:button>

                <flux:button variant="primary" :href="route('org.settings.index')" wire:navigate>
                    <flux:icon.settings class="size-4" />
                    Organization Settings
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
