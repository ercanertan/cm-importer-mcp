<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Monitor CSV Import</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Campaign Monitor CSV Import</h1>
                <p class="mt-2 text-gray-600">Upload and import subscriber data from CSV files</p>
            </div>

            <!-- Success/Error Messages -->
            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Upload Form -->
            @if(!isset($uploadedFile))
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4">Upload CSV File</h2>

                    <form action="{{ route('campaign-monitor.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-2">
                                CSV File
                            </label>
                            <input type="file"
                                   id="csv_file"
                                   name="csv_file"
                                   accept=".csv,.txt"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                   required>
                            <p class="mt-1 text-sm text-gray-500">
                                Upload a CSV file with subscriber data. File must contain an "email" column.
                                Maximum file size: {{ config('campaign-monitor.max_upload_size', 10240) / 1024 }}MB
                            </p>
                        </div>

                        @if(config('campaign-monitor.enable_file_type_selection', false))
                            <div class="mb-4">
                                <label for="file_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Subscriber File Type
                                </label>
                                <select id="file_type"
                                        name="file_type"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                    <option value="all">All Subscribers (don't change status)</option>
                                    <option value="active">Active Subscribers</option>
                                    <option value="bounced">Bounced Subscribers</option>
                                    <option value="deleted">Deleted Subscribers</option>
                                    <option value="unsubscribed">Unsubscribed Subscribers</option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">
                                    Select the type of subscriber list you're importing. This will set the cm_status for all imported subscribers accordingly.
                                </p>
                            </div>
                        @else
                            <input type="hidden" name="file_type" value="active">
                        @endif

                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded">
                            Upload & Preview
                        </button>
                    </form>
                </div>
            @endif

            <!-- Preview Section -->
            @if(isset($preview) && $preview['valid'])
                <div class="bg-white shadow rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold mb-4">CSV Preview</h2>

                    <!-- File Type Info -->
                    @if(config('campaign-monitor.enable_file_type_selection', false) && isset($fileType))
                        <div class="mb-6 bg-blue-50 p-4 rounded">
                            <h4 class="font-medium text-blue-900 mb-1">Subscriber File Type:</h4>
                            <p class="text-blue-800">
                                @if($fileType === 'all')
                                    All Subscribers (status will not be changed)
                                @else
                                    {{ ucfirst($fileType) }} Subscribers - All imported users will have cm_status set to "{{ $fileType }}"
                                @endif
                            </p>
                        </div>
                    @endif

                    <!-- Field Information -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium mb-2">Detected Fields</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            @foreach($preview['headers'] as $header)
                                <div class="bg-gray-50 p-3 rounded">
                                    <div class="font-medium">{{ $header }}</div>
                                    <div class="text-sm text-gray-600">
                                        @if(in_array($header, $preview['custom_fields']))
                                            Custom Field
                                        @else
                                            Standard Field
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Data Preview -->
                    @if(!empty($preview['preview']))
                        <div class="mb-6">
                            <h3 class="text-lg font-medium mb-2">Sample Data</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            @foreach($preview['headers'] as $header)
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ $header }}
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($preview['preview'] as $row)
                                            <tr>
                                                @foreach($preview['headers'] as $header)
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $row[$header] ?? '' }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Custom Fields Info -->
                    @if(!empty($preview['custom_fields']))
                        <div class="mb-6 bg-blue-50 p-4 rounded">
                            <h4 class="font-medium text-blue-900 mb-2">Custom Fields Detected:</h4>
                            <p class="text-blue-800">{{ implode(', ', $preview['custom_fields']) }}</p>
                        </div>
                    @endif

                    <!-- Import Actions -->
                    <div class="flex gap-4">
                        <form action="{{ route('campaign-monitor.import') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="file_path" value="{{ $uploadedFile }}">
                            <input type="hidden" name="file_type" value="{{ $fileType ?? 'all' }}">
                            <button type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded"
                                    onclick="return confirm('Are you sure you want to import this data?')">
                                Start Import
                            </button>
                        </form>

                        <a href="{{ route('campaign-monitor.import') }}"
                           class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-6 rounded">
                            Cancel
                        </a>
                    </div>
                </div>
            @endif

            <!-- Recent Imports -->
            @if(!empty($recentImports) && $recentImports->count() > 0)
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">Recent Imports</h2>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Filename
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Progress
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Results
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($recentImports as $import)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ $import->filename }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                @if($import->status === 'completed') bg-green-100 text-green-800
                                                @elseif($import->status === 'failed') bg-red-100 text-red-800
                                                @elseif($import->status === 'processing') bg-yellow-100 text-yellow-800
                                                @else bg-gray-100 text-gray-800
                                                @endif">
                                                {{ ucfirst($import->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if($import->total_rows > 0)
                                                {{ $import->processed_rows }}/{{ $import->total_rows }}
                                                ({{ number_format($import->progress_percentage, 1) }}%)
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="text-xs">
                                                <div class="text-green-600">✓ {{ $import->created_count }} created</div>
                                                <div class="text-blue-600">✓ {{ $import->updated_count }} updated</div>
                                                @if($import->failed_count > 0)
                                                    <div class="text-red-600">✗ {{ $import->failed_count }} failed</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $import->created_at->format('M j, Y g:i A') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('campaign-monitor.show', $import->id) }}"
                                               class="text-blue-600 hover:text-blue-900">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Auto-refresh for processing imports -->
    <script>
        // Auto refresh page if there are processing imports
        const processingImports = @json($recentImports->where('status', 'processing')->count() ?? 0);
        if (processingImports > 0) {
            setTimeout(() => {
                window.location.reload();
            }, 5000); // Refresh every 5 seconds
        }
    </script>
</body>
</html>