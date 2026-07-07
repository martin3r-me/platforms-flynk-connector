<?php

namespace Platform\FlynkConnector\Livewire\Container;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkContainerEvent;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Organization\Services\EntityDimensionBridge;

class Index extends Component
{
    public string $search = '';
    public string $statusFilter = '';

    public bool $modalShow = false;

    public array $form = [
        'name' => '',
        'description' => '',
        'integration_connection_id' => '',
        'link_mode' => 'create',   // create | link
        'external_id' => '',
    ];

    /** Aus FLYNK geladene Projects (für den Verknüpfen-Picker). */
    public array $availableProjects = [];

    protected $queryString = [
        'search'       => ['except' => ''],
        'statusFilter' => ['except' => ''],
    ];

    public function updatedSearch(): void { unset($this->containers); }
    public function updatedStatusFilter(): void { unset($this->containers); }

    protected function rules(): array
    {
        return [
            'form.name'                      => ['required', 'string', 'max:255'],
            'form.description'               => ['nullable', 'string'],
            'form.integration_connection_id' => ['nullable', 'integer', 'exists:integration_connections,id'],
            'form.link_mode'                 => ['required', 'in:create,link'],
            'form.external_id'               => ['nullable', 'required_if:form.link_mode,link', 'string', 'max:255'],
        ];
    }

    protected function rootTeamId(): ?int
    {
        return Auth::user()?->currentTeamRelation?->getRootTeam()?->id;
    }

    #[Computed]
    public function containers()
    {
        $q = FlynkContainer::query()
            ->with(['connection'])
            ->where('team_id', $this->rootTeamId());

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $q->where(fn ($qq) => $qq->where('name', 'like', $term)->orWhere('description', 'like', $term));
        }

        if ($this->statusFilter !== '') {
            $q->where('status', $this->statusFilter);
        }

        return $q->orderBy('name')->get();
    }

    /** container_id => [Knoten-Namen] — gebündelt über alle Container aufgelöst. */
    #[Computed]
    public function entityNamesByContainer(): array
    {
        $ids = $this->containers->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        $map = [];
        foreach (EntityDimensionBridge::linksForLinkables(['flynk_container'], $ids, true) as $link) {
            if ($link->entity) {
                $map[$link->linkable_id][] = $link->entity->name;
            }
        }

        return $map;
    }

    #[Computed]
    public function flynkConnections()
    {
        $integration = Integration::where('key', 'flynk')->first();
        if (! $integration) {
            return collect();
        }

        return IntegrationConnection::where('integration_id', $integration->id)
            ->where('owner_user_id', Auth::id())
            ->orderByDesc('is_default')
            ->get();
    }

    #[Computed]
    public function recentActivity()
    {
        $teamId = $this->rootTeamId();

        return FlynkContainerEvent::query()
            ->whereHas('container', fn ($q) => $q->where('team_id', $teamId))
            ->with(['container', 'user'])
            ->orderByDesc('created_at')
            ->take(15)
            ->get();
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('form', 'availableProjects');
        $this->form['link_mode'] = 'create';
        $this->modalShow = true;
    }

    /** Lädt die verfügbaren FLYNK-Projects der gewählten Connection (Verknüpfen-Picker). */
    public function loadProjects(): void
    {
        $connectionId = $this->form['integration_connection_id'] !== ''
            ? (int) $this->form['integration_connection_id']
            : null;

        $connection = $connectionId ? IntegrationConnection::find($connectionId) : null;

        if (! $connection) {
            $this->dispatch('toast', message: 'Bitte zuerst eine FLYNK-Verbindung wählen.');
            return;
        }

        try {
            $api = app(\Platform\Integrations\Services\FlynkApiService::class);
            $response = $api->listProjects($connection);
            $rows = $response['data'] ?? $response ?? [];

            $this->availableProjects = collect($rows)
                ->map(fn ($p) => [
                    'id' => $p['id'] ?? $p['uuid'] ?? null,
                    'name' => $p['name'] ?? ($p['title'] ?? '—'),
                ])
                ->filter(fn ($p) => $p['id'] !== null)
                ->values()
                ->all();

            if (empty($this->availableProjects)) {
                $this->dispatch('toast', message: 'Keine FLYNK-Projects gefunden.');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'FLYNK-Projects konnten nicht geladen werden: '.$e->getMessage());
        }
    }

    public function store(FlynkContainerService $service): void
    {
        $data = $this->validate()['form'];

        $container = FlynkContainer::create([
            'name'                      => trim($data['name']),
            'description'               => $data['description'] !== '' ? $data['description'] : null,
            'integration_connection_id' => $data['integration_connection_id'] !== '' ? (int) $data['integration_connection_id'] : null,
            'status'                    => FlynkContainerStatus::DRAFT,
        ]);

        try {
            if ($data['link_mode'] === 'link') {
                $service->linkRemote($container, $data['external_id']);
                $this->dispatch('toast', message: 'Container mit FLYNK-Project verknüpft.');
            } else {
                $service->createRemote($container);
                $this->dispatch('toast', message: 'Container in FLYNK angelegt.');
            }
        } catch (\Throwable $e) {
            // Container bleibt als Entwurf/Fehler bestehen — Nutzer kann es im Detail erneut versuchen.
            $this->dispatch('toast', message: 'FLYNK-Aktion fehlgeschlagen: '.$e->getMessage());
        }

        $this->modalShow = false;
        unset($this->containers, $this->recentActivity);
    }

    public function delete(int $id): void
    {
        $container = FlynkContainer::where('team_id', $this->rootTeamId())->find($id);
        $container?->delete();
        unset($this->containers, $this->recentActivity);
        $this->dispatch('toast', message: 'Container gelöscht');
    }

    public function render()
    {
        return view('flynk-connector::livewire.container.index')
            ->layout('platform::layouts.app');
    }
}
