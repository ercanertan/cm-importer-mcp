<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Progress - Campaign Monitor</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Import Progress</h1>
                <p class="mt-2 text-gray-600">Processing your CSV file...</p>
            </div>

            <!-- Progress Card -->
            <div class="bg-white shadow rounded-lg p-8">
                <!-- Status Message -->
                <div class="mb-6">
                    <div id="status-icon" class="inline-block mr-2">
                        <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                    <h2 id="status-message" class="text-xl font-semibold text-gray-900 inline">Starting import...</h2>
                </div>

                <!-- Progress Bar -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">Progress</span>
                        <span id="progress-percentage" class="text-sm font-medium text-gray-700">0%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div id="progress-bar" class="bg-blue-600 h-4 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                    <div class="flex justify-between mt-2 text-sm text-gray-600">
                        <span id="progress-rows">0 / 0 rows</span>
                        <span id="progress-memory"></span>
                    </div>
                </div>

                <!-- Statistics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="text-sm text-green-600 font-medium">Created</div>
                        <div id="stat-created" class="text-3xl font-bold text-green-700">0</div>
                    </div>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="text-sm text-blue-600 font-medium">Updated</div>
                        <div id="stat-updated" class="text-3xl font-bold text-blue-700">0</div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="text-sm text-red-600 font-medium">Failed</div>
                        <div id="stat-failed" class="text-3xl font-bold text-red-700">0</div>
                    </div>
                </div>

                <!-- Success/Error Message (hidden initially) -->
                <div id="completion-message" class="hidden mb-6 p-4 rounded-lg">
                    <p id="completion-text" class="font-medium"></p>
                </div>

                <!-- Action Buttons -->
                <div id="action-buttons" class="hidden">
                    <a href="{{ route('campaign-monitor.import') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded inline-block">
                        Back to Import
                    </a>
                    <a href="{{ route('campaign-monitor.show', $importId) }}" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-6 rounded inline-block ml-2">
                        View Details
                    </a>
                </div>

                <!-- Warning message -->
                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                        <strong>Please keep this page open.</strong> The import is processing and closing this page will interrupt the process.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize EventSource for Server-Sent Events
        const eventSource = new EventSource(
            '{{ route('campaign-monitor.stream-import') }}?' +
            'file_path={{ urlencode($filePath) }}&' +
            'import_id={{ $importId }}'
        );

        const statusIcon = document.getElementById('status-icon');
        const statusMessage = document.getElementById('status-message');
        const progressBar = document.getElementById('progress-bar');
        const progressPercentage = document.getElementById('progress-percentage');
        const progressRows = document.getElementById('progress-rows');
        const progressMemory = document.getElementById('progress-memory');
        const statCreated = document.getElementById('stat-created');
        const statUpdated = document.getElementById('stat-updated');
        const statFailed = document.getElementById('stat-failed');
        const completionMessage = document.getElementById('completion-message');
        const completionText = document.getElementById('completion-text');
        const actionButtons = document.getElementById('action-buttons');

        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);

            if (data.type === 'status') {
                statusMessage.textContent = data.message;
            }
            else if (data.type === 'progress') {
                // Update progress bar
                const percentage = data.percentage || 0;
                progressBar.style.width = percentage + '%';
                progressPercentage.textContent = percentage.toFixed(1) + '%';

                // Update row count
                if (data.processed_rows !== undefined && data.total_rows !== undefined) {
                    progressRows.textContent = `${data.processed_rows.toLocaleString()} / ${data.total_rows.toLocaleString()} rows`;
                }

                // Update memory usage
                if (data.memory_usage) {
                    progressMemory.textContent = 'Memory: ' + data.memory_usage;
                }

                // Update statistics
                if (data.created_count !== undefined) {
                    statCreated.textContent = data.created_count.toLocaleString();
                }
                if (data.updated_count !== undefined) {
                    statUpdated.textContent = data.updated_count.toLocaleString();
                }
                if (data.failed_count !== undefined) {
                    statFailed.textContent = data.failed_count.toLocaleString();
                }

                statusMessage.textContent = 'Processing data...';
            }
            else if (data.type === 'complete') {
                // Import completed
                statusIcon.innerHTML = `
                    <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                `;
                statusMessage.textContent = 'Import Completed!';

                // Update final statistics
                if (data.log) {
                    statCreated.textContent = data.log.created_count.toLocaleString();
                    statUpdated.textContent = data.log.updated_count.toLocaleString();
                    statFailed.textContent = data.log.failed_count.toLocaleString();
                    progressRows.textContent = `${data.log.processed_rows.toLocaleString()} / ${data.log.processed_rows.toLocaleString()} rows`;
                }

                // Show success message
                completionMessage.classList.remove('hidden');
                completionMessage.classList.add('bg-green-100', 'border', 'border-green-400', 'text-green-700');
                completionText.textContent = data.message || 'Import completed successfully!';

                // Show action buttons
                actionButtons.classList.remove('hidden');

                // Close event source
                eventSource.close();
            }
            else if (data.type === 'error') {
                // Import failed
                statusIcon.innerHTML = `
                    <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                `;
                statusMessage.textContent = 'Import Failed';

                // Show error message
                completionMessage.classList.remove('hidden');
                completionMessage.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
                completionText.textContent = 'Error: ' + data.message;

                // Show action buttons
                actionButtons.classList.remove('hidden');

                // Close event source
                eventSource.close();
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            statusIcon.innerHTML = `
                <svg class="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            `;
            statusMessage.textContent = 'Connection Error';

            completionMessage.classList.remove('hidden');
            completionMessage.classList.add('bg-red-100', 'border', 'border-red-400', 'text-red-700');
            completionText.textContent = 'Lost connection to server. Please check if the import completed.';

            actionButtons.classList.remove('hidden');
            eventSource.close();
        };

        // Warn user before leaving page
        window.addEventListener('beforeunload', function(e) {
            if (eventSource.readyState === EventSource.OPEN) {
                e.preventDefault();
                e.returnValue = 'Import in progress. Are you sure you want to leave?';
            }
        });
    </script>
</body>
</html>
