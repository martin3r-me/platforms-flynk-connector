<?php

namespace Platform\FlynkConnector\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\FlynkConnector\Models\FlynkQuestion;
use Platform\FlynkConnector\Services\FlynkQuestionService;
use Platform\FlynkConnector\Tools\Concerns\ResolvesFlynkTeam;

class AnswerFlynkQuestionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesFlynkTeam;

    public function getName(): string { return 'flynk-connector.questions.answer.POST'; }

    public function getDescription(): string
    {
        return 'POST /flynk-connector/questions/{id}/answer - Beantwortet eine FLYNK-Rückfrage: schreibt einen Kommentar an FLYNK und markiert sie als beantwortet.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id'     => ['type' => 'integer'],
                'question_id' => ['type' => 'integer', 'description' => 'ID der Rückfrage (alternativ external_id).'],
                'external_id' => ['type' => 'string', 'description' => 'FLYNK-Task-UUID (alternativ question_id).'],
                'text'        => ['type' => 'string', 'description' => 'ERFORDERLICH: Antworttext, geht als Kommentar an FLYNK.'],
            ],
            'required' => ['text'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) return $resolved['error'];
            $rootTeamId = (int) $resolved['root_team_id'];

            $text = trim((string) ($arguments['text'] ?? ''));
            if ($text === '') {
                return ToolResult::error('VALIDATION_ERROR', 'text ist erforderlich.');
            }

            $query = FlynkQuestion::where('team_id', $rootTeamId);
            if (! empty($arguments['question_id'])) {
                $question = $query->find((int) $arguments['question_id']);
            } elseif (! empty($arguments['external_id'])) {
                $question = $query->where('external_id', (string) $arguments['external_id'])->first();
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'question_id oder external_id erforderlich.');
            }

            if (! $question) {
                return ToolResult::error('NOT_FOUND', 'Rückfrage nicht gefunden.');
            }

            app(FlynkQuestionService::class)->answer($question, $text, $context->user?->id);

            return ToolResult::success([
                'id' => $question->id,
                'external_id' => $question->external_id,
                'answered_at' => $question->answered_at?->toIso8601String(),
                'message' => 'Antwort an FLYNK gesendet.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Antwort fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['flynk', 'questions', 'answer'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
