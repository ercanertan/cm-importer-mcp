<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Details - Campaign Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Import Details</h1>
                        <p class="mt-2 text-gray-600">Import #{{ $import->id }} - {{ $import->filename }}</p>
                    </div>
                    <div>
                        <a href="{{ route('campaign-monitor.import') }}"
                           class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded">
                            Back to Imports
                        </a>
                    </div>
                </div>
            </div>

            <!-- Import Status -->
            <div class="bg-white shadow rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">Import Status</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Status -->
                    <div class="text-center">
                        <div class="text-2xl font-bold
                            @if($import->status === 'completed') text-green-600
                            @elseif($import->status === 'failed') text-red-600
                            @elseif($import->status === 'processing') text-yellow-600
                            @else text-gray-600
                            @endif">
                            {{ ucfirst($import->status) }}
                        </div>
                        <div class="text-sm text-gray-500">Status</div>
                    </div>

                    <!-- Progress -->
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ $import->total_rows > 0 ? number_format($import->progress_percentage, 1) : 0 }}%
                        </div>
                        <div class="text-sm text-gray-500">Progress</div>
                    </div>

                    <!-- Duration -->
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">
                            {{ $import->duration ? $import->duration . 's' : '-' }}
                        </div>
                        <div class="text-sm text-gray-500">Duration</div>
                    </div>

                    <!-- Total Rows -->
                    <div class="text-center">
                        <div class="text-2xl font-bold text-indigo-600">
                            {{ number_format($import->total_rows) }}
                        </div>
                        <div class="text-sm text-gray-500">Total Rows</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                @if($import->total_rows > 0)
                    <div class="mt-6">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Progress: {{ $import->processed_rows }}/{{ $import->total_rows }} rows</span>
                            <span>{{ number_format($import->progress_percentage, 1) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                 style="width: {{ $import->progress_percentage }}%"></div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Import Results -->
            <div class="bg-white shadow rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">Import Results</h2>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Created -->
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">
                            {{ number_format($import->created_count) }}
                        </div>
                        <div class="text-sm text-green-700">Created</div>
                    </div>

                    <!-- Updated -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ number_format($import->updated_count) }}
                        </div>
                        <div class="text-sm text-blue-700">Updated</div>
                    </div>

                    <!-- Failed -->
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">
                            {{ number_format($import->failed_count) }}
                        </div>
                        <div class="text-sm text-red-700">Failed</div>
                    </div>

                    <!-- Processed -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600">
                            {{ number_format($import->processed_rows) }}
                        </div>
                        <div class="text-sm text-gray-700">Processed</div>
                    </div>
                </div>
            </div>

            <!-- Custom Fields Detected -->
            @if($import->custom_fields_detected && count($import->custom_fields_detected) > 0)
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4">Custom Fields Detected</h2>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        @foreach($import->custom_fields_detected as $field)
                            <div class="bg-blue-50 p-3 rounded">
                                <div class="font-medium text-blue-900">{{ $field }}</div>
                                <div class="text-sm text-blue-700">Custom Field</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Import Timeline -->
            <div class="bg-white shadow rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold mb-4">Import Timeline</h2>

                <div class="space-y-4">
                    <div class="flex items-center">
                        <div class="w-3 h-3 bg-gray-400 rounded-full mr-4"></div>
                        <div>
                            <div class="font-medium">Import Created</div>
                            <div class="text-sm text-gray-600">{{ $import->created_at->format('M j, Y g:i:s A') }}</div>
                        </div>
                    </div>

                    @if($import->started_at)
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-yellow-400 rounded-full mr-4"></div>
                            <div>
                                <div class="font-medium">Import Started</div>
                                <div class="text-sm text-gray-600">{{ $import->started_at->format('M j, Y g:i:s A') }}</div>
                            </div>
                        </div>
                    @endif

                    @if($import->completed_at)
                        <div class="flex items-center">
                            <div class="w-3 h-3 {{ $import->status === 'completed' ? 'bg-green-400' : 'bg-red-400' }} rounded-full mr-4"></div>
                            <div>
                                <div class="font-medium">Import {{ ucfirst($import->status) }}</div>
                                <div class="text-sm text-gray-600">{{ $import->completed_at->format('M j, Y g:i:s A') }}</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Error Details -->
            @if($import->status === 'failed' && $import->error_details)
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4 text-red-600">Error Details</h2>

                    <div class="bg-red-50 border border-red-200 rounded p-4">
                        @if(is_array($import->error_details))
                            @foreach($import->error_details as $key => $value)
                                <div class="mb-2">
                                    <span class="font-medium text-red-800">{{ ucfirst($key) }}:</span>
                                    <span class="text-red-700">{{ $value }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="text-red-700">{{ $import->error_details }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Import Information -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Import Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">File Details</h3>
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600">Filename:</dt>
                                <dd class="text-sm font-medium">{{ $import->filename ?? 'N/A' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600">Import ID:</dt>
                                <dd class="text-sm font-medium">#{{ $import->id }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="font-medium text-gray-900 mb-2">Processing Details</h3>
                        <dl class="space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600">Status:</dt>
                                <dd class="text-sm font-medium">{{ ucfirst($import->status) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-600">Duration:</dt>
                                <dd class="text-sm font-medium">{{ $import->duration ? $import->duration . ' seconds' : 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto-refresh for processing imports -->
    <script>
        @if($import->status === 'processing')
            setTimeout(() => {
                window.location.reload();
            }, 3000); // Refresh every 3 seconds for processing imports
        @endif
    </script>
</body>
</html>