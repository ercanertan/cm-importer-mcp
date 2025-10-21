<div class="py-12">
    <div class="bg-white max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Users</h2>
                </div>

                <!-- Flash Messages -->
                @if (session()->has('message'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg relative font-medium" role="alert">
                        <span class="block sm:inline">{!! session('message') !!}</span>
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg relative font-medium" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Search -->
                <div class="mb-6">
                    <input wire:model.live="search"
                           type="text"
                           placeholder="Search by name or email..."
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-500">
                </div>

                <!-- Users Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Organizations</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ $user->fullname }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">{{ $user->email }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($user->domain)
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                                {{ $user->domain->domain }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @if($user->organizations->count() > 0)
                                                @foreach($user->organizations as $org)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                        {{ $org->name }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-gray-400 font-medium">None</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button wire:click="openManageModal({{ $user->id }})"
                                                class="text-indigo-700 hover:text-indigo-900 font-semibold mr-4">
                                            Manage Orgs
                                        </button>
                                        @if($user->organizations_count > 1)
                                            <button wire:click="openPrimaryOrgModal({{ $user->id }})"
                                                    class="text-blue-700 hover:text-blue-900 font-semibold mr-4">
                                                Primary Org
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.users.custom-fields.edit', $user->id) }}"
                                           wire:navigate
                                           class="text-purple-700 hover:text-purple-900 font-semibold">
                                            Custom Fields
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-700 font-medium">
                                        No users found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Organizations Modal -->
    @if($showManageModal && $userToManage)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative z-50">
                    <form wire:submit.prevent="updateOrganizations">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">
                                Manage Organizations for {{ $userToManage->fullname }}
                            </h3>

                            <!-- User Info -->
                            <div class="mb-4 p-3 bg-gray-50 rounded-lg border-2 border-gray-200">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="font-semibold text-gray-600">Email:</span>
                                        <span class="text-gray-900">{{ $userToManage->email }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-gray-600">Domain:</span>
                                        @if($userToManage->domain)
                                            <span class="text-gray-900">{{ $userToManage->domain->domain }}</span>
                                        @else
                                            <span class="text-gray-400">No domain</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Organizations Selection -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                    Select Organizations
                                    @if(count($selectedOrganizations) > 0)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                            {{ count($selectedOrganizations) }} selected
                                        </span>
                                    @endif
                                </label>
                                <div class="space-y-2 max-h-80 overflow-y-auto border-2 border-gray-300 rounded-lg p-3 bg-gray-50">
                                    @foreach($organizations as $org)
                                        @php
                                            $isAutoAssigned = in_array($org->id, $autoAssignedOrgs);
                                            $isManuallyAssigned = in_array($org->id, $manuallyAssignedOrgs);
                                            $isChecked = in_array($org->id, $selectedOrganizations);
                                        @endphp
                                        <label class="flex items-center justify-between cursor-pointer hover:bg-white p-2 rounded">
                                            <div class="flex items-center">
                                                <input type="checkbox"
                                                       wire:model="selectedOrganizations"
                                                       value="{{ $org->id }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4">
                                                <span class="ml-2 text-sm font-medium text-gray-900">{{ $org->name }}</span>
                                            </div>
                                            @if($isAutoAssigned)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                    🔵 Auto-assigned
                                                </span>
                                            @elseif($isManuallyAssigned)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-900 border border-green-300">
                                                    🟢 Manual
                                                </span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-gray-600">
                                    <strong>🔵 Auto-assigned:</strong> From user's email domain. Will be re-synced during domain operations.<br>
                                    <strong>🟢 Manual:</strong> Added manually by admin. Preserved during domain syncs.
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update Organizations
                            </button>
                            <button type="button" wire:click="closeManageModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Primary Organization Modal -->
    @if($showPrimaryOrgModal && $userToManage)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative z-50">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg leading-6 font-bold text-gray-900">
                                Set Primary Organization for {{ $userToManage->fullname }}
                            </h3>
                            <button wire:click="closePrimaryOrgModal" class="text-gray-400 hover:text-gray-600">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Flash Messages -->
                        @if (session()->has('message'))
                            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-md">
                                {{ session('message') }}
                            </div>
                        @endif

                        @if (session()->has('error'))
                            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-md">
                                {{ session('error') }}
                            </div>
                        @endif

                        <!-- User Info -->
                        <div class="mb-4 p-3 bg-gray-50 rounded-lg border-2 border-gray-200">
                            <div class="text-sm">
                                <span class="font-semibold text-gray-600">Email:</span>
                                <span class="text-gray-900">{{ $userToManage->email }}</span>
                            </div>
                        </div>

                        @if(count($userOrganizations) > 0)
                            <div class="space-y-3">
                                <p class="text-sm text-gray-600 mb-4">
                                    Select the primary organization for this user. This will be used as their default organization.
                                </p>

                                @foreach($userOrganizations as $org)
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-colors
                                        {{ $org['is_primary'] ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                                        <input
                                            type="radio"
                                            wire:model.live="primaryOrganizationId"
                                            wire:change="setPrimary({{ $org['id'] }})"
                                            value="{{ $org['id'] }}"
                                            class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                        <div class="ml-3 flex-1">
                                            <span class="block text-sm font-medium text-gray-900">
                                                {{ $org['name'] }}
                                            </span>
                                            <div class="flex items-center mt-1 space-x-2">
                                                @if($org['is_primary'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        Primary
                                                    </span>
                                                @endif
                                                @if($org['is_manual'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        Manual
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <p class="mt-4 text-sm text-gray-500">This user doesn't belong to any organizations yet.</p>
                                <p class="mt-2 text-xs text-gray-400">Assign organizations first using "Manage Orgs".</p>
                            </div>
                        @endif
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="closePrimaryOrgModal"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
