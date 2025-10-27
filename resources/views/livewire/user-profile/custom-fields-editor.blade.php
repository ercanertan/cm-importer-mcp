<div>
    <div class="bg-white shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Custom Fields</h2>
                <p class="text-sm text-gray-600 mt-1">Manage your custom profile information</p>
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

            @if(count($customFields) > 0)
                <form wire:submit="save">
                    <div class="space-y-6">
                        @foreach($customFields as $field)
                            <div>
                                <label for="field_{{ $field['id'] }}" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $field['field_name'] }}
                                </label>

                                @if($field['data_type'] === 'Text')
                                    <input
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        type="text"
                                        id="field_{{ $field['id'] }}"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                        placeholder="Enter {{ strtolower($field['field_name']) }}">

                                @elseif($field['data_type'] === 'Number')
                                    <input
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        type="number"
                                        id="field_{{ $field['id'] }}"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                        placeholder="Enter {{ strtolower($field['field_name']) }}">

                                @elseif($field['data_type'] === 'Date')
                                    <input
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        type="date"
                                        id="field_{{ $field['id'] }}"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700">

                                @elseif($field['data_type'] === 'MultiSelectMany' && $field['options'])
                                    <select
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        id="field_{{ $field['id'] }}"
                                        multiple
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white text-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @foreach($field['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl (Cmd on Mac) to select multiple options</p>

                                @elseif($field['data_type'] === 'MultiSelectOne' && $field['options'])
                                    <select
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        id="field_{{ $field['id'] }}"
                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white text-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="">Select {{ strtolower($field['field_name']) }}</option>
                                        @foreach($field['options'] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>

                                @else
                                    <input
                                        wire:model="fieldValues.{{ $field['id'] }}"
                                        type="text"
                                        id="field_{{ $field['id'] }}"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md text-gray-700"
                                        placeholder="Enter {{ strtolower($field['field_name']) }}">
                                @endif

                                @error("fieldValues.{$field['id']}")
                                    <span class="text-red-500 text-xs mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Custom Fields
                        </button>
                    </div>
                </form>
            @else
                <div class="text-center py-8">
                    <p class="text-gray-500">No editable custom fields are available at this time.</p>
                    <p class="text-sm text-gray-400 mt-2">Contact your administrator to enable custom fields.</p>
                </div>
            @endif
        </div>
    </div>
</div>
