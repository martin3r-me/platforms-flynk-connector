<?php

namespace Platform\FlynkConnector\Console\Commands;

use Illuminate\Console\Command;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Enums\FlynkPushStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkPush;
use Platform\FlynkConnector\Services\FlynkContainerService;
use Platform\FlynkConnector\Services\FlynkPushService;
use Platform\FlynkConnector\Services\FlynkQuestionService;

/**
 * Ein inbound-Pull: pro aktivem Container in einem Durchgang Meta auffrischen +
 * Rückfragen ziehen; danach offene Pushes mit Feedback aktualisieren.
 * Konsolidiert flynk:pull-questions + flynk:pull-feedback + Meta-Sync.
 */
class PullFlynk extends Command
{
    protected $signature = 'flynk:pull {--team= : Nur dieses Team (id)} {--container= : Nur dieser Container (id)}';

    protected $description = 'Inbound-Pull: FLYNK-Meta, Rückfragen und Push-Feedback in einem Durchgang.';

    public function handle(
        FlynkContainerService $containers,
        FlynkQuestionService $questions,
        FlynkPushService $pushes,
    ): int {
        $query = FlynkContainer::query()
            ->where('status', FlynkContainerStatus::ACTIVE->value)
            ->whereNotNull('external_id');

        if ($this->option('team')) {
            $query->where('team_id', (int) $this->option('team'));
        }
        if ($this->option('container')) {
            $query->where('id', (int) $this->option('container'));
        }

        $meta = 0; $openQuestions = 0; $failed = 0;

        foreach ($query->get() as $container) {
            try {
                $containers->syncMeta($container);
                $meta++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Meta {$container->name}: {$e->getMessage()}");
            }
            try {
                $r = $questions->pull($container);
                $openQuestions += $r['open'];
            } catch (\Throwable $e) {
                $this->error("Rückfragen {$container->name}: {$e->getMessage()}");
            }
        }

        // Offene Pushes mit Feedback aktualisieren
        $feedback = 0;
        $openPushes = FlynkPush::query()
            ->whereIn('status', FlynkPushStatus::openStates())
            ->with('container')
            ->when($this->option('container'), fn ($q) => $q->where('flynk_container_id', (int) $this->option('container')))
            ->limit(500)
            ->get();

        foreach ($openPushes as $push) {
            if ($this->option('team') && $push->container?->team_id !== (int) $this->option('team')) {
                continue;
            }
            try {
                $pushes->pullFeedback($push);
                $feedback++;
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $this->line("Fertig: {$meta} Meta, {$openQuestions} offene Rückfragen, {$feedback} Push-Feedback aktualisiert, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
