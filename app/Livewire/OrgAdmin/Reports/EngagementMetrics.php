<?php

namespace App\Livewire\OrgAdmin\Reports;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Engagement Metrics')]
class EngagementMetrics extends Component
{
    public function render()
    {
        return view('livewire.org-admin.reports.engagement-metrics');
    }
}
