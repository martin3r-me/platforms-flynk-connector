<?php

namespace Platform\FlynkConnector\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\FlynkConnector\Models\FlynkContainer;

class Sidebar extends Component
{
    public function render()
    {
        $user = Auth::user();
        $teamId = $user?->currentTeamRelation?->getRootTeam()?->id;

        $containers = $teamId
            ? FlynkContainer::where('team_id', $teamId)->orderBy('name')->get()
            : collect();

        return view('flynk-connector::livewire.sidebar', [
            'containers' => $containers,
        ]);
    }
}
