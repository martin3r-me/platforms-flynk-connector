<?php

namespace Platform\FlynkConnector\Console\Commands;

use Illuminate\Console\Command;
use Platform\FlynkConnector\Enums\FlynkPushStatus;
use Platform\FlynkConnector\Models\FlynkPush;
use Platform\FlynkConnector\Services\FlynkPushService;

/**
 * Holt Feedback zu offenen Pushes (gesendet/angenommen/in Verarbeitung) —
 * was FLYNK aus den gelieferten Infos gemacht hat.
 */
class PullFlynkFeedback extends Command
{
    protected $signature = 'flynk:pull-feedback {--limit=200 : Maximale Anzahl Pushes pro Lauf}';

    protected $description = 'Aktualisiert offene FLYNK-Pushes mit dem Feedback von FLYNK.';

    public function handle(FlynkPushService $service): int
    {
        $pushes = FlynkPush::query()
            ->whereIn('status', FlynkPushStatus::openStates())
            ->with('container')
            ->orderBy('sent_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $updated = 0; $failed = 0;

        foreach ($pushes as $push) {
            try {
                $service->pullFeedback($push);
                $updated++;
                $this->info("✓ {$push->uuid}: {$push->status->value}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ {$push->uuid}: {$e->getMessage()}");
            }
        }

        $this->line("Fertig: {$updated} aktualisiert, {$failed} Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
