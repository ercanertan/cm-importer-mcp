<div>
    <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 sm:px-20 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-800">Custom Fields Management</h2>
                            <p class="text-sm text-gray-600 mt-1">Manage custom fields for user profiles</p>
                        </div>
                        <div class="flex gap-3">
                            <button wire:click="openCampaignMonitorModal"
                                    wire:loading.attr="disabled"
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2"
                                    title="@if($cmConnectionStatus['connected'] ?? false) Fetch from Campaign Monitor: {{ $cmConnectionStatus['client_name'] ?? 'Unknown Client' }} @else Campaign Monitor not connected @endif">
                                @if($isFetchingFromCm)
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Fetching...
                                @else
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                    </svg>
                                    Fetch from Campaign Monitor
                                @endif
                            </button>
                            <button wire:click="openJsonModal"
                                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                                Import from JSON
                            </button>
                            <button wire:click="openCreateModal"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                Create Custom Field
                            </button>
                        </div>

                        {{-- Campaign Monitor Status --}}
                        @if(isset($cmConnectionStatus['configured']) && !$cmConnectionStatus['configured'])
                            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-yellow-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-yellow-800">
                                        <strong>Campaign Monitor not configured:</strong> Please set CM_API_KEY and CREATESEND_CLIENT_ID in your .env file.
                                    </div>
                                </div>
                            </div>
                        @elseif(isset($cmConnectionStatus['connected']) && !$cmConnectionStatus['connected'])
                            <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-red-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-red-800">
                                        <strong>Campaign Monitor connection failed:</strong> {{ $cmConnectionStatus['error'] ?? 'Unknown error' }}
                                    </div>
                                </div>
                            </div>
                        @elseif(isset($cmConnectionStatus['connected']) && $cmConnectionStatus['connected'])
                            <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-md">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-green-400 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <div class="text-sm text-green-800">
                                        <strong>Connected to Campaign Monitor:</strong> {{ $cmConnectionStatus['client_name'] ?? 'Unknown Client' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if (session()->has('message'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ session('message') }}</span>
                        </div>
                    @endif

                    @if (session()->has('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="mb-4">
                        <input wire:model.live="search"
                               type="text"
                               placeholder="Search by field name or key..."
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Field Key
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Field Name
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Data Type
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Options
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Active
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        User Editable
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Last Seen
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($customFields as $customField)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <code class="bg-gray-100 px-2 py-1 rounded">{{ $customField->field_key }}</code>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $customField->field_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                @if($customField->data_type->value === 'Text') bg-blue-100 text-blue-800
                                                @elseif($customField->data_type->value === 'Number') bg-green-100 text-green-800
                                                @elseif($customField->data_type->value === 'Date') bg-purple-100 text-purple-800
                                                @elseif(in_array($customField->data_type->value, ['MultiSelectOne', 'MultiSelectMany'])) bg-yellow-100 text-yellow-800
                                                @endif">
                                                {{ $customField->data_type->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            @if($customField->options)
                                                <span class="text-xs">{{ implode(', ', array_slice($customField->options, 0, 3)) }}@if(count($customField->options) > 3)...@endif</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button wire:click="toggleActive({{ $customField->id }})"
                                                    class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none
                                                    {{ $customField->is_active ? 'bg-green-600' : 'bg-gray-300' }}">
                                                <span class="sr-only">Toggle active</span>
                                                <span class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform
                                                    {{ $customField->is_active ? 'translate-x-6' : 'translate-x-1' }}">
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button wire:click="toggleUserEditable({{ $customField->id }})"
                                                    class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none
                                                    {{ $customField->is_user_editable ? 'bg-indigo-600' : 'bg-gray-300' }}">
                                                <span class="sr-only">Toggle user editable</span>
                                                <span class="inline-block w-4 h-4 transform bg-white rounded-full transition-transform
                                                    {{ $customField->is_user_editable ? 'translate-x-6' : 'translate-x-1' }}">
                                                </span>
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if($customField->last_seen_at)
                                                {{ $customField->last_seen_at->diffForHumans() }}
                                            @else
                                                <span class="text-gray-400">Never</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button wire:click="openEditModal({{ $customField->id }})"
                                                    class="text-indigo-700 hover:text-indigo-900 font-semibold mr-4">
                                                Edit
                                            </button>
                                            <button wire:click="confirmDelete({{ $customField->id }})"
                                                    class="text-red-700 hover:text-red-900 font-semibold">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                            No custom fields found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $customFields->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    @if($showCreateModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.closeCreateModal()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 bg-gray-500">
                <div class="inset-0 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" x-data @click.away="$wire.closeCreateModal()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Create Custom Field</h3>

                        <div class="mb-4">
                            <label for="field_key" class="block text-sm font-medium text-gray-700">Field Key *</label>
                            <input wire:model="field_key" type="text" id="field_key"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                   placeholder="e.g., department">
                            @error('field_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="field_name" class="block text-sm font-medium text-gray-700">Field Name *</label>
                            <input wire:model="field_name" type="text" id="field_name"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                   placeholder="e.g., Department">
                            @error('field_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="data_type" class="block text-sm font-medium text-gray-700">Data Type *</label>
                            <select wire:model.live="data_type" id="data_type"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white text-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @foreach(\App\Enums\CustomFieldTypes::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('data_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="options" class="block text-sm font-medium text-gray-700">Options (comma-separated, for multi_select)</label>
                            <input wire:model="options" type="text" id="options"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                   placeholder="e.g., Option 1, Option 2, Option 3">
                            @error('options') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <flux:switch
                                    wire:model="is_active"
                                    class="mr-2">
                                </flux:switch>
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <flux:switch
                                    wire:model="is_user_editable"
                                    class="mr-2">
                                </flux:switch>
                                <span class="text-sm text-gray-700">User Editable</span>
                            </label>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="createCustomField" type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Create
                        </button>
                        <button wire:click="closeCreateModal" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Edit Modal --}}
    @if($showEditModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.closeEditModal()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 bg-gray-500">
                <div class="inset-0 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" x-data @click.away="$wire.closeEditModal()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Custom Field</h3>

                        <div class="mb-4">
                            <label for="edit_field_key" class="block text-sm font-medium text-gray-700">Field Key *</label>
                            <input wire:model="field_key" type="text" id="edit_field_key"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700">
                            @error('field_key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="edit_field_name" class="block text-sm font-medium text-gray-700">Field Name *</label>
                            <input wire:model="field_name" type="text" id="edit_field_name"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700">
                            @error('field_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="edit_data_type" class="block text-sm font-medium text-gray-700">Data Type *</label>
                            <select wire:model.live="data_type" id="edit_data_type"
                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white text-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @foreach(\App\Enums\CustomFieldTypes::cases() as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                            @error('data_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label for="edit_options" class="block text-sm font-medium text-gray-700">Options (comma-separated, for multi_select)</label>
                            <input wire:model="options" type="text" id="edit_options"
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700">
                            @error('options') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <flux:switch
                                    wire:model="is_active"
                                    class="mr-2">
                                </flux:switch>
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center">
                                <flux:switch
                                    wire:model="is_user_editable"
                                    class="mr-2">
                                </flux:switch>
                                <span class="text-sm text-gray-700">User Editable</span>
                            </label>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="updateCustomField" type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Update
                        </button>
                        <button wire:click="closeEditModal" type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Delete Modal --}}
    @if($showDeleteModal)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.cancelDelete()">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 bg-gray-500">
                <div class="inset-0 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full" x-data @click.away="$wire.cancelDelete()">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Delete Custom Field
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this custom field?
                                        This action will also delete all associated custom field values for all users. This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="deleteCustomField" type="button"
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

        <!-- JSON Input Modal -->
        @if($showJsonModal)
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="relative min-h-screen flex items-center justify-center p-4">
                    <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-4xl sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Import Custom Fields from JSON</h3>

                            @if(!empty($importErrors))
                                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                                    @foreach($importErrors as $error)
                                        <p class="text-sm">{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mb-4">
                                <label for="jsonInput" class="block text-sm font-medium text-gray-700 mb-2">JSON Data</label>
                                <textarea wire:model="jsonInput"
                                          id="jsonInput"
                                          rows="12"
                                          class="w-full border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 p-3 font-mono text-sm"
                                          placeholder='[{"FieldName":"Job Title","Key":"[JobTitle]","DataType":"Text","FieldOptions":[],"VisibleInPreferenceCenter":true}]'></textarea>
                                @error('jsonInput')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-blue-600 hover:text-blue-800 font-medium">
                                        Example Format & Requirements
                                    </summary>
                                    <div class="mt-2 p-3 bg-gray-50 rounded-md">
                                        <div class="mb-3">
                                            <h4 class="font-semibold text-gray-800 mb-2">Required Format:</h4>
                                            <pre class="text-xs text-gray-700 bg-white p-2 rounded overflow-x-auto"><code>{{ $this->getExampleJson() }}</code></pre>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            <ul class="list-disc list-inside space-y-1">
                                                <li><strong>FieldName</strong>: Field display name (required)</li>
                                                <li><strong>Key</strong>: Field key in brackets like [JobTitle] (required)</li>
                                                <li><strong>DataType</strong>: Text, Number, Date, MultiSelectOne, MultiSelectMany, Country (required)</li>
                                                <li><strong>FieldOptions</strong>: Array of options for select fields (required)</li>
                                                <li><strong>VisibleInPreferenceCenter</strong>: Boolean value (required)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="closeJsonModal" type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:ml-3 sm:text-sm">
                                Cancel
                            </button>
                            <button wire:click="processJsonInput" type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:w-auto sm:text-sm">
                                Process JSON
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- JSON Preview Modal -->
        @if($showPreviewModal)
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="relative min-h-screen flex items-center justify-center p-4">
                    <div class="relative bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:max-w-6xl sm:w-full">
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Preview Import Changes</h3>

                            <!-- Statistics -->
                            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                                    <div>
                                        <div class="text-2xl font-bold text-blue-600">{{ $previewData['statistics']['total'] ?? 0 }}</div>
                                        <div class="text-xs text-blue-800">Total</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-green-600">{{ $previewData['statistics']['new'] ?? 0 }}</div>
                                        <div class="text-xs text-green-800">New</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-orange-600">{{ $previewData['statistics']['updated'] ?? 0 }}</div>
                                        <div class="text-xs text-orange-800">Updates</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-yellow-600">{{ $previewData['statistics']['duplicates'] ?? 0 }}</div>
                                        <div class="text-xs text-yellow-800">Duplicates</div>
                                    </div>
                                    <div>
                                        <div class="text-2xl font-bold text-red-600">{{ $previewData['statistics']['invalid'] ?? 0 }}</div>
                                        <div class="text-xs text-red-800">Invalid</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Selection Controls -->
                            <div class="mb-4 flex justify-between items-center">
                                <div class="text-sm text-gray-600">
                                    Selected: {{ count($selectedPreviewIndices) }} fields
                                </div>
                                <div class="flex gap-2">
                                    <button wire:click="selectAllValidFields" type="button"
                                            class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                        Select All Valid
                                    </button>
                                    <button wire:click="clearSelection" type="button"
                                            class="text-sm text-gray-600 hover:text-gray-800 font-medium">
                                        Clear Selection
                                    </button>
                                </div>
                            </div>

                            <!-- Preview Tabs -->
                            <div class="space-y-4">
                                @if(!empty($previewData['new']))
                                    <div>
                                        <h4 class="text-md font-semibold text-green-800 mb-2">New Fields ({{ count($previewData['new']) }})</h4>
                                        <div class="space-y-2">
                                            @foreach($previewData['new'] as $item)
                                                <div class="border border-green-200 rounded-md p-3 bg-green-50">
                                                    <div class="flex items-start">
                                                        <input type="checkbox"
                                                               wire:model.live="selectedPreviewIndices"
                                                               value="{{ $item['index'] }}"
                                                               class="mt-1 mr-3">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-green-900">{{ $item['external']['field_name'] }}</div>
                                                            <div class="text-sm text-green-700">
                                                                Key: {{ $item['external']['field_key'] }} | Type: {{ $item['external']['data_type'] }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($previewData['updated']))
                                    <div>
                                        <h4 class="text-md font-semibold text-orange-800 mb-2">Fields to Update ({{ count($previewData['updated']) }})</h4>
                                        <div class="space-y-2">
                                            @foreach($previewData['updated'] as $item)
                                                <div class="border border-orange-200 rounded-md p-3 bg-orange-50">
                                                    <div class="flex items-start">
                                                        <input type="checkbox"
                                                               wire:model.live="selectedPreviewIndices"
                                                               value="{{ $item['index'] }}"
                                                               class="mt-1 mr-3">
                                                        <div class="flex-1">
                                                            <div class="font-medium text-orange-900">{{ $item['external']['field_name'] }}</div>
                                                            <div class="text-sm text-orange-700">
                                                                Key: {{ $item['external']['field_key'] }} | Type: {{ $item['external']['data_type'] }}
                                                            </div>
                                                            @if(!empty($item['differences']))
                                                                <div class="mt-1 text-xs text-orange-600">
                                                                    Changes: {{ implode(', ', array_keys($item['differences'])) }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($previewData['duplicates']))
                                    <div>
                                        <h4 class="text-md font-semibold text-yellow-800 mb-2">Duplicates ({{ count($previewData['duplicates']) }})</h4>
                                        <div class="space-y-2">
                                            @foreach($previewData['duplicates'] as $item)
                                                <div class="border border-yellow-200 rounded-md p-3 bg-yellow-50 opacity-75">
                                                    <div class="flex items-center">
                                                        <div class="text-yellow-600 mr-2">⚠️</div>
                                                        <div>
                                                            <div class="font-medium text-yellow-900">{{ $item['external']['field_name'] }}</div>
                                                            <div class="text-sm text-yellow-700">
                                                                {{ $item['reason'] }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($previewData['invalid']))
                                    <div>
                                        <h4 class="text-md font-semibold text-red-800 mb-2">Invalid Fields ({{ count($previewData['invalid']) }})</h4>
                                        <div class="space-y-2">
                                            @foreach($previewData['invalid'] as $item)
                                                <div class="border border-red-200 rounded-md p-3 bg-red-50 opacity-75">
                                                    <div class="flex items-center">
                                                        <div class="text-red-600 mr-2">✗</div>
                                                        <div>
                                                            <div class="font-medium text-red-900">Field at index {{ $item['index'] }}</div>
                                                            <div class="text-sm text-red-700">
                                                                {{ $item['error'] }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="closePreviewModal" type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:ml-3 sm:text-sm">
                                Cancel
                            </button>
                            <button wire:click="applyJsonChanges" type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:w-auto sm:text-sm">
                                Import Selected ({{ count($selectedPreviewIndices) }})
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
