<?php

namespace Platform\FlynkConnector\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Models\User;
use Symfony\Component\Uid\UuidV7;

/**
 * Kanal 2 (inbound): eine FLYNK-Rückfrage (Task vom Typ "question"), bei der
 * wir am Zug sind. Lokaler Spiegel; wird per Pull aus FLYNK aktualisiert.
 */
class FlynkQuestion extends Model
{
    use SoftDeletes;

    protected $table = 'flynk_questions';

    /**
     * Handshake mit FLYNK (statusbasiert): FLYNK setzt einen question-Task auf
     * "on_hold", wenn der Ball bei uns liegt (offene Rückfrage). Nach unserer
     * Antwort setzen wir ihn auf "new" (Ball zurück bei FLYNK). "on_hold" ist
     * also unser einziges „wir sind am Zug"-Signal — ping-pong-fähig.
     */
    public const OPEN_STATUS = 'on_hold';

    /** Status, den wir setzen, wenn wir geantwortet haben (Ball zurück zu FLYNK). */
    public const ANSWERED_STATUS = 'new';

    protected $fillable = [
        'uuid', 'team_id', 'flynk_container_id',
        'external_id', 'title', 'description', 'status', 'priority',
        'target_url', 'assignee', 'source',
        'flynk_created_at', 'flynk_updated_at',
        'answered_at', 'answered_by_user_id', 'answer_text',
        'metadata', 'last_pulled_at',
    ];

    protected $casts = [
        'flynk_created_at' => 'datetime',
        'flynk_updated_at' => 'datetime',
        'answered_at' => 'datetime',
        'last_pulled_at' => 'datetime',
        'metadata' => 'array',
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
    public function answeredBy(): BelongsTo { return $this->belongsTo(User::class, 'answered_by_user_id'); }

    /** Offen = FLYNK hat den Task auf "on_hold" gesetzt → der Ball liegt bei uns. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN_STATUS);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN_STATUS;
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }
}
