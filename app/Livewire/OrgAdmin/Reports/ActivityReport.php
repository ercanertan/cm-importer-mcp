<?php

namespace App\Livewire\OrgAdmin\Reports;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.org-admin')]
#[Title('Activity Report')]
class ActivityReport extends Component
{
    public function render()
    {
        return view('livewire.org-admin.reports.activity-report');
    }
}
