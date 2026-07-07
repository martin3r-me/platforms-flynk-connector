<?php

namespace Platform\FlynkConnector\Console\Commands;

use Illuminate\Console\Command;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Services\FlynkPushService;

/**
 * Nächtlicher Kontext-Push: geht alle aktiven, verbundenen Container durch und
 * pusht ihren Kontext an FLYNK — delta-bewusst (nur bei Änderung).
 */
class PushFlynkContexts extends Command
{
    protected $signature = 'flynk:push-contexts {--force : Auch ohne Delta senden} {--container= : Nur diesen Container (id)}';

    protected $description = 'Pusht den Kontext aller aktiven FLYNK-Container (delta-bewusst).';

    public function handle(FlynkPushService $service): int
    {
        $query = FlynkContainer::query()
            ->where('status', FlynkContainerStatus::ACTIVE->value)
            ->whereNotNull('external_id');

        if ($this->option('container')) {
            $query->where('id', (int) $this->option('container'));
        }

        $containers = $query->get();
        $pushed = 0; $skipped = 0; $failed = 0;

        foreach ($containers as $container) {
            try {
                $result = $service->push($container, (bool) $this->option('force'));
                if ($result['skipped'] ?? false) {
                    $skipped++;
                    $this->line("~ {$container->name}: kein Delta");
                } else {
                    $pushed++;
                    $this->info("✓ {$container->name}: Push {$result['push']->uuid}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ {$container->name}: {$e->getMessage()}");
            }
        }

        $this->line("Fertig: {$pushed} gepusht, {$skipped} ohne Delta, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
