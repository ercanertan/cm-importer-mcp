<?php

namespace App\Livewire\Admin\SyncLogs;

use App\Models\SyncLog;
use Livewire\Component;
use Livewire\WithPagination;

class SyncLogViewer extends Component
{
    use WithPagination;

    public $selectedLog = null;
    public $showDetailModal = false;

    public function render()
    {
        $syncLogs = SyncLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Check if any sync jobs are currently running
        $hasActiveSyncs = SyncLog::whereIn('status', ['pending', 'running'])
            ->exists();

        return view('livewire.admin.sync-logs.sync-log-viewer', [
            'syncLogs' => $syncLogs,
            'hasActiveSyncs' => $hasActiveSyncs
        ])->layout('components.layouts.app', ['title' => 'Sync Logs']);
    }

    public function viewDetails($id)
    {
        $this->selectedLog = SyncLog::with('user')->findOrFail($id);
        $this->showDetailModal = true;
    }

    public function closeDetailModal()
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
    }

    // Livewire will automatically refresh the component when this method is called
}
