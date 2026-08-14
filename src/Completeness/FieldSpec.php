<?php

namespace Platform\FlynkConnector\Completeness;

use Closure;
use Platform\FlynkConnector\Enums\FlynkTier;

/**
 * Ein Eintrag der FLYNK-Datenset-Spezifikation: ein Ziel-Feld aus dem
 * Anforderungsprofil, seine Bereitschafts-Stufe, wo im Roh-Envelope es zu
 * finden ist (probe) und was fehlt, wenn es fehlt (consequence).
 *
 * WICHTIG (Design-Entscheidung): Dies ist eine MESS-LINSE, kein Transformer.
 * Der Connector schreibt die FLYNK-Felder NICHT selbst aus — die semantische
 * Verdichtung (usp, tone_*, pain_point) bleibt FLYNKs Aufgabe. Wir prüfen nur
 * die PRÄSENZ des Rohmaterials im Envelope.
 */
readonly class FieldSpec
{
    /**
     * @param string          $key         FLYNK-Zielfeldname (z.B. 'legal_register_court')
     * @param FlynkTier        $tier        Bereitschafts-Stufe
     * @param string          $label       Menschliche Bezeichnung (de)
     * @param string          $consequence FLYNK-Folgetext, wenn das Feld fehlt
     * @param string          $module      Quell-Modul: connector|brand|seo|crm|commerce|—
     * @param string          $node        Ort im Baum: self (Website-Knoten) | customer (Parent) | —
     * @param string|Closure  $probe       Dot-Pfad in den Roh-Kontext ODER Closure(array $context): mixed
     * @param string|null     $validator   hex|email|url|maps_link|list_min:N|null (null = reine Präsenz)
     */
    public function __construct(
        public string $key,
        public FlynkTier $tier,
        public string $label,
        public string $consequence,
        public string $module,
        public string $node,
        public string|Closure $probe,
        public ?string $validator = null,
    ) {}

    /** Löst den Roh-Wert dieses Feldes aus dem Kontext auf. */
    public function resolve(array $context): mixed
    {
        return $this->probe instanceof Closure
            ? ($this->probe)($context)
            : data_get($context, $this->probe);
    }
}
