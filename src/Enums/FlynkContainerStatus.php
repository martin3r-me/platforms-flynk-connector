<?php

namespace Platform\FlynkConnector\Enums;

enum FlynkContainerStatus: string
{
    /** Lokal angelegt, noch nicht mit einem FLYNK-Project verbunden. */
    case DRAFT = 'draft';

    /** Mit einem FLYNK-Project verbunden (angelegt oder verknüpft). */
    case ACTIVE = 'active';

    /** Letzter FLYNK-Call ist fehlgeschlagen. */
    case ERROR = 'error';

    /** Abgemeldet — FLYNK-Project entkoppelt/entfernt. */
    case UNREGISTERED = 'unregistered';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::ACTIVE => 'Aktiv',
            self::ERROR => 'Fehler',
            self::UNREGISTERED => 'Abgemeldet',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'muted',
            self::ACTIVE => 'success',
            self::ERROR => 'danger',
            self::UNREGISTERED => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
