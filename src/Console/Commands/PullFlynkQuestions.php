<?php

namespace Platform\FlynkConnector\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Models\Team;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Services\FlynkQuestionService;

/**
 * Zieht FLYNK-Rückfragen (Tasks vom Typ "question") aller aktiven Container in
 * die zentrale Inbox. Nächtlich + on-demand.
 */
class PullFlynkQuestions extends Command
{
    protected $signature = 'flynk:pull-questions {--team= : Nur dieses Team (id)}';

    protected $description = 'Synchronisiert FLYNK-Rückfragen (type=question) in die Taiste-Inbox.';

    public function handle(FlynkQuestionService $service): int
    {
        $teamIds = $this->option('team')
            ? [(int) $this->option('team')]
            : FlynkContainer::query()->distinct()->pluck('team_id')->all();

        $totalOpen = 0; $failed = 0;

        foreach ($teamIds as $teamId) {
            try {
                $r = $service->pullForTeam((int) $teamId);
                $totalOpen += $r['open'];
                $this->info("Team {$teamId}: {$r['containers']} Container, {$r['pulled']} Rückfragen, {$r['open']} offen");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Team {$teamId}: {$e->getMessage()}");
            }
        }

        $this->line("Fertig: {$totalOpen} offene Rückfragen gesamt, {$failed} Team-Fehler.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
