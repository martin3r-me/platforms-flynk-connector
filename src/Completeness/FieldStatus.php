<?php

namespace Platform\FlynkConnector\Completeness;

/**
 * Ergebnis der Prüfung eines einzelnen FieldSpec gegen den Roh-Kontext.
 */
readonly class FieldStatus
{
    public function __construct(
        public FieldSpec $spec,
        public bool $present,   // probe hat etwas Nicht-Leeres aufgelöst
        public bool $valid,     // present UND Validator bestanden (bzw. kein Validator)
        public mixed $value = null,
    ) {}

    /** Zählt dieses Feld als erfüllt? */
    public function satisfied(): bool
    {
        return $this->present && $this->valid;
    }

    /** Da, aber ungültig (z.B. g.page-Kurzlink, nur 1 statt 2 Leistungen). */
    public function invalid(): bool
    {
        return $this->present && ! $this->valid;
    }

    public function toArray(): array
    {
        return [
            'key'         => $this->spec->key,
            'label'       => $this->spec->label,
            'tier'        => $this->spec->tier->value,
            'module'      => $this->spec->module,
            'node'        => $this->spec->node,
            'present'     => $this->present,
            'valid'       => $this->valid,
            'satisfied'   => $this->satisfied(),
            'consequence' => $this->spec->consequence,
        ];
    }
}
