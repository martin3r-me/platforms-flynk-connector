<?php

namespace Platform\FlynkConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Mapping lokaler Datensätze ⇄ FLYNK-Objekte inkl. Idempotenz/Delta-Info.
 *
 * Phase-2-Gerüst (Daten-Abo). In v1 bewusst leer, aber bereits inbound-ready
 * modelliert (direction outbound|inbound).
 */
class FlynkSyncState extends Model
{
    use SoftDeletes;

    protected $table = 'flynk_sync_states';

    protected $fillable = [
        'uuid', 'team_id', 'flynk_container_id',
        'syncable_type', 'syncable_id',
        'external_id', 'external_type',
        'direction', 'status', 'payload_hash',
        'last_pushed_at', 'last_pulled_at', 'last_error', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_pushed_at' => 'datetime',
        'last_pulled_at' => 'datetime',
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

    public function container(): BelongsTo { return $this->belongsTo(FlynkContainer::class, 'flynk_container_id'); }
    public function syncable(): MorphTo { return $this->morphTo(); }
}
