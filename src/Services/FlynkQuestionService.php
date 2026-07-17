<?php

namespace Platform\FlynkConnector\Services;

use Illuminate\Support\Facades\Log;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Models\FlynkContainerEvent;
use Platform\FlynkConnector\Models\FlynkQuestion;
use Platform\Integrations\Exceptions\FlynkApiException;
use Platform\Integrations\Services\FlynkApiService;

/**
 * Kanal 2 (inbound): zieht FLYNK-Rückfragen (Tasks vom Typ "question") in Taiste
 * und schickt unsere Antworten zurück.
 */
class FlynkQuestionService
{
    public function __construct(
        protected FlynkContainerService $containers,
        protected FlynkApiService $api,
    ) {}

    /**
     * Zieht die Rückfragen (type=question) eines Containers aus FLYNK und
     * spiegelt sie lokal. Gibt Zähler zurück.
     *
     * @return array{pulled:int, open:int}
     */
    public function pull(FlynkContainer $container): array
    {
        if (! $container->external_id) {
            return ['pulled' => 0, 'open' => 0];
        }

        $connection = $this->containers->resolveConnection($container);

        try {
            $response = $this->api->listTasks($connection, [
                'project_id' => $container->external_id,
                'type' => 'question',
            ]);
        } catch (FlynkApiException $e) {
            Log::warning('FLYNK Rückfragen-Pull fehlgeschlagen', [
                'container_id' => $container->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $tasks = $response['data'] ?? $response ?? [];
        $pulled = 0; $open = 0;

        foreach ($tasks as $task) {
            $externalId = $task['id'] ?? $task['uuid'] ?? null;
            if (! $externalId) {
                continue;
            }

            $question = FlynkQuestion::firstOrNew(['external_id' => $externalId]);
            $question->fill([
                'team_id' => $container->team_id,
                'flynk_container_id' => $container->id,
                'title' => (string) ($task['title'] ?? 'Rückfrage'),
                'description' => $task['description'] ?? $question->description,
                'status' => $task['status'] ?? null,
                'priority' => $task['priority'] ?? null,
                'target_url' => $task['target_url'] ?? ($task['production_url'] ?? null),
                'assignee' => $task['assignee_name'] ?? (is_string($task['assignee'] ?? null) ? $task['assignee'] : null),
                'source' => $task['source'] ?? null,
                'flynk_created_at' => $task['created_at'] ?? null,
                'flynk_updated_at' => $task['updated_at'] ?? null,
                'last_pulled_at' => now(),
            ])->save();

            $pulled++;
            if ($question->isOpen()) {
                $open++;
            }
        }

        return ['pulled' => $pulled, 'open' => $open];
    }

    /** Zieht die Rückfragen aller aktiven, verbundenen Container eines Teams. */
    public function pullForTeam(int $teamId): array
    {
        $containers = FlynkContainer::query()
            ->where('team_id', $teamId)
            ->where('status', FlynkContainerStatus::ACTIVE->value)
            ->whereNotNull('external_id')
            ->get();

        $pulled = 0; $open = 0;
        foreach ($containers as $container) {
            try {
                $r = $this->pull($container);
                $pulled += $r['pulled'];
                $open += $r['open'];
            } catch (\Throwable $e) {
                // einzelner Container-Fehler stoppt den Lauf nicht
            }
        }

        return ['containers' => $containers->count(), 'pulled' => $pulled, 'open' => $open];
    }

    /**
     * Beantwortet eine Rückfrage: Kommentar an FLYNK + Status setzen, lokal als
     * beantwortet markieren.
     *
     * Payload-Default: { body: <text> } + Status "in_progress" — mit Koni final
     * abzustimmen.
     */
    public function answer(FlynkQuestion $question, string $text, ?int $userId = null): FlynkQuestion
    {
        $container = $question->container;
        $connection = $this->containers->resolveConnection($container);

        // Antwort als Kommentar an den FLYNK-Task
        $this->api->addTaskComment($connection, $question->external_id, ['body' => $text]);

        // Status auf "in Bearbeitung" — signalisiert FLYNK, dass wir geliefert haben
        try {
            $this->api->updateTask($connection, $question->external_id, ['status' => 'in_progress']);
        } catch (FlynkApiException $e) {
            // Kommentar ist raus; Status-Update ist best-effort
            Log::info('FLYNK Task-Status-Update nach Antwort fehlgeschlagen', ['task' => $question->external_id, 'error' => $e->getMessage()]);
        }

        $question->update([
            'answered_at' => now(),
            'answered_by_user_id' => $userId,
            'answer_text' => $text,
            'status' => 'in_progress',
        ]);

        FlynkContainerEvent::create([
            'flynk_container_id' => $container->id,
            'user_id' => $userId,
            'type' => 'answered',
            'title' => 'Rückfrage beantwortet',
            'message' => \Illuminate\Support\Str::limit($question->title, 80),
            'payload' => ['question_external_id' => $question->external_id],
        ]);

        return $question;
    }
}
