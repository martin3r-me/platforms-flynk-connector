<?php

namespace Platform\FlynkConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Platform\FlynkConnector\Enums\FlynkPushStatus;
use Symfony\Component\Uid\UuidV7;

/**
 * Ein Push: eine an FLYNK gesendete Kontext-Lieferung mit eigener UUID.
 * Trägt den gesendeten Envelope, den Delta-Hash und das FLYNK-Feedback
 * (was FLYNK aus den Infos gemacht hat). Gehört zum Container.
 */
class FlynkPush extends Model
{
    protected $table = 'flynk_pushes';

    protected $fillable = [
        'uuid', 'flynk_container_id', 'status',
        'payload', 'payload_hash', 'response',
        'sent_at', 'feedback_at',
    ];

    protected $casts = [
        'status' => FlynkPushStatus::class,
        'payload' => 'array',
        'response' => 'array',
        'sent_at' => 'datetime',
        'feedback_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do { $uuid = UuidV7::generate(); } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
        });
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(FlynkContainer::class, 'flynk_container_id');
    }

    /** Von FLYNK gemeldete Ergebnisse (Documents/Seiten/…), sofern vorhanden. */
    public function results(): array
    {
        return $this->response['results'] ?? [];
    }
}
