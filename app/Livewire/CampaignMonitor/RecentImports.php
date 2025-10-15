<?php

namespace App\Livewire\CampaignMonitor;

use App\Services\CampaignMonitorImportService;
use Livewire\Component;

class RecentImports extends Component
{
    protected $importService;

    public function boot(CampaignMonitorImportService $importService)
    {
        $this->importService = $importService;
    }

    public function render()
    {
        $recentImports = $this->importService->getImportHistory();

        return view('livewire.campaign-monitor.recent-imports', [
            'recentImports' => $recentImports
        ]);
    }
}
