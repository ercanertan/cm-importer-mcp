<div wire:poll.3s>
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
                                    <div class="flex gap-3">
                                        @if(in_array($import->status, ['pending', 'processing']))
                                            <a href="{{ route('campaign-monitor.import-progress', $import->id) }}"
                                               class="text-yellow-600 hover:text-yellow-900">
                                                View Progress
                                            </a>
                                        @endif
                                        <a href="{{ route('campaign-monitor.show', $import->id) }}"
                                           class="text-blue-600 hover:text-blue-900">
                                            View Details
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
