<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('user.dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('user.dashboard')" :current="request()->routeIs('user.dashboard')" wire:navigate>
                    Dashboard
                </flux:navlist.item>

                <flux:navlist.group heading="My Profile" expandable :expanded="request()->routeIs('user.profile.*')">
                    <flux:navlist.item icon="user" :href="route('user.profile.edit')" :current="request()->routeIs('user.profile.edit')" wire:navigate>
                        Edit Profile
                    </flux:navlist.item>
                    <flux:navlist.item icon="shield-check" :href="route('user.profile.consent')" :current="request()->routeIs('user.profile.consent')" wire:navigate>
                        Privacy & Consent
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Email Subscriptions" expandable :expanded="request()->routeIs('user.subscriptions.*')">
                    <flux:navlist.item icon="mail" :href="route('user.subscriptions.index')" :current="request()->routeIs('user.subscriptions.index')" wire:navigate>
                        Manage Subscriptions
                    </flux:navlist.item>
                    <flux:navlist.item icon="settings" :href="route('user.subscriptions.preferences')" :current="request()->routeIs('user.subscriptions.preferences')" wire:navigate>
                        Preferences
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Activity" expandable :expanded="request()->routeIs('user.activity.*')">
                    <flux:navlist.item icon="activity" :href="route('user.activity.history')" :current="request()->routeIs('user.activity.history')" wire:navigate>
                        Email Activity
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            @if(auth()->user()->primaryOrganization)
                <div class="mx-2 mb-4 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1">Organization</div>
                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ auth()->user()->primaryOrganization->name }}
                    </div>
                </div>
            @endif

            <!-- User Activity Summary -->
            <div class="mx-2 mb-4 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-2">Activity Score</div>
                <div class="space-y-2">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-zinc-600 dark:text-zinc-400">Last 7 days</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->activity_score_7d }}</span>
                        </div>
                        <div class="h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: {{ min(100, auth()->user()->activity_score_7d) }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-zinc-600 dark:text-zinc-400">Last 30 days</span>
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ auth()->user()->activity_score_30d }}</span>
                        </div>
                        <div class="h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ min(100, auth()->user()->activity_score_30d) }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->fullname"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->fullname }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Log Out
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->fullname }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Log Out
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
