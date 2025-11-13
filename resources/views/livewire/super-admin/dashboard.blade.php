<div class="space-y-6">
    {{-- Page Header --}}
    <flux:header>
        <flux:heading size="xl">Super Admin Dashboard</flux:heading>
        <flux:subheading>
            Platform-wide overview and management
        </flux:subheading>
    </flux:header>

    {{-- Statistics Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Organizations Stats --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Organizations</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ number_format($this->stats['total_organizations']) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        {{ $this->stats['active_organizations'] }} active
                    </div>
                </div>
                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                    <flux:icon.building class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        {{-- Users Stats --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Total Users</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ number_format($this->stats['total_users']) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        {{ $this->stats['super_admins'] }} admins
                    </div>
                </div>
                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                    <flux:icon.users class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        {{-- Tiers Stats --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Tiers</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ number_format($this->stats['total_tiers']) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        {{ $this->stats['active_tiers'] }} active
                    </div>
                </div>
                <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                    <flux:icon.layers class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>

        {{-- Products Stats --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Products</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ number_format($this->stats['total_products']) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        {{ $this->stats['active_products'] }} active
                    </div>
                </div>
                <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                    <flux:icon.package class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Recent Organizations --}}
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Recent Organizations</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('super-admin.organizations.index')" wire:navigate>
                    View All
                </flux:button>
            </div>

            @if($this->recentOrganizations->count() > 0)
                <div class="space-y-3">
                    @foreach($this->recentOrganizations as $org)
                        <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                            <div class="flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $org->name }}
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    @if($org->tier)
                                        {{ $org->tier->name }} •
                                    @endif
                                    {{ $org->activeUsersCount }} users
                                </div>
                            </div>
                            <flux:badge :color="$org->is_active ? 'green' : 'gray'">
                                {{ $org->is_active ? 'Active' : 'Inactive' }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    No organizations yet
                </div>
            @endif
        </flux:card>

        {{-- Recent Users --}}
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Recent Users</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('super-admin.users.index')" wire:navigate>
                    View All
                </flux:button>
            </div>

            @if($this->recentUsers->count() > 0)
                <div class="space-y-3">
                    @foreach($this->recentUsers as $user)
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
                                        {{ $user->email }}
                                    </div>
                                </div>
                            </div>
                            <flux:badge :color="match($user->role) {
                                'super_admin' => 'red',
                                'org_admin' => 'blue',
                                default => 'gray'
                            }">
                                {{ str_replace('_', ' ', ucfirst($user->role)) }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    No users yet
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Tier Distribution --}}
    @if($this->tierDistribution->count() > 0)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Tier Distribution</flux:heading>

            <div class="space-y-4">
                @foreach($this->tierDistribution as $tier)
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <div>
                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $tier->name }}</span>
                                <span class="text-sm text-zinc-600 dark:text-zinc-400 ml-2">
                                    ({{ $tier->organizations_count }} {{ Str::plural('organization', $tier->organizations_count) }})
                                </span>
                            </div>
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                ${{ number_format($tier->price, 2) }}
                            </span>
                        </div>
                        <div class="h-2 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            @php
                                $percentage = $this->stats['total_organizations'] > 0
                                    ? ($tier->organizations_count / $this->stats['total_organizations'] * 100)
                                    : 0;
                            @endphp
                            <div class="h-full bg-blue-500 rounded-full" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- Quick Actions --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:button variant="primary" :href="route('super-admin.cdp.tiers.index')" wire:navigate>
                <flux:icon.layers class="size-4" />
                Manage Tiers
            </flux:button>

            <flux:button variant="primary" :href="route('super-admin.cdp.products.index')" wire:navigate>
                <flux:icon.package class="size-4" />
                Manage Products
            </flux:button>

            <flux:button variant="primary" :href="route('super-admin.organizations.index')" wire:navigate>
                <flux:icon.building class="size-4" />
                Manage Organizations
            </flux:button>

            <flux:button variant="primary" :href="route('super-admin.users.index')" wire:navigate>
                <flux:icon.users class="size-4" />
                Manage Users
            </flux:button>
        </div>
    </flux:card>
</div>
