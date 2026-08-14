<?php

namespace Platform\FlynkConnector\Enums;

/**
 * Bereitschafts-Stufen des FLYNK-Anforderungsprofils (Datenset-Doku §9).
 * Reihenfolge = Eskalation: REQUIRED < PUBLISHABLE < DRAFT < IDEAL.
 *
 * REQUIRED/PUBLISHABLE/DRAFT sind Tore (boolesch: erfüllt oder nicht).
 * IDEAL ist der graduelle Reichtums-Score obendrauf.
 */
enum FlynkTier: string
{
    /** Ohne das gibt es kein Projekt (name, external_reference). */
    case REQUIRED = 'required';

    /** Pflicht für eine veröffentlichungsfähige Seite (Impressum + primary_contact). */
    case PUBLISHABLE = 'publishable';

    /** Mindestset für einen Entwurf, der etwas zeigt. */
    case DRAFT = 'draft';

    /** Alles Weitere — macht die Seite besser, blockt aber nichts. */
    case IDEAL = 'ideal';

    public function label(): string
    {
        return match ($this) {
            self::REQUIRED => 'Anlegbar',
            self::PUBLISHABLE => 'Go-Live-fähig',
            self::DRAFT => 'Entwurf-tauglich',
            self::IDEAL => 'Ideal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::REQUIRED => 'danger',
            self::PUBLISHABLE => 'warning',
            self::DRAFT => 'info',
            self::IDEAL => 'muted',
        };
    }

    /** Ist dieses Tier ein hartes Tor (nicht der graduelle Ideal-Score)? */
    public function isGate(): bool
    {
        return $this !== self::IDEAL;
    }

    /** Die Tore in Eskalations-Reihenfolge. */
    public static function gates(): array
    {
        return [self::REQUIRED, self::PUBLISHABLE, self::DRAFT];
    }
}
