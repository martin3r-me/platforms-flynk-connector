<?php

namespace Platform\FlynkConnector\Completeness;

use Platform\FlynkConnector\Models\FlynkContainer;
use Platform\FlynkConnector\Services\FlynkContainerService;

/**
 * Wertet das FLYNK-Datenset gegen den assemblierten Roh-Kontext eines Containers
 * aus und liefert den Füllgrad (Tore + Ideal-Score + Lückenliste).
 *
 * Reine Präsenz-/Validitäts-Prüfung — KEINE semantische Verdichtung, KEIN LLM.
 * Die probe-Pfade zeigen in denselben Envelope, den wir an FLYNK pushen; darum
 * misst die Engine genau das, was FLYNK auch bekommt.
 */
class FlynkCompletenessEngine
{
    public function __construct(
        protected FlynkDatenset $datenset,
        protected FlynkContainerService $containers,
    ) {}

    /** Füllgrad für einen Container (baut den Kontext selbst zusammen). */
    public function forContainer(FlynkContainer $container): CompletenessResult
    {
        $context = $this->containers->assembleContext($container);

        // Container-Stammdaten als 'project'-Slice injizieren (REQUIRED-Tor).
        $context['project'] = [
            'name'               => $container->name,
            'external_reference' => $container->uuid,
        ];

        return $this->evaluate($context);
    }

    /** Wertet einen bereits zusammengebauten Roh-Kontext aus (rein, testbar). */
    public function evaluate(array $context): CompletenessResult
    {
        $statuses = [];

        foreach ($this->datenset->fields() as $spec) {
            $value   = $spec->resolve($context);
            $present = $this->isPresent($value);
            $valid   = $present && $this->validate($value, $spec->validator);

            $statuses[] = new FieldStatus($spec, $present, $valid, $value);
        }

        return new CompletenessResult($statuses);
    }

    /** Nicht null, nicht leerer String, nicht leeres Array. */
    protected function isPresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /** Validiert einen präsenten Wert gegen die Regel (null = reine Präsenz genügt). */
    protected function validate(mixed $value, ?string $rule): bool
    {
        if ($rule === null) {
            return true;
        }

        // list_min:N — Liste mit mindestens N Einträgen.
        if (str_starts_with($rule, 'list_min:')) {
            $min = (int) substr($rule, strlen('list_min:'));
            return is_array($value) && count($value) >= $min;
        }

        return match ($rule) {
            'hex'   => is_string($value) && (bool) preg_match('/^#?[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', trim($value)),
            'email' => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false,
            'url'   => is_string($value) && filter_var(trim($value), FILTER_VALIDATE_URL) !== false,
            // Langer Google-Maps-Place-Link (trägt Koordinate/cid) — NICHT der g.page-Kurzlink.
            'maps_link' => is_string($value)
                && str_contains($value, '/maps/')
                && ! str_contains($value, 'g.page'),
            // Öffnungszeiten: Map mit mindestens einem gesetzten Tag.
            'map_non_empty' => is_array($value) && array_filter($value) !== [],
            default => true,
        };
    }
}
