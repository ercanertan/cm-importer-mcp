<div class="py-12" @if($hasActiveSyncs) wire:poll.5s @endif>
    <div class="bg-white max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Sync Logs</h2>
                    <button wire:click="$refresh"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded-lg shadow-sm">
                        Refresh Now
                    </button>
                </div>

                <!-- Auto-refresh indicator -->
                @if($hasActiveSyncs)
                    <div class="mb-4 bg-blue-50 border border-blue-200 px-4 py-2 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">Auto-refresh enabled:</span> This page automatically updates every 5 seconds while there are active sync jobs.
                        </p>
                    </div>
                @else
                    <div class="mb-4 bg-gray-50 border border-gray-200 px-4 py-2 rounded-lg">
                        <p class="text-sm text-gray-700">
                            <span class="font-semibold">Auto-refresh paused:</span> No active sync jobs. Use "Refresh Now" to manually update.
                        </p>
                    </div>
                @endif

                <!-- Flash Messages -->
                @if (session()->has('message'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg relative font-medium" role="alert">
                        <span class="block sm:inline">{{ session('message') }}</span>
                    </div>
                @endif

                <!-- Sync Logs Table -->
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-300">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Progress</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Started</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-900 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($syncLogs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">#{{ $log->id }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ str_replace('_', ' ', ucwords($log->type, '_')) }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($log->status === 'completed')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-green-100 text-green-900 border border-green-300">
                                                Completed
                                            </span>
                                        @elseif($log->status === 'processing')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-blue-100 text-blue-900 border border-blue-300">
                                                Processing
                                            </span>
                                        @elseif($log->status === 'failed')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-red-100 text-red-900 border border-red-300">
                                                Failed
                                            </span>
                                        @else
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-gray-200 text-gray-900 border border-gray-400">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            @if($log->total_items > 0)
                                                {{ $log->processed_items }} / {{ $log->total_items }}
                                                <span class="text-gray-600">({{ $log->progress_percentage }}%)</span>
                                            @else
                                                N/A
                                            @endif
                                        </div>
                                        @if($log->status === 'processing' && $log->total_items > 0)
                                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $log->progress_percentage }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">{{ $log->user?->fullname ?? 'System' }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">
                                            {{ $log->started_at ? $log->started_at->format('M d, Y H:i') : '-' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">
                                            @if($log->duration)
                                                {{ gmdate('H:i:s', $log->duration) }}
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button wire:click="viewDetails({{ $log->id }})"
                                                class="text-indigo-700 hover:text-indigo-900 font-semibold">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-700 font-medium">
                                        No sync logs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $syncLogs->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    @if($showDetailModal && $selectedLog)
        <div class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-40" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full relative z-50">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                        <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Sync Log Details - #{{ $selectedLog->id }}</h3>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900">Type</label>
                                    <p class="mt-1 text-sm text-gray-700">{{ str_replace('_', ' ', ucwords($selectedLog->type, '_')) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900">Status</label>
                                    <p class="mt-1">
                                        @if($selectedLog->status === 'completed')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-green-100 text-green-900 border border-green-300">
                                                Completed
                                            </span>
                                        @elseif($selectedLog->status === 'processing')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-blue-100 text-blue-900 border border-blue-300">
                                                Processing
                                            </span>
                                        @elseif($selectedLog->status === 'failed')
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-red-100 text-red-900 border border-red-300">
                                                Failed
                                            </span>
                                        @else
                                            <span class="px-3 py-1 inline-flex text-xs font-bold rounded-full bg-gray-200 text-gray-900 border border-gray-400">
                                                Pending
                                            </span>
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900">User</label>
                                    <p class="mt-1 text-sm text-gray-700">{{ $selectedLog->user?->fullname ?? 'System' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-900">Created</label>
                                    <p class="mt-1 text-sm text-gray-700">{{ $selectedLog->created_at->format('M d, Y H:i:s') }}</p>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 pt-4">
                                <label class="block text-sm font-semibold text-gray-900 mb-2">Progress</label>
                                <div class="grid grid-cols-4 gap-4 text-center">
                                    <div class="bg-gray-100 p-3 rounded">
                                        <div class="text-2xl font-bold text-gray-900">{{ $selectedLog->total_items }}</div>
                                        <div class="text-xs text-gray-600">Total</div>
                                    </div>
                                    <div class="bg-blue-100 p-3 rounded">
                                        <div class="text-2xl font-bold text-blue-900">{{ $selectedLog->processed_items }}</div>
                                        <div class="text-xs text-blue-700">Processed</div>
                                    </div>
                                    <div class="bg-green-100 p-3 rounded">
                                        <div class="text-2xl font-bold text-green-900">{{ $selectedLog->successful_items }}</div>
                                        <div class="text-xs text-green-700">Successful</div>
                                    </div>
                                    <div class="bg-red-100 p-3 rounded">
                                        <div class="text-2xl font-bold text-red-900">{{ $selectedLog->failed_items }}</div>
                                        <div class="text-xs text-red-700">Failed</div>
                                    </div>
                                </div>
                                @if($selectedLog->total_items > 0)
                                    <div class="mt-4">
                                        <div class="w-full bg-gray-200 rounded-full h-4">
                                            <div class="bg-blue-600 h-4 rounded-full flex items-center justify-center text-xs text-white font-semibold" style="width: {{ $selectedLog->progress_percentage }}%">
                                                {{ $selectedLog->progress_percentage }}%
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="border-t border-gray-200 pt-4">
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900">Started At</label>
                                        <p class="mt-1 text-sm text-gray-700">{{ $selectedLog->started_at?->format('M d, Y H:i:s') ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900">Completed At</label>
                                        <p class="mt-1 text-sm text-gray-700">{{ $selectedLog->completed_at?->format('M d, Y H:i:s') ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-900">Duration</label>
                                        <p class="mt-1 text-sm text-gray-700">
                                            @if($selectedLog->duration)
                                                {{ gmdate('H:i:s', $selectedLog->duration) }}
                                            @else
                                                -
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            @if($selectedLog->error_message)
                                <div class="border-t border-gray-200 pt-4">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">Error Message</label>
                                    <div class="bg-red-50 border border-red-200 rounded p-3">
                                        <p class="text-sm text-red-900 font-mono">{{ $selectedLog->error_message }}</p>
                                    </div>
                                </div>
                            @endif

                            @if($selectedLog->metadata)
                                <div class="border-t border-gray-200 pt-4">
                                    <label class="block text-sm font-semibold text-gray-900 mb-2">Additional Information</label>
                                    <div class="bg-gray-50 border border-gray-200 rounded p-3">
                                        <pre class="text-xs text-gray-700 overflow-auto">{{ json_encode($selectedLog->metadata, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" wire:click="closeDetailModal"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
