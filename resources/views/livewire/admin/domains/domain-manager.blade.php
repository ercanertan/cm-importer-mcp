<div class="py-12" wire:poll.{{ $pollingInterval ?? 'keep-alive' }}ms="checkSyncStatus">
    <div class="bg-white max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Domains</h2>
                    <div class="flex gap-3">
                        <button wire:click="syncAllDomains"
                                wire:loading.attr="disabled"
                                wire:target="syncAllDomains"
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm disabled:opacity-50">
                            <span wire:loading.remove wire:target="syncAllDomains">Sync All</span>
                            <span wire:loading wire:target="syncAllDomains">Syncing...</span>
                        </button>
                        <button wire:click="openCreateModal"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                            Create Domain
                        </button>
                    </div>
                </div>

                <!-- Flash Messages -->
                @if (session()->has('message'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg relative font-medium" role="alert">
                        <span class="block sm:inline">{{ session('message') }}</span>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg relative font-medium" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Sync Status Indicator -->
                @if($activeSyncLogId && $syncStatus)
                    <div class="mb-4 bg-blue-50 border-2 border-blue-400 rounded-lg p-4" wire:key="sync-status-{{ $activeSyncLogId }}">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                @if($syncStatus['status'] === 'running')
                                    <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @elseif($syncStatus['status'] === 'completed')
                                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @elseif($syncStatus['status'] === 'failed')
                                    <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900">
                                        @if($syncStatus['status'] === 'running')
                                            Syncing Domain Organizations...
                                        @elseif($syncStatus['status'] === 'completed')
                                            Sync Completed Successfully
                                        @elseif($syncStatus['status'] === 'failed')
                                            Sync Failed
                                        @endif
                                    </h4>
                                    <p class="text-xs text-gray-600">Sync Log ID: #{{ $activeSyncLogId }}</p>
                                </div>
                            </div>
                            <span class="text-lg font-bold text-blue-600">{{ $syncStatus['progress'] }}%</span>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-gray-200 rounded-full h-3 mb-3 overflow-hidden">
                            <div class="bg-blue-600 h-3 rounded-full transition-all duration-300 ease-out"
                                 style="width: {{ $syncStatus['progress'] }}%"></div>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-4 gap-3 text-center">
                            <div class="bg-white rounded-lg p-2 border border-gray-200">
                                <p class="text-xs text-gray-600 font-medium">Processed</p>
                                <p class="text-sm font-bold text-gray-900">{{ $syncStatus['processed'] ?? 0 }}/{{ $syncStatus['total'] ?? 0 }}</p>
                            </div>
                            <div class="bg-green-50 rounded-lg p-2 border border-green-200">
                                <p class="text-xs text-green-700 font-medium">Successful</p>
                                <p class="text-sm font-bold text-green-900">{{ $syncStatus['successful'] ?? 0 }}</p>
                            </div>
                            <div class="bg-red-50 rounded-lg p-2 border border-red-200">
                                <p class="text-xs text-red-700 font-medium">Failed</p>
                                <p class="text-sm font-bold text-red-900">{{ $syncStatus['failed'] ?? 0 }}</p>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-2 border border-gray-200">
                                <p class="text-xs text-gray-600 font-medium">Status</p>
                                <p class="text-sm font-bold text-gray-900 capitalize">{{ $syncStatus['status'] }}</p>
                            </div>
                        </div>

                        @if($syncStatus['status'] === 'running')
                            <p class="mt-3 text-xs text-gray-600 font-medium">
                                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Live updates will stop automatically when sync completes to save server resources.
                            </p>
                        @endif
                    </div>
                @endif

                <!-- Search -->
                <div class="mb-6">
                    <input wire:model.live="search"
                           type="text"
                           placeholder="Search domains..."
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-500">
                </div>

                <!-- Domains Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Organizations</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($domains as $domainItem)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ $domainItem->domain }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $domainItem->users_count }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @if($domainItem->organizations->count() > 0)
                                                @foreach($domainItem->organizations as $org)
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                        {{ $org->name }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-gray-600 font-medium">None</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button wire:click="syncDomain({{ $domainItem->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="syncDomain({{ $domainItem->id }})"
                                                class="text-green-700 hover:text-green-900 font-semibold mr-3 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="syncDomain({{ $domainItem->id }})">Sync</span>
                                            <span wire:loading wire:target="syncDomain({{ $domainItem->id }})">Syncing...</span>
                                        </button>
                                        <button wire:click="openAssociateModal({{ $domainItem->id }})"
                                                class="text-purple-700 hover:text-purple-900 font-semibold mr-3">
                                            Orgs
                                        </button>
                                        <button wire:click="openEditModal({{ $domainItem->id }})"
                                                class="text-indigo-700 hover:text-indigo-900 font-semibold mr-3">
                                            Edit
                                        </button>
                                        <button wire:click="confirmDelete({{ $domainItem->id }})"
                                                class="text-red-700 hover:text-red-900 font-semibold">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-700 font-medium">
                                        No domains found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $domains->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Create Modal -->
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                    <form wire:submit.prevent="createDomain">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Create Domain</h3>

                            <div class="mb-4">
                                <label for="domain" class="block text-sm font-semibold text-gray-900 mb-2">Domain *</label>
                                <input wire:model="domain" type="text" id="domain"
                                       placeholder="example.com"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2 placeholder-gray-500">
                                @error('domain') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">Enter domain without @ symbol (e.g., example.com)</p>
                            </div>

                            <div class="mb-4">
                                <label for="organization_id" class="block text-sm font-semibold text-gray-900 mb-2">Organization (Optional)</label>
                                <select wire:model="organization_id" id="organization_id"
                                        class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2">
                                    <option value="">-- Select Organization --</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </select>
                                @error('organization_id') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">System will automatically assign users with this domain to the selected organization</p>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Create
                            </button>
                            <button type="button" wire:click="closeCreateModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Edit Modal -->
    @if($showEditModal && $domainToEdit)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-50">
                    <form wire:submit.prevent="updateDomain">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Edit Domain</h3>

                            <div class="mb-4">
                                <label for="edit_domain" class="block text-sm font-semibold text-gray-900 mb-2">Domain *</label>
                                <input wire:model="domain" type="text" id="edit_domain"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2">
                                @error('domain') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Connected Organizations -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                    Connected Organizations
                                    @if(count($selectedOrganizations) > 0)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800">
                                            {{ count($selectedOrganizations) }} selected
                                        </span>
                                    @endif
                                </label>
                                <div class="space-y-2 max-h-40 overflow-y-auto border-2 border-gray-300 rounded-lg p-3 bg-gray-50">
                                    @foreach($organizations as $org)
                                        <label class="flex items-center cursor-pointer hover:bg-white p-2 rounded">
                                            <input type="checkbox"
                                                   wire:model="selectedOrganizations"
                                                   value="{{ $org->id }}"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4">
                                            <span class="ml-2 text-sm font-medium text-gray-900">{{ $org->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('selectedOrganizations') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-2 text-xs text-gray-600">
                                    <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    Select organizations to associate with this domain. Users from this domain will be assigned to these organizations.
                                </p>
                            </div>

                            <!-- Organization Statistics -->
                            @if(count($userOrganizationStats) > 0)
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                                        <svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        Organization Memberships
                                        <span class="ml-2 text-xs font-normal text-gray-600">({{ $domainToEdit->user_count }} total users)</span>
                                    </label>
                                    <div class="border-2 border-blue-300 rounded-lg p-4 bg-blue-50">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            @foreach($userOrganizationStats as $stat)
                                                <div class="flex items-center justify-between bg-white p-3 rounded-lg border-2 border-gray-200 hover:border-blue-400 transition-colors">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $stat['name'] }}</p>
                                                            <p class="text-xs text-gray-500">
                                                                @if($domainToEdit->user_count != 0)
                                                                {{ number_format(($stat['count'] / $domainToEdit->user_count) * 100, 1) }}% membership rate
                                                                @endif
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="ml-2 flex-shrink-0">
                                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold bg-blue-600 text-white shadow-sm">
                                                            {{ number_format($stat['count']) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-3 pt-3 border-t-2 border-blue-200">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <p class="text-xs font-semibold text-gray-600 mb-1">Total Users:</p>
                                                    <p class="text-lg font-bold text-blue-600">
                                                        {{ number_format($domainToEdit->user_count) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-semibold text-gray-600 mb-1">Total Memberships:</p>
                                                    <p class="text-lg font-bold text-purple-600">
                                                        {{ number_format(collect($userOrganizationStats)->sum('count')) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-600">
                                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Users with @{{ $domainToEdit->domain }} can belong to multiple organizations. Counts show organization memberships, not unique users.
                                    </p>
                                </div>
                            @endif

                            <!-- Domain's Connected Organizations Info -->
                            @if($domainToEdit->organizations->count() > 0)
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                                        <svg class="inline w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        Domain is Connected To:
                                    </label>
                                    <div class="border-2 border-green-300 rounded-lg p-3 bg-green-50">
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($domainToEdit->organizations as $connectedOrg)
                                                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-bold bg-green-600 text-white shadow-sm">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                    </svg>
                                                    {{ $connectedOrg->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <p class="mt-2 text-xs text-gray-700 font-medium">
                                            <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                            </svg>
                                            This domain is configured to work with {{ $domainToEdit->organizations->count() }} organization{{ $domainToEdit->organizations->count() != 1 ? 's' : '' }}
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <!-- Users Table -->
                            @if(count($domainUsers) > 0)
                                <div class="mb-4">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                                        Associated Users ({{ count($domainUsers) }}{{ count($domainUsers) >= 100 ? '+' : '' }})
                                        @if(count($userOrganizationStats) > 1)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800">
                                                Across {{ count($userOrganizationStats) }} organizations
                                            </span>
                                        @endif
                                    </label>
                                    <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
                                        <div class="max-h-80 overflow-y-auto">
                                            <table class="w-full divide-y divide-gray-300">
                                                <thead class="bg-gray-100 sticky top-0">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-900 uppercase">Name</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-900 uppercase">Email</th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-900 uppercase">
                                                            Organization{{ count($userOrganizationStats) > 1 ? 's' : '' }}
                                                        </th>
                                                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-900 uppercase">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-200">
                                                    @php
                                                        $currentOrgId = null;
                                                    @endphp
                                                    @foreach($domainUsers as $user)
                                                        @php
                                                            $userOrgId = $user['organization_id'] ?? null;
                                                        @endphp

                                                        {{-- Organization group header --}}
                                                        @if($currentOrgId !== $userOrgId && count($userOrganizationStats) > 1)
                                                            @php
                                                                $currentOrgId = $userOrgId;
                                                                $orgName = $user['organization']['name'] ?? 'No Organization';
                                                                $orgCount = collect($userOrganizationStats)->firstWhere('id', $userOrgId)['count'] ?? 0;
                                                            @endphp
                                                            <tr class="bg-gradient-to-r from-blue-50 to-blue-100 border-t-2 border-blue-300">
                                                                <td colspan="4" class="px-4 py-2">
                                                                    <div class="flex items-center justify-between">
                                                                        <div class="flex items-center space-x-2">
                                                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                                            </svg>
                                                                            <span class="text-sm font-bold text-gray-900">{{ $orgName }}</span>
                                                                        </div>
                                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-600 text-white">
                                                                            {{ $orgCount }} user{{ $orgCount != 1 ? 's' : '' }}
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endif

                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $user['fullname'] ?? 'N/A' }}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-700">{{ $user['email'] }}</td>
                                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                                @if(isset($user['organizations']) && count($user['organizations']) > 0)
                                                                    <div class="flex flex-wrap gap-1">
                                                                        @foreach($user['organizations'] as $userOrg)
                                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                                                {{ $userOrg['name'] }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                @elseif(isset($user['organization']['name']))
                                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                                        {{ $user['organization']['name'] }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-gray-400">None</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-sm">
                                                                <!-- Actions will be added here later -->
                                                                <span class="text-gray-400 text-xs">Actions</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @if(count($domainUsers) >= 100)
                                        <p class="mt-2 text-xs text-gray-600 font-medium">
                                            Showing first 100 users. Total user count: {{ $domainToEdit->user_count }}
                                        </p>
                                    @endif
                                </div>
                            @elseif(count($userOrganizationStats) == 0)
                                <div class="mb-4 p-4 bg-gray-50 border-2 border-gray-300 rounded-lg text-center">
                                    <p class="text-sm text-gray-600 font-medium">No users associated with this domain yet.</p>
                                </div>
                            @endif
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update
                            </button>
                            <button type="button" wire:click="closeEditModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Associate Organizations Modal -->
    @if($showAssociateModal && $domainToAssociate)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                    <form wire:submit.prevent="updateAssociations">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">
                                Manage Organizations for <span class="text-blue-600 bg-blue-50 px-2 py-1 rounded">{{ $domainToAssociate->domain }}</span>
                            </h3>

                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Select Organizations</label>
                                <div class="space-y-2 max-h-60 overflow-y-auto border-2 border-gray-300 rounded-lg p-3 bg-gray-50">
                                    @foreach($organizations as $org)
                                        <label class="flex items-center cursor-pointer hover:bg-white p-2 rounded">
                                            <input type="checkbox"
                                                   wire:model="selectedOrganizations"
                                                   value="{{ $org->id }}"
                                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4">
                                            <span class="ml-2 text-sm font-medium text-gray-900">{{ $org->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-gray-600 font-medium">
                                    Users with <span class="font-bold text-blue-600">{{ $domainToAssociate->domain }}</span> email will be assigned to selected organizations
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update & Sync
                            </button>
                            <button type="button" wire:click="closeAssociateModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $domainToDelete)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Domain</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete "{{ $domainToDelete->domain }}"?
                                        This will unlink {{ $domainToDelete->users_count }} users from this domain.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="deleteDomain" type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button wire:click="cancelDelete" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
