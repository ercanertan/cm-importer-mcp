<div class="space-y-6">
    {{-- Page Header --}}
    <flux:header>
        <flux:heading size="xl">Welcome back, {{ auth()->user()->fullname }}</flux:heading>
        <flux:subheading>
            Manage your profile, subscriptions, and email preferences
        </flux:subheading>
    </flux:header>

    {{-- Statistics Grid --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Active Subscriptions --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Active Subscriptions</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $this->stats['total_subscriptions'] }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        Email products
                    </div>
                </div>
                <div class="rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                    <flux:icon.mail class="size-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        {{-- 7-Day Activity --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">7-Day Activity</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $this->stats['activity_score_7d'] }}
                    </div>
                    @php
                        $level = $this->getActivityLevel($this->stats['activity_score_7d']);
                    @endphp
                    <flux:badge size="sm" :color="$level['color']" class="mt-1">
                        {{ $level['label'] }}
                    </flux:badge>
                </div>
                <div class="rounded-full bg-green-100 p-3 dark:bg-green-900">
                    <flux:icon.activity class="size-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        {{-- 30-Day Activity --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">30-Day Activity</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $this->stats['activity_score_30d'] }}
                    </div>
                    @php
                        $level = $this->getActivityLevel($this->stats['activity_score_30d']);
                    @endphp
                    <flux:badge size="sm" :color="$level['color']" class="mt-1">
                        {{ $level['label'] }}
                    </flux:badge>
                </div>
                <div class="rounded-full bg-purple-100 p-3 dark:bg-purple-900">
                    <flux:icon.trending-up class="size-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>

        {{-- Privacy Status --}}
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Tracking Consent</div>
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 mt-1">
                        {{ $this->stats['consent_status'] ? 'Active' : 'Disabled' }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                        GDPR compliant
                    </div>
                </div>
                <div class="rounded-full bg-orange-100 p-3 dark:bg-orange-900">
                    <flux:icon.shield-check class="size-6 text-orange-600 dark:text-orange-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- My Subscriptions --}}
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">My Subscriptions</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('user.subscriptions.index')" wire:navigate>
                    Manage All
                </flux:button>
            </div>

            @if($this->activeSubscriptions->count() > 0)
                <div class="space-y-3">
                    @foreach($this->activeSubscriptions as $subscription)
                        <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                            <div class="flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $subscription->product->name }}
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    Subscribed {{ $subscription->subscribed_at->diffForHumans() }}
                                </div>
                            </div>
                            <flux:badge color="green">Active</flux:badge>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon.mail class="mx-auto size-12 text-zinc-400 dark:text-zinc-600" />
                    <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                        You're not subscribed to any products yet
                    </div>
                    <flux:button variant="primary" size="sm" class="mt-3" :href="route('user.subscriptions.index')" wire:navigate>
                        Browse Products
                    </flux:button>
                </div>
            @endif
        </flux:card>

        {{-- Available Products --}}
        <flux:card>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Available Products</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('user.subscriptions.index')" wire:navigate>
                    View All
                </flux:button>
            </div>

            @if($this->availableProducts->count() > 0)
                <div class="space-y-3">
                    @foreach($this->availableProducts as $product)
                        <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                            <div class="flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $product->name }}
                                </div>
                                @if($product->description)
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ Str::limit($product->description, 50) }}
                                    </div>
                                @endif
                            </div>
                            <flux:button variant="primary" size="sm" :href="route('user.subscriptions.index')" wire:navigate>
                                Subscribe
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    You're subscribed to all available products
                </div>
            @endif
        </flux:card>
    </div>

    {{-- Recent Activity --}}
    @if($this->recentActivity->count() > 0)
        <flux:card>
            <flux:heading size="lg" class="mb-4">Recent Subscription Activity</flux:heading>

            <div class="space-y-3">
                @foreach($this->recentActivity as $subscription)
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-3 last:border-0 dark:border-zinc-700">
                        <div class="flex items-center space-x-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $subscription->is_active ? 'bg-green-100 dark:bg-green-900' : 'bg-gray-100 dark:bg-gray-900' }}">
                                <flux:icon.mail class="size-5 {{ $subscription->is_active ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400' }}" />
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $subscription->is_active ? 'Subscribed to' : 'Unsubscribed from' }}
                                    <span class="font-semibold">{{ $subscription->product->name }}</span>
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $subscription->updated_at->diffForHumans() }}
                                </div>
                            </div>
                        </div>
                        <flux:badge :color="$subscription->is_active ? 'green' : 'gray'">
                            {{ $subscription->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- Quick Actions --}}
    <flux:card>
        <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:button variant="primary" :href="route('user.subscriptions.index')" wire:navigate>
                <flux:icon.mail class="size-4" />
                Manage Subscriptions
            </flux:button>

            <flux:button variant="primary" :href="route('user.profile.edit')" wire:navigate>
                <flux:icon.user class="size-4" />
                Edit Profile
            </flux:button>

            <flux:button variant="primary" :href="route('user.profile.consent')" wire:navigate>
                <flux:icon.shield-check class="size-4" />
                Privacy Settings
            </flux:button>

            <flux:button variant="primary" :href="route('user.activity.history')" wire:navigate>
                <flux:icon.activity class="size-4" />
                Activity History
            </flux:button>
        </div>
    </flux:card>

    {{-- Last Login Info --}}
    @if($this->stats['last_login'])
        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            Last login: {{ $this->stats['last_login']->diffForHumans() }}
        </div>
    @endif
</div>
