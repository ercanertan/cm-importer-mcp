<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Quick access to main features</p>
        </div>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- Campaign Monitor Import -->
            <a href="{{ route('campaign-monitor.import') }}" wire:navigate
               class="block p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Campaign Monitor Import</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Import CSV files from Campaign Monitor</p>
                    </div>
                </div>
            </a>

            <!-- Organizations -->
            <a href="{{ route('admin.organizations.index') }}" wire:navigate
               class="block p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Organizations</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Manage organizations and associations</p>
                    </div>
                </div>
            </a>

            <!-- Domains -->
            <a href="{{ route('admin.domains.index') }}" wire:navigate
               class="block p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl hover:shadow-lg transition-shadow">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Domains</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Manage email domains and auto-assignment</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Quick Stats -->
        <div class="grid gap-6 md:grid-cols-3">
            <div class="p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Users</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ \App\Models\User::count() }}</div>
            </div>

            <div class="p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Organizations</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ \App\Models\Organization::count() }}</div>
            </div>

            <div class="p-6 bg-white dark:bg-zinc-800 border border-neutral-200 dark:border-neutral-700 rounded-xl">
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Domains</div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ \App\Models\Domain::count() }}</div>
            </div>
        </div>
    </div>
</x-layouts.app>
