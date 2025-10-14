<div class="min-h-screen py-8 bg-gray-50"
     wire:poll.1s="refreshImport"
     x-data="{ started: false }"
     x-init="
         @if($import && $import->status === 'pending' && !$isProcessing)
         if (!started) {
             started = true;
             // Wait for page to fully load, then trigger import in background
             setTimeout(() => {
                 fetch('{{ route('campaign-monitor.start-import', $importId) }}', {
                     method: 'POST',
                     headers: {
                         'X-CSRF-TOKEN': '{{ csrf_token() }}',
                         'Accept': 'application/json'
                     }
                 }).catch(e => console.error('Failed to start import:', e));
             }, 100);
         }
         @endif
     ">

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Import Progress</h1>
            <p class="mt-2 text-gray-600">Processing your CSV file...</p>
        </div>

        <!-- Progress Card -->
        <div class="bg-white shadow-lg rounded-lg p-8">
            <!-- Status Icon & Message -->
            <div class="mb-6 flex items-center">
                <!-- Loading Icon -->
                <div class="mr-3" @if($isComplete || $hasError) style="display:none;" @endif>
                    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <!-- Success Icon -->
                <div class="mr-3" @if(!$isComplete || $hasError) style="display:none;" @endif>
                    <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <!-- Error Icon -->
                <div class="mr-3" @if(!$hasError) style="display:none;" @endif>
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <!-- Status Text -->
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        @if($hasError)
                            Import Failed
                        @elseif($isComplete)
                            Import Completed!
                        @elseif($import && $import->status === 'processing')
                            Processing...
                        @elseif($import && $import->status === 'pending')
                            Ready to Import
                        @else
                            Initializing...
                        @endif
                    </h2>
                </div>
            </div>

            @if($import)
                <!-- Progress Bar -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Progress</span>
                        <span class="text-sm font-medium text-gray-700">{{ number_format($this->progressPercentage, 1) }}%</span>
                    </div>

                    <!-- Animated Progress Bar -->
                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div class="@if($isComplete && !$hasError) bg-gradient-to-r from-green-500 to-green-600 @else bg-gradient-to-r from-blue-500 to-blue-600 @endif h-4 rounded-full transition-all duration-500 ease-out"
                             style="width: {{ $this->progressPercentage }}%">
                        </div>
                    </div>

                    <div class="flex justify-between mt-2 text-sm text-gray-600">
                        <span>{{ number_format($import->processed_rows) }} / {{ number_format($import->total_rows) }} rows</span>
                        <span wire:loading.remove wire:target="refreshImport" class="text-xs text-gray-400">
                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-1"></span>
                            Live
                        </span>
                    </div>
                </div>

                <!-- Statistics Grid with Alpine transitions -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Created -->
                    <div class="bg-green-50 p-4 rounded-lg border border-green-100 transition-all duration-300 hover:shadow-md">
                        <div class="text-sm text-green-600 font-medium mb-1">Created</div>
                        <div class="text-3xl font-bold text-green-700 transition-all duration-500">
                            {{ number_format($import->created_count) }}
                        </div>
                    </div>

                    <!-- Updated -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 transition-all duration-300 hover:shadow-md">
                        <div class="text-sm text-blue-600 font-medium mb-1">Updated</div>
                        <div class="text-3xl font-bold text-blue-700 transition-all duration-500">
                            {{ number_format($import->updated_count) }}
                        </div>
                    </div>

                    <!-- Failed -->
                    <div class="bg-red-50 p-4 rounded-lg border border-red-100 transition-all duration-300 hover:shadow-md @if($import->failed_count > 0) ring-2 ring-red-300 @endif">
                        <div class="text-sm text-red-600 font-medium mb-1">Failed</div>
                        <div class="text-3xl font-bold text-red-700 transition-all duration-500">
                            {{ number_format($import->failed_count) }}
                        </div>
                    </div>
                </div>

                <!-- Memory Usage -->
                @if($import->memory_current || $import->memory_peak)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    @if($import->memory_current)
                    <div class="bg-purple-50 p-4 rounded-lg border border-purple-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-purple-600 font-medium mb-1">Current Memory</div>
                                <div class="text-2xl font-bold text-purple-700">{{ $import->memory_current }}</div>
                            </div>
                            <svg class="h-8 w-8 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                            </svg>
                        </div>
                    </div>
                    @endif

                    @if($import->memory_peak)
                    <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm text-indigo-600 font-medium mb-1">Peak Memory</div>
                                <div class="text-2xl font-bold text-indigo-700">{{ $import->memory_peak }}</div>
                            </div>
                            <svg class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                <!-- Error Message -->
                @if($hasError)
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        <p class="font-medium">{{ $errorMessage }}</p>
                    </div>
                @endif

                <!-- Success Message -->
                @if($isComplete && !$hasError)
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <p class="font-medium">
                            Import completed successfully!
                            Processed {{ number_format($import->processed_rows) }} rows in {{ $import->duration ? round($import->duration) . 's' : 'N/A' }}.
                            @if($import->memory_peak)
                                <span class="block mt-1 text-sm">Peak memory usage: {{ $import->memory_peak }}</span>
                            @endif
                        </p>
                    </div>
                @endif

                <!-- Action Buttons -->
                @if($isComplete || $hasError)
                <div class="flex gap-4">
                    <a href="{{ route('campaign-monitor.import') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded transition-colors duration-200">
                        Back to Import
                    </a>
                    <a href="{{ route('campaign-monitor.show', $importId) }}"
                       class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-6 rounded transition-colors duration-200">
                        View Details
                    </a>
                </div>
                @endif

                <!-- Warning Message (only while processing) -->
                @if($isProcessing && !$isComplete)
                    <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-start">
                            <svg class="h-5 w-5 text-yellow-600 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm text-yellow-800">
                                <strong>Please keep this page open.</strong> The import is processing and closing this page may interrupt the process.
                            </p>
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <!-- Additional Info -->
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>Auto-refreshing every second • Wire:poll enabled</p>
        </div>
    </div>
</div>
