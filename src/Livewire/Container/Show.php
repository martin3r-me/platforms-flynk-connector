<?php

namespace Platform\FlynkConnector\Livewire\Container;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

class Show extends Component
{
    public FlynkContainer $container;

    public array $form = [
        'name' => '',
        'description' => '',
        'integration_connection_id' => '',
    ];

    /** Für erneutes Verknüpfen (bei Entwurf/abgemeldet). */
    public string $relinkExternalId = '';

    public function mount(FlynkContainer $container): void
    {
        abort_unless($container->team_id === $this->rootTeamId(), 404);

        $this->container = $container->load(['connection', 'user']);
        $this->loadForm();
    }

    protected function rootTeamId(): ?int
    {
        return Auth::user()?->currentTeamRelation?->getRootTeam()?->id;
    }

    public function loadForm(): void
    {
        $this->form = [
            'name'                      => $this->container->name,
            'description'               => $this->container->description ?? '',
            'integration_connection_id' => (string) ($this->container->integration_connection_id ?? ''),
        ];
    }

    #[Computed]
    public function isDirty(): bool
    {
        return $this->form['name'] !== ($this->container->name ?? '')
            || $this->form['description'] !== ($this->container->description ?? '')
            || $this->form['integration_connection_id'] != ($this->container->integration_connection_id ?? '');
    }

    #[Computed]
    public function events()
    {
        return $this->container->events()->with('user')->orderByDesc('created_at')->take(30)->get();
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

    public function save(): void
    {
        $data = $this->validate([
            'form.name'                      => ['required', 'string', 'max:255'],
            'form.description'               => ['nullable', 'string'],
            'form.integration_connection_id' => ['nullable', 'integer', 'exists:integration_connections,id'],
        ])['form'];

        $this->container->update([
            'name'                      => trim($data['name']),
            'description'               => $data['description'] !== '' ? $data['description'] : null,
            'integration_connection_id' => $data['integration_connection_id'] !== '' ? (int) $data['integration_connection_id'] : null,
        ]);

        $this->container->refresh();
        $this->dispatch('toast', message: 'Container gespeichert');
    }

    /** "anlegen" — neues FLYNK-Project für einen Entwurf erstellen. */
    public function createRemote(FlynkContainerService $service): void
    {
        try {
            $service->createRemote($this->container);
            $this->dispatch('toast', message: 'In FLYNK angelegt.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Anlegen fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    /** "verknüpfen" — bestehendes FLYNK-Project anbinden. */
    public function relink(FlynkContainerService $service): void
    {
        $this->validate(['relinkExternalId' => ['required', 'string', 'max:255']]);

        try {
            $service->linkRemote($this->container, $this->relinkExternalId);
            $this->relinkExternalId = '';
            $this->dispatch('toast', message: 'Mit FLYNK-Project verknüpft.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Verknüpfen fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    /** "update mit Daten". */
    public function pushUpdate(FlynkContainerService $service): void
    {
        try {
            $service->pushUpdate($this->container);
            $this->dispatch('toast', message: 'Daten an FLYNK gepusht.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Update fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    /** "abmelden". */
    public function unregister(FlynkContainerService $service): void
    {
        try {
            $service->unregister($this->container);
            $this->dispatch('toast', message: 'Container abgemeldet.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Abmelden fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    /** FLYNK-Projekt-Metadaten ziehen und cachen. */
    public function syncMeta(FlynkContainerService $service): void
    {
        try {
            $service->syncMeta($this->container);
            $this->dispatch('toast', message: 'FLYNK-Meta aktualisiert.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Meta-Abruf fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    public function testConnection(FlynkContainerService $service): void
    {
        try {
            $result = $service->testConnection($this->container);
            $this->dispatch('toast', message: $result['message'] ?? 'Test abgeschlossen.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Test fehlgeschlagen: '.$e->getMessage());
        }
        $this->refreshState();
    }

    public function delete()
    {
        $this->container->delete();
        $this->dispatch('toast', message: 'Container gelöscht');

        return redirect()->route('flynk-connector.containers.index');
    }

    protected function refreshState(): void
    {
        $this->container->refresh()->load(['connection', 'user']);
        unset($this->events);
        $this->loadForm();
    }

    public function render()
    {
        return view('flynk-connector::livewire.container.show')
            ->layout('platform::layouts.app');
    }
}
