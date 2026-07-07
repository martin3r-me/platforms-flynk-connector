<?php

namespace Platform\FlynkConnector\Enums;

enum FlynkPushStatus: string
{
    /** Angelegt, noch nicht gesendet. */
    case PENDING = 'pending';

    /** An FLYNK gesendet. */
    case SENT = 'sent';

    /** Von FLYNK angenommen (202). */
    case ACCEPTED = 'accepted';

    /** In FLYNK verarbeitet (Content erzeugt). */
    case PROCESSED = 'processed';

    /** Verarbeitung läuft. */
    case PROCESSING = 'processing';

    /** Fehlgeschlagen. */
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Ausstehend',
            self::SENT => 'Gesendet',
            self::ACCEPTED => 'Angenommen',
            self::PROCESSING => 'In Verarbeitung',
            self::PROCESSED => 'Verarbeitet',
            self::FAILED => 'Fehler',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'muted',
            self::SENT, self::ACCEPTED, self::PROCESSING => 'info',
            self::PROCESSED => 'success',
            self::FAILED => 'danger',
        };
    }

    /** Pushes, die noch auf Feedback warten. */
    public static function openStates(): array
    {
        return [self::SENT->value, self::ACCEPTED->value, self::PROCESSING->value];
    }
}
