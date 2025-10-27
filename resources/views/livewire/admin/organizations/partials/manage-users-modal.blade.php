<!-- Manage Users Modal -->
<div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-on:keydown.escape.window="$wire.closeManageUsersModal()">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true" wire:click="closeManageUsersModal"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-50" x-on:click.stop>
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-bold text-gray-900">
                        Manage Users for {{ $this->organizationToManage->name }}
                    </h3>
                    <button type="button" wire:click="closeManageUsersModal"
                            class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Organization Info -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg border-2 border-gray-200">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="font-semibold text-gray-600">Description:</span>
                            <span class="text-gray-900">{{ $this->organizationToManage->description ?: 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="font-semibold text-gray-600">Status:</span>
                            @if($this->organizationToManage->is_active)
                                <span class="px-2 py-1 inline-flex text-xs font-bold rounded-full bg-green-100 text-green-900 border border-green-300">
                                    Active
                                </span>
                            @else
                                <span class="px-2 py-1 inline-flex text-xs font-bold rounded-full bg-gray-200 text-gray-900 border border-gray-400">
                                    Inactive
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Currently Assigned Users Table -->
                <div class="mb-6">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-semibold text-gray-900">
                            Currently Assigned Users
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-800">
                                {{ $this->totalAssignedUsers }} total
                            </span>
                        </label>
                    </div>

                    @if($this->totalAssignedUsers > 0)
                        <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
                            <div class="max-h-64 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-100 sticky top-0">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">User</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Email</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Domain</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Type</th>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($this->assignedUsers as $user)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    {{ $user->fullname ?: 'N/A' }}
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-600">
                                                    {{ $user->email }}
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-600">
                                                    @if($user->domain)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                            {{ $user->domain->domain }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-400">N/A</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-700">
                                                    {{ $user->cm_status }}
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    @if($user->pivot->is_manual)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-900 border border-green-300">
                                                            🟢 Manual
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                            🔵 Auto
                                                        </span>
                                                    @endif
                                                </td>
                                                
                                                <td class="px-3 py-2 text-sm">
                                                    <button type="button"
                                                            wire:click="detachUser({{ $user->id }})"
                                                            wire:confirm="Are you sure you want to remove this user?"
                                                            class="text-red-700 hover:text-red-900 font-semibold">
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Load More Button for Assigned Users -->
                        @if($this->hasMoreAssignedUsers)
                            <div class="mt-3 text-center">
                                <button type="button" wire:click="loadMoreAssignedUsers"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Load More
                                    <span class="ml-2 text-xs text-gray-500">
                                        (Showing {{ is_array($this->assignedUsers) ? count($this->assignedUsers) : count($this->assignedUsers) }} of {{ $this->totalAssignedUsers }})
                                    </span>
                                </button>
                            </div>
                        @else
                            @php
                                $assignedCount = is_array($this->assignedUsers) ? count($this->assignedUsers) : count($this->assignedUsers);
                            @endphp
                            @if($assignedCount > 0)
                                <div class="mt-2 text-center text-xs text-gray-500">
                                    Showing all {{ $this->totalAssignedUsers }} assigned users
                                </div>
                            @endif
                        @endif

                        <p class="mt-2 text-xs text-gray-600">
                            <strong>🔵 Auto:</strong> Assigned via domain sync. Will be re-synced during domain operations.<br>
                            <strong>🟢 Manual:</strong> Added manually by admin. Preserved during domain syncs.
                        </p>
                    @else
                        <div class="border-2 border-gray-300 rounded-lg p-6 text-center text-gray-500 bg-gray-50">
                            No users currently assigned to this organization.
                        </div>
                    @endif
                </div>

                <hr class="my-6 border-gray-300">

                <!-- Search and Add Users Section -->
                <h4 class="text-md font-bold text-gray-900 mb-4">Search and Add Users</h4>

                <!-- Search Users -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-900 mb-2">
                        Search All Users
                    </label>
                    <input wire:model.live="userSearch"
                           type="text"
                           placeholder="Search by name or email to add users..."
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-500">
                </div>

                <!-- Users List with Add Buttons -->
                <div class="mb-4">
                    <div class="border-2 border-gray-300 rounded-lg overflow-hidden">
                        <div class="max-h-96 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">User</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Email</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Domain</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($this->filteredUsers as $user)
                                        @php
                                            // Check if user is already assigned to THIS organization
                                            $userOrg = $user->organizations->firstWhere('id', $this->organizationToManage->id);
                                            $isAssigned = $userOrg !== null;
                                            $isManual = $isAssigned ? $userOrg->pivot->is_manual : false;
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $user->fullname ?: 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-600 truncate max-w-xs">
                                                {{ $user->email }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-600">
                                                @if($user->domain)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        {{ $user->domain->domain }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                @if($isAssigned)
                                                    @if($isManual)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-900 border border-green-300">
                                                            🟢 Manual
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                            🔵 Auto
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                @if($isAssigned)
                                                    <button type="button"
                                                            wire:click="detachUser({{ $user->id }})"
                                                            wire:confirm="Remove this user?"
                                                            class="text-red-700 hover:text-red-900 font-semibold">
                                                        Remove
                                                    </button>
                                                @else
                                                    <button type="button"
                                                            wire:click="attachUser({{ $user->id }}, true)"
                                                            class="text-green-700 hover:text-green-900 font-semibold">
                                                        + Add
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                                                @if($userSearch)
                                                    No users found matching "{{ $userSearch }}"
                                                @else
                                                    Start typing to search for users
                                                @endif
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Load More Button -->
                    @if($this->hasMoreUsers)
                        <div class="mt-3 text-center">
                            <button type="button" wire:click="loadMoreUsers"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Load More Users
                                <span class="ml-2 text-xs text-gray-500">
                                    (Showing {{ is_array($this->filteredUsers) ? count($this->filteredUsers) : $this->filteredUsers->count() }} of {{ $this->totalUsersCount }})
                                </span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" wire:click="closeManageUsersModal"
                        class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
