<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('org.dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">ORG ADMIN</span>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('org.dashboard')" :current="request()->routeIs('org.dashboard')" wire:navigate>
                    Dashboard
                </flux:navlist.item>

                <flux:navlist.group heading="Team" expandable :expanded="request()->routeIs('org.team.*')">
                    <flux:navlist.item icon="users" :href="route('org.team.members')" :current="request()->routeIs('org.team.members')" wire:navigate>
                        Team Members
                    </flux:navlist.item>
                    <flux:navlist.item icon="user-plus" :href="route('org.team.invite')" :current="request()->routeIs('org.team.invite')" wire:navigate>
                        Invite Users
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Products" expandable :expanded="request()->routeIs('org.products.*')">
                    <flux:navlist.item icon="package" :href="route('org.products.subscriptions')" :current="request()->routeIs('org.products.subscriptions')" wire:navigate>
                        Subscriptions
                    </flux:navlist.item>
                    <flux:navlist.item icon="users-cog" :href="route('org.products.bulk-subscribe')" :current="request()->routeIs('org.products.bulk-subscribe')" wire:navigate>
                        Bulk Subscribe
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Reports" expandable :expanded="request()->routeIs('org.reports.*')">
                    <flux:navlist.item icon="activity" :href="route('org.reports.activity')" :current="request()->routeIs('org.reports.activity')" wire:navigate>
                        Activity Report
                    </flux:navlist.item>
                    <flux:navlist.item icon="trending-up" :href="route('org.reports.engagement')" :current="request()->routeIs('org.reports.engagement')" wire:navigate>
                        Engagement Metrics
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Settings" expandable :expanded="request()->routeIs('org.settings.*')">
                    <flux:navlist.item icon="building" :href="route('org.settings.index')" :current="request()->routeIs('org.settings.index')" wire:navigate>
                        Organization Settings
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
                    @if(auth()->user()->primaryOrganization->tier)
                        <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                            {{ auth()->user()->primaryOrganization->tier->name }} Plan
                        </div>
                    @endif
                </div>
            @endif

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
