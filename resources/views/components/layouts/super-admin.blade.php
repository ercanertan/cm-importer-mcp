<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('super-admin.dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">SUPER ADMIN</span>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('super-admin.dashboard')" :current="request()->routeIs('super-admin.dashboard')" wire:navigate>
                    Dashboard
                </flux:navlist.item>

                <flux:navlist.group heading="CDP Management" expandable :expanded="request()->routeIs('super-admin.cdp.*')">
                    <flux:navlist.item icon="layers" :href="route('super-admin.cdp.tiers.index')" :current="request()->routeIs('super-admin.cdp.tiers.*')" wire:navigate>
                        Tiers
                    </flux:navlist.item>
                    <flux:navlist.item icon="package" :href="route('super-admin.cdp.products.index')" :current="request()->routeIs('super-admin.cdp.products.*')" wire:navigate>
                        Products
                    </flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('super-admin.cdp.segments.index')" :current="request()->routeIs('super-admin.cdp.segments.*')" wire:navigate>
                        Segments
                    </flux:navlist.item>
                    <flux:navlist.item icon="mail" :href="route('super-admin.cdp.campaigns.index')" :current="request()->routeIs('super-admin.cdp.campaigns.*')" wire:navigate>
                        Campaigns
                    </flux:navlist.item>
                    <flux:navlist.item icon="calendar" :href="route('super-admin.cdp.templates.index')" :current="request()->routeIs('super-admin.cdp.templates.*')" wire:navigate>
                        Templates
                    </flux:navlist.item>
                    <flux:navlist.item icon="zap" :href="route('super-admin.cdp.sync-monitor')" :current="request()->routeIs('super-admin.cdp.sync-monitor')" wire:navigate>
                        Sync Monitor
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Platform Management" expandable :expanded="request()->routeIs('super-admin.organizations.*', 'super-admin.domains.*', 'super-admin.users.*')">
                    <flux:navlist.item icon="building" :href="route('super-admin.organizations.index')" :current="request()->routeIs('super-admin.organizations.*')" wire:navigate>
                        Organizations
                    </flux:navlist.item>
                    <flux:navlist.item icon="globe" :href="route('super-admin.domains.index')" :current="request()->routeIs('super-admin.domains.*')" wire:navigate>
                        Domains
                    </flux:navlist.item>
                    <flux:navlist.item icon="users" :href="route('super-admin.users.index')" :current="request()->routeIs('super-admin.users.*')" wire:navigate>
                        Users
                    </flux:navlist.item>
                </flux:navlist.group>

                <flux:navlist.group heading="Settings" expandable :expanded="request()->routeIs('super-admin.settings.*')">
                    <flux:navlist.item icon="sliders" :href="route('super-admin.settings.custom-fields')" :current="request()->routeIs('super-admin.settings.custom-fields')" wire:navigate>
                        Custom Fields
                    </flux:navlist.item>
                    <flux:navlist.item icon="database" :href="route('super-admin.settings.sync-logs')" :current="request()->routeIs('super-admin.settings.sync-logs')" wire:navigate>
                        Sync Logs
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

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
