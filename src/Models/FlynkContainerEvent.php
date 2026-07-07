<?php

namespace Platform\FlynkConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\User;

/**
 * Audit-/Event-Log-Eintrag je Container — speist die Activity-Sidebar.
 */
class FlynkContainerEvent extends Model
{
    protected $table = 'flynk_container_events';

    protected $fillable = [
        'flynk_container_id', 'user_id',
        'type', 'title', 'message', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->user_id) { $model->user_id = Auth::id(); }
        });
    }

    public function container(): BelongsTo { return $this->belongsTo(FlynkContainer::class, 'flynk_container_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Heroicon + Farbe je Event-Typ (für die Activity-Sidebar). */
    public function icon(): string
    {
        return match ($this->type) {
            'created', 'linked' => 'heroicon-o-link',
            'updated' => 'heroicon-o-arrow-path',
            'unregistered' => 'heroicon-o-x-circle',
            'error' => 'heroicon-o-exclamation-triangle',
            'test' => 'heroicon-o-signal',
            default => 'heroicon-o-information-circle',
        };
    }

    public function color(): string
    {
        return match ($this->type) {
            'created', 'linked' => 'success',
            'updated' => 'info',
            'unregistered' => 'warning',
            'error' => 'danger',
            default => 'muted',
        };
    }
}
