<?php

namespace Platform\FlynkConnector\Livewire\Questions;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\FlynkConnector\Models\FlynkQuestion;
use Platform\FlynkConnector\Services\FlynkQuestionService;

/**
 * Zentrale Rückfragen-Inbox: alle FLYNK-Rückfragen, bei denen wir am Zug sind —
 * über alle Container hinweg.
 */
class Index extends Component
{
    public ?int $answeringId = null;
    public string $answerText = '';
    public bool $showAnswered = false;

    protected function rootTeamId(): ?int
    {
        return Auth::user()?->currentTeamRelation?->getRootTeam()?->id;
    }

    #[Computed]
    public function questions()
    {
        $q = FlynkQuestion::query()
            ->where('team_id', $this->rootTeamId())
            ->with(['container', 'answeredBy']);

        if ($this->showAnswered) {
            $q->whereNotNull('answered_at');
        } else {
            $q->open();
        }

        return $q->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByDesc('flynk_created_at')
            ->get();
    }

    #[Computed]
    public function openCount(): int
    {
        return FlynkQuestion::where('team_id', $this->rootTeamId())->open()->count();
    }

    public function pullNow(FlynkQuestionService $service): void
    {
        try {
            $r = $service->pullForTeam((int) $this->rootTeamId());
            $this->dispatch('toast', message: "Rückfragen abgerufen: {$r['open']} offen.");
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Abruf fehlgeschlagen: '.$e->getMessage());
        }
        unset($this->questions, $this->openCount);
    }

    public function startAnswer(int $id): void
    {
        $this->answeringId = $id;
        $this->answerText = '';
    }

    public function cancelAnswer(): void
    {
        $this->answeringId = null;
        $this->answerText = '';
    }

    public function submitAnswer(FlynkQuestionService $service): void
    {
        $this->validate(['answerText' => ['required', 'string', 'min:2']]);

        $question = FlynkQuestion::where('team_id', $this->rootTeamId())->find($this->answeringId);
        if (! $question) {
            return;
        }

        try {
            $service->answer($question, $this->answerText, Auth::id());
            $this->dispatch('toast', message: 'Antwort an FLYNK gesendet.');
            $this->cancelAnswer();
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: 'Antwort fehlgeschlagen: '.$e->getMessage());
        }
        unset($this->questions, $this->openCount);
    }

    public function render()
    {
        return view('flynk-connector::livewire.questions.index')
            ->layout('platform::layouts.app');
    }
}
