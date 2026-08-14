<?php

namespace Platform\FlynkConnector\Completeness;

use Platform\FlynkConnector\Enums\FlynkTier;

/**
 * Der Füllgrad eines Containers: Tore (boolesch) + Ideal-Score (graduell),
 * plus die Lückenliste als Arbeitsauftrag.
 *
 * Bewusst KEIN flaches %: ein Projekt bei "70%" kann das eine Impressumsfeld
 * fehlen, das den Go-Live blockt. Darum Gates + Score getrennt.
 */
readonly class CompletenessResult
{
    /** @param FieldStatus[] $statuses */
    public function __construct(public array $statuses) {}

    /** @return FieldStatus[] */
    public function forTier(FlynkTier $tier): array
    {
        return array_values(array_filter($this->statuses, fn ($s) => $s->spec->tier === $tier));
    }

    /** Ist das Tor dieser Stufe erfüllt (alle Felder erfüllt)? */
    public function gate(FlynkTier $tier): bool
    {
        foreach ($this->forTier($tier) as $status) {
            if (! $status->satisfied()) {
                return false;
            }
        }

        return true;
    }

    public function canCreate(): bool
    {
        return $this->gate(FlynkTier::REQUIRED);
    }

    /** Veröffentlichungsfähig = anlegbar UND alle Impressums-/Kontakt-Felder da. */
    public function canPublish(): bool
    {
        return $this->canCreate() && $this->gate(FlynkTier::PUBLISHABLE);
    }

    /** Zeigt-etwas = das inhaltliche Entwurf-Mindestset ist erfüllt. */
    public function canDraft(): bool
    {
        return $this->gate(FlynkTier::DRAFT);
    }

    /**
     * Die höchste ERREICHTE Bereitschafts-Stufe. Kumulativ gelesen, wie FLYNK §9:
     * online-fähig (Impressum) ist die Basis, "zeigt etwas" (Entwurf) kommt oben
     * drauf. Die Kette bricht am ersten nicht erfüllten Tor — die einzelnen
     * gate()-Booleans bleiben die Wahrheit, dies ist nur der Rollup.
     */
    public function stage(): FlynkTier
    {
        $reached = FlynkTier::REQUIRED;

        foreach (FlynkTier::gates() as $tier) {
            if (! $this->gate($tier)) {
                return $reached;
            }
            $reached = $tier;
        }

        // Alle Tore erfüllt → Ideal nur, wenn wirklich jedes Feld sitzt.
        return $this->score() === 100 ? FlynkTier::IDEAL : $reached;
    }

    /** [erfüllt, gesamt] für eine Stufe. */
    public function fraction(FlynkTier $tier): array
    {
        $fields = $this->forTier($tier);
        $done = count(array_filter($fields, fn ($s) => $s->satisfied()));

        return [$done, count($fields)];
    }

    /** Gesamt-Reichtum über ALLE Felder, in Prozent (0–100). */
    public function score(): int
    {
        $total = count($this->statuses);
        if ($total === 0) {
            return 0;
        }
        $done = count(array_filter($this->statuses, fn ($s) => $s->satisfied()));

        return (int) round($done / $total * 100);
    }

    /**
     * Nicht erfüllte Felder — der Arbeitsauftrag.
     *
     * @return FieldStatus[]
     */
    public function missing(?FlynkTier $tier = null): array
    {
        return array_values(array_filter(
            $this->statuses,
            fn ($s) => ! $s->satisfied() && ($tier === null || $s->spec->tier === $tier),
        ));
    }

    /** Fehlende Felder, die ein Tor blocken (required/publishable/draft). */
    public function blockers(): array
    {
        return array_values(array_filter(
            $this->statuses,
            fn ($s) => ! $s->satisfied() && $s->spec->tier->isGate(),
        ));
    }

    public function toArray(): array
    {
        $gates = [];
        foreach (FlynkTier::gates() as $tier) {
            [$done, $total] = $this->fraction($tier);
            $gates[$tier->value] = [
                'label'  => $tier->label(),
                'passed' => $this->gate($tier),
                'done'   => $done,
                'total'  => $total,
            ];
        }

        [$idealDone, $idealTotal] = $this->fraction(FlynkTier::IDEAL);

        return [
            'stage'  => $this->stage()->value,
            'score'  => $this->score(),
            'gates'  => $gates,
            'ideal'  => ['done' => $idealDone, 'total' => $idealTotal],
            'fields' => array_map(fn ($s) => $s->toArray(), $this->statuses),
        ];
    }
}
