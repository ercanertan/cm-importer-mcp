<div>
    <div class="bg-white shadow rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Primary Organization</h3>

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

        @if($organizations && $organizations->count() > 0)
            <div class="space-y-3">
                <p class="text-sm text-gray-600 mb-4">
                    Select your primary organization. This will be used as your default organization throughout the application.
                </p>

                @foreach($organizations as $org)
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
                <p class="mt-4 text-sm text-gray-500">You don't belong to any organizations yet.</p>
            </div>
        @endif
    </div>
</div>
