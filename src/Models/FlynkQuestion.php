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

    /** FLYNK-Status, die eine Rückfrage abschließen (nicht mehr „für uns offen"). */
    public const CLOSED_STATUSES = ['done', 'rejected'];

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

    /** Offen = noch nicht von uns beantwortet und in FLYNK nicht abgeschlossen. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('answered_at')->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function isOpen(): bool
    {
        return $this->answered_at === null && ! $this->isClosed();
    }
}
