<div class="py-12" @if($pollingInterval) wire:poll.{{ $pollingInterval }}ms="checkSyncStatus" @endif>
    <div class="bg-white max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Organizations</h2>
                    <div class="flex gap-3">
                        <button wire:click="openAdvancedCreateModal"
                                class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                            Advanced Create
                        </button>
                        <button wire:click="openCreateModal"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                            Create Organization
                        </button>
                    </div>
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
                           placeholder="Search organizations..."
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 placeholder-gray-500">
                </div>

                <!-- Organizations Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Domains</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($organizations as $organization)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">{{ $organization->name }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-700">{{ Str::limit($organization->description, 50) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="openManageUsersModal({{ $organization->id }})"
                                                class="text-sm font-medium text-blue-700 hover:text-blue-900 underline">
                                            {{ $organization->users_count }} users
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $organization->domains_count }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <button wire:click="toggleActive({{ $organization->id }})"
                                                class="text-sm">
                                            @if($organization->is_active)
                                                <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-green-100 text-green-900 border border-green-300">
                                                    Active
                                                </span>
                                            @else
                                                <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-gray-200 text-gray-900 border border-gray-400">
                                                    Inactive
                                                </span>
                                            @endif
                                        </button>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        @if($organization->conditional_rules)
                                            <button wire:click="openAdvancedEditModal({{ $organization->id }})"
                                                    class="text-purple-700 hover:text-purple-900 font-semibold mr-4">
                                                Edit Advanced
                                            </button>
                                        @else
                                            <button wire:click="openEditModal({{ $organization->id }})"
                                                    class="text-indigo-700 hover:text-indigo-900 font-semibold mr-4">
                                                Edit
                                            </button>
                                        @endif
                                        <button wire:click="confirmDelete({{ $organization->id }})"
                                                class="text-red-700 hover:text-red-900 font-semibold">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-700 font-medium">
                                        No organizations found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $organizations->links() }}
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
                    <form wire:submit.prevent="createOrganization">
                        <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Create Organization</h3>

                            <div class="mb-4">
                                <label for="name" class="block text-sm font-semibold text-gray-900 mb-2">Name *</label>
                                <input wire:model="name" type="text" id="name"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2">
                                @error('name') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="description" class="block text-sm font-semibold text-gray-900 mb-2">Description</label>
                                <textarea wire:model="description" id="description" rows="3"
                                          class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2"></textarea>
                                @error('description') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center cursor-pointer">
                                    <input wire:model="is_active" type="checkbox"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4">
                                    <span class="ml-2 text-sm font-medium text-gray-900">Active</span>
                                </label>
                            </div>

                            <!-- Domains Section -->
                            <div class="mb-4 pt-4 border-t-2 border-gray-200">
                                <label for="domains" class="block text-sm font-semibold text-gray-900 mb-2">Associate Domains (Optional)</label>
                                <input wire:model="domains" type="text" id="domains"
                                       placeholder="example.com, company.com, organization.org"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2 placeholder-gray-500">
                                @error('domains') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">Enter domain names separated by commas (e.g., example.com, company.com)</p>
                            </div>

                            <div class="mb-4">
                                <label class="flex items-start cursor-pointer">
                                    <input wire:model="syncUsers" type="checkbox"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4 mt-0.5">
                                    <div class="ml-2">
                                        <span class="text-sm font-medium text-gray-900">Sync Existing Users</span>
                                        <p class="text-xs text-gray-600 mt-1">Automatically assign existing users with these domain emails to this organization</p>
                                    </div>
                                </label>
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

    <!-- Advanced Create Modal -->
    @if($showAdvancedCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-50">
                    <form wire:submit.prevent="createAdvancedOrganization">
                        <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[85vh] overflow-y-auto">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Advanced Organization Creation</h3>
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full border border-purple-300">
                                    Conditional Assignment
                                </span>
                            </div>

                            <div class="mb-4 p-3 bg-blue-50 border-2 border-blue-200 rounded-lg">
                                <p class="text-sm text-blue-900">
                                    <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    <strong>Advanced Mode:</strong> Create an organization with conditional user assignment based on custom field values. Users matching the conditions will be automatically assigned.
                                </p>
                            </div>

                            <!-- Basic Info -->
                            <div class="mb-4">
                                <label for="adv_name" class="block text-sm font-semibold text-gray-900 mb-2">Organization Name *</label>
                                <input wire:model="name" type="text" id="adv_name"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2">
                                @error('name') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="adv_description" class="block text-sm font-semibold text-gray-900 mb-2">Description</label>
                                <textarea wire:model="description" id="adv_description" rows="2"
                                          class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2"></textarea>
                                @error('description') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center cursor-pointer">
                                    <input wire:model="is_active" type="checkbox"
                                           class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50 w-4 h-4">
                                    <span class="ml-2 text-sm font-medium text-gray-900">Active</span>
                                </label>
                            </div>

                            <!-- Domains Section -->
                            <div class="mb-4 pt-4 border-t-2 border-gray-200">
                                <label for="adv_domains" class="block text-sm font-semibold text-gray-900 mb-2">Domains *</label>
                                <input wire:model="domains" type="text" id="adv_domains"
                                       placeholder="example.com, company.com"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2 placeholder-gray-500">
                                @error('domains') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">Users from these domains will be evaluated against the conditions below</p>
                            </div>

                            <!-- Conditional Logic Builder -->
                            <div class="mb-4 pt-4 border-t-2 border-purple-200">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-semibold text-gray-900">
                                        <svg class="inline w-5 h-5 mr-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                        Conditional Rules *
                                    </label>
                                    <button type="button" wire:click="addCondition"
                                            class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold py-1 px-3 rounded">
                                        + Add Condition
                                    </button>
                                </div>

                                <!-- Condition Logic Selector -->
                                <div class="mb-3 flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border-2 border-gray-200">
                                    <span class="text-sm font-semibold text-gray-700">Match:</span>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" wire:model="conditionLogic" value="AND"
                                               class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                                        <span class="ml-2 text-sm font-medium text-gray-900">ALL conditions (AND)</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" wire:model="conditionLogic" value="OR"
                                               class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                                        <span class="ml-2 text-sm font-medium text-gray-900">ANY condition (OR)</span>
                                    </label>
                                </div>

                                <!-- Conditions List -->
                                <div class="space-y-3">
                                    @forelse($conditions as $index => $condition)
                                        <div class="border-2 border-purple-200 rounded-lg p-3 bg-purple-50" wire:key="condition-{{ $index }}">
                                            <div class="flex items-start space-x-2">
                                                <div class="flex-1 grid grid-cols-3 gap-2">
                                                    <!-- Custom Field -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Custom Field</label>
                                                        <select wire:model="conditions.{{ $index }}.field_id"
                                                                class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5">
                                                            <option value="">Select field...</option>
                                                            @foreach($this->availableCustomFields as $field)
                                                                <option value="{{ $field->id }}">{{ $field->field_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('conditions.' . $index . '.field_id')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    <!-- Operator -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Operator</label>
                                                        <select wire:model="conditions.{{ $index }}.operator"
                                                                class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5">
                                                            <option value="equals">Equals</option>
                                                            <option value="not_equals">Not Equals</option>
                                                            <option value="contains">Contains</option>
                                                            <option value="not_contains">Not Contains</option>
                                                            <option value="starts_with">Starts With</option>
                                                            <option value="ends_with">Ends With</option>
                                                            <option value="is_empty">Is Empty</option>
                                                            <option value="is_not_empty">Is Not Empty</option>
                                                        </select>
                                                        @error('conditions.' . $index . '.operator')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    <!-- Value -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Value</label>
                                                        <input type="text" wire:model="conditions.{{ $index }}.value"
                                                               placeholder="Value to compare..."
                                                               class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5"
                                                               @if(in_array($conditions[$index]['operator'] ?? '', ['is_empty', 'is_not_empty'])) disabled @endif>
                                                        @error('conditions.' . $index . '.value')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <!-- Remove Button -->
                                                <button type="button" wire:click="removeCondition({{ $index }})"
                                                        class="mt-6 text-red-600 hover:text-red-800">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center p-4 bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg">
                                            <p class="text-sm text-gray-600 font-medium">No conditions added yet. Click "Add Condition" to start.</p>
                                        </div>
                                    @endforelse
                                </div>
                                @error('conditions') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Create with Conditions
                            </button>
                            <button type="button" wire:click="closeAdvancedCreateModal"
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
    @if($showEditModal && $organizationToEdit)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full relative z-50">
                    <form wire:submit.prevent="updateOrganization">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Edit Organization</h3>

                            <div class="mb-4">
                                <label for="edit_name" class="block text-sm font-semibold text-gray-900 mb-2">Name *</label>
                                <input wire:model="name" type="text" id="edit_name"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2">
                                @error('name') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="edit_description" class="block text-sm font-semibold text-gray-900 mb-2">Description</label>
                                <textarea wire:model="description" id="edit_description" rows="3"
                                          class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2"></textarea>
                                @error('description') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center cursor-pointer">
                                    <input wire:model="is_active" type="checkbox"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4">
                                    <span class="ml-2 text-sm font-medium text-gray-900">Active</span>
                                </label>
                            </div>

                            <!-- Domains Section -->
                            <div class="mb-4 pt-4 border-t-2 border-gray-200">
                                <label for="edit_domains" class="block text-sm font-semibold text-gray-900 mb-2">Associate Domains (Optional)</label>
                                <input wire:model="domains" type="text" id="edit_domains"
                                       placeholder="example.com, company.com, organization.org"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 px-3 py-2 placeholder-gray-500">
                                @error('domains') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">Enter domain names separated by commas. Remove all domains to unassociate them.</p>
                            </div>

                            <div class="mb-4">
                                <label class="flex items-start cursor-pointer">
                                    <input wire:model="syncUsers" type="checkbox"
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4 mt-0.5">
                                    <div class="ml-2">
                                        <span class="text-sm font-medium text-gray-900">Sync Users on Update</span>
                                        <p class="text-xs text-gray-600 mt-1">Automatically assign existing users with these domain emails to this organization when updating</p>
                                    </div>
                                </label>
                            </div>
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

    <!-- Advanced Edit Modal -->
    @if($showAdvancedEditModal && $organizationToEditAdvanced)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-50">
                    <form wire:submit.prevent="updateAdvancedOrganization">
                        <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[85vh] overflow-y-auto">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Edit Advanced Organization</h3>
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs font-bold rounded-full border border-purple-300">
                                    Conditional Assignment
                                </span>
                            </div>

                            <div class="mb-4 p-3 bg-amber-50 border-2 border-amber-200 rounded-lg">
                                <p class="text-sm text-amber-900">
                                    <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <strong>Note:</strong> Updating conditions will re-sync users in the background. Users will be re-evaluated against the new conditions.
                                </p>
                            </div>

                            <!-- Basic Info -->
                            <div class="mb-4">
                                <label for="adv_edit_name" class="block text-sm font-semibold text-gray-900 mb-2">Organization Name *</label>
                                <input wire:model="name" type="text" id="adv_edit_name"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2">
                                @error('name') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label for="adv_edit_description" class="block text-sm font-semibold text-gray-900 mb-2">Description</label>
                                <textarea wire:model="description" id="adv_edit_description" rows="2"
                                          class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2"></textarea>
                                @error('description') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div class="mb-4">
                                <label class="flex items-center cursor-pointer">
                                    <input wire:model="is_active" type="checkbox"
                                           class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50 w-4 h-4">
                                    <span class="ml-2 text-sm font-medium text-gray-900">Active</span>
                                </label>
                            </div>

                            <!-- Domains Section -->
                            <div class="mb-4 pt-4 border-t-2 border-gray-200">
                                <label for="adv_edit_domains" class="block text-sm font-semibold text-gray-900 mb-2">Domains *</label>
                                <input wire:model="domains" type="text" id="adv_edit_domains"
                                       placeholder="example.com, company.com"
                                       class="mt-1 block w-full border-2 border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 text-gray-900 px-3 py-2 placeholder-gray-500">
                                @error('domains') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-600">Users from these domains will be evaluated against the conditions below</p>
                            </div>

                            <!-- Conditional Logic Builder -->
                            <div class="mb-4 pt-4 border-t-2 border-purple-200">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-semibold text-gray-900">
                                        <svg class="inline w-5 h-5 mr-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                        </svg>
                                        Conditional Rules *
                                    </label>
                                    <button type="button" wire:click="addCondition"
                                            class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold py-1 px-3 rounded">
                                        + Add Condition
                                    </button>
                                </div>

                                <!-- Condition Logic Selector -->
                                <div class="mb-3 flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border-2 border-gray-200">
                                    <span class="text-sm font-semibold text-gray-700">Match:</span>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" wire:model="conditionLogic" value="AND"
                                               class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                                        <span class="ml-2 text-sm font-medium text-gray-900">ALL conditions (AND)</span>
                                    </label>
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" wire:model="conditionLogic" value="OR"
                                               class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                                        <span class="ml-2 text-sm font-medium text-gray-900">ANY condition (OR)</span>
                                    </label>
                                </div>

                                <!-- Conditions List -->
                                <div class="space-y-3">
                                    @forelse($conditions as $index => $condition)
                                        <div class="border-2 border-purple-200 rounded-lg p-3 bg-purple-50" wire:key="edit-condition-{{ $index }}">
                                            <div class="flex items-start space-x-2">
                                                <div class="flex-1 grid grid-cols-3 gap-2">
                                                    <!-- Custom Field -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Custom Field</label>
                                                        <select wire:model="conditions.{{ $index }}.field_id"
                                                                class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5">
                                                            <option value="">Select field...</option>
                                                            @foreach($this->availableCustomFields as $field)
                                                                <option value="{{ $field->id }}">{{ $field->field_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('conditions.' . $index . '.field_id')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    <!-- Operator -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Operator</label>
                                                        <select wire:model="conditions.{{ $index }}.operator"
                                                                class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5">
                                                            <option value="equals">Equals</option>
                                                            <option value="not_equals">Not Equals</option>
                                                            <option value="contains">Contains</option>
                                                            <option value="not_contains">Not Contains</option>
                                                            <option value="starts_with">Starts With</option>
                                                            <option value="ends_with">Ends With</option>
                                                            <option value="is_empty">Is Empty</option>
                                                            <option value="is_not_empty">Is Not Empty</option>
                                                        </select>
                                                        @error('conditions.' . $index . '.operator')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>

                                                    <!-- Value -->
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Value</label>
                                                        <input type="text" wire:model="conditions.{{ $index }}.value"
                                                               placeholder="Value to compare..."
                                                               class="block w-full border-2 border-gray-300 text-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500 px-2 py-1.5"
                                                               @if(in_array($conditions[$index]['operator'] ?? '', ['is_empty', 'is_not_empty'])) disabled @endif>
                                                        @error('conditions.' . $index . '.value')
                                                            <span class="text-red-600 text-xs">{{ $message }}</span>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <!-- Remove Button -->
                                                <button type="button" wire:click="removeCondition({{ $index }})"
                                                        class="mt-6 text-red-600 hover:text-red-800">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center p-4 bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg">
                                            <p class="text-sm text-gray-600 font-medium">No conditions added yet. Click "Add Condition" to start.</p>
                                        </div>
                                    @endforelse
                                </div>
                                @error('conditions') <span class="text-red-600 text-xs font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update with Conditions
                            </button>
                            <button type="button" wire:click="closeAdvancedEditModal"
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
    @if($showDeleteModal && $organizationToDelete)
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
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Organization</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete "{{ $organizationToDelete->name }}"? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="deleteOrganization" type="button"
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

    <!-- Manage Users Modal -->
    @if($showManageUsersModal && $organizationToManageId)
        @include('livewire.admin.organizations.partials.manage-users-modal')
    @endif

    <!-- OLD MODAL REMOVED - Using partial now
    @if(false && $showManageUsersModal && $organizationToManage)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full relative z-50">
                    <form wire:submit.prevent="updateUsers">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">
                                Manage Users for {{ $organizationToManage->name }}
                            </h3>

                            <!-- Organization Info -->
                            <div class="mb-4 p-3 bg-gray-50 rounded-lg border-2 border-gray-200">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="font-semibold text-gray-600">Description:</span>
                                        <span class="text-gray-900">{{ $organizationToManage->description ?: 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="font-semibold text-gray-600">Status:</span>
                                        @if($organizationToManage->is_active)
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

                            <!-- Add/Remove Users Section -->
                            <h4 class="text-md font-bold text-gray-900 mb-4">Add or Remove Users</h4>

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

                            <!-- Users Selection -->
                            <div class="mb-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">
                                    Select Users to Assign
                                    @php
                                        $selectedCount = is_array($selectedUsers) ? count($selectedUsers) : $selectedUsers->count();
                                    @endphp
                                    @if($selectedCount > 0)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800">
                                            {{ $selectedCount }} selected
                                        </span>
                                    @endif
                                </label>
                                <div class="space-y-2 max-h-96 overflow-y-auto border-2 border-gray-300 rounded-lg p-3 bg-gray-50">
                                    @forelse($this->filteredUsers as $user)
                                        @php
                                            $isAutoAssigned = in_array($user->id, $autoAssignedUsers);
                                            $isManuallyAssigned = in_array($user->id, $manuallyAssignedUsers);
                                            $isChecked = in_array($user->id, $selectedUsers);
                                        @endphp
                                        <label class="flex items-center justify-between cursor-pointer hover:bg-white p-2 rounded">
                                            <div class="flex items-center flex-1 min-w-0">
                                                <input type="checkbox"
                                                       wire:model="selectedUsers"
                                                       value="{{ $user->id }}"
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 w-4 h-4 flex-shrink-0">
                                                <div class="ml-3 flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-gray-900 truncate">{{ $user->fullname }}</div>
                                                    <div class="text-xs text-gray-600 truncate">{{ $user->email }}</div>
                                                    @if($user->domain)
                                                        <div class="text-xs text-gray-500">Domain: {{ $user->domain->domain }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ml-2 flex-shrink-0">
                                                @if($isAutoAssigned)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-900 border border-blue-300">
                                                        🔵 Auto
                                                    </span>
                                                @elseif($isManuallyAssigned)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-900 border border-green-300">
                                                        🟢 Manual
                                                    </span>
                                                @endif
                                            </div>
                                        </label>
                                    @empty
                                        <div class="text-center text-gray-500 py-4">
                                            @if($userSearch)
                                                No users found matching "{{ $userSearch }}"
                                            @else
                                                No users available
                                            @endif
                                        </div>
                                    @endforelse
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
                                @else
                                    @php
                                        $filteredCount = is_array($this->filteredUsers) ? count($this->filteredUsers) : $this->filteredUsers->count();
                                    @endphp
                                    @if($filteredCount > 0)
                                        <div class="mt-2 text-center text-xs text-gray-500">
                                            Showing all {{ $this->totalUsersCount }} users
                                        </div>
                                    @endif
                                @endif

                                <p class="mt-2 text-xs text-gray-600">
                                    <strong>🔵 Auto:</strong> Assigned via domain sync. Will be re-synced during domain operations.<br>
                                    <strong>🟢 Manual:</strong> Added manually by admin. Preserved during domain syncs.
                                </p>
                            </div>

                            <!-- Stats -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm">
                                <div class="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <div class="font-bold text-gray-900">{{ is_array($selectedUsers) ? count($selectedUsers) : $selectedUsers->count() }}</div>
                                        <div class="text-gray-600">Total Selected</div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-blue-900">{{ is_array($autoAssignedUsers) ? count($autoAssignedUsers) : $autoAssignedUsers->count() }}</div>
                                        <div class="text-gray-600">Auto-assigned</div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-green-900">{{ is_array($manuallyAssignedUsers) ? count($manuallyAssignedUsers) : $manuallyAssignedUsers->count() }}</div>
                                        <div class="text-gray-600">Manually Assigned</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Update Users
                            </button>
                            <button type="button" wire:click="closeManageUsersModal"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
