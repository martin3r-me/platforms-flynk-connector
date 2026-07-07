<?php

namespace Platform\FlynkConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\FlynkConnector\Enums\FlynkContainerStatus;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Organization\Models\OrganizationDimensionLink;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Symfony\Component\Uid\UuidV7;

/**
 * Ein Container ist die Brücke [Organisations-Knoten] ⇄ [FLYNK-Project].
 * Auf unserer Seite gilt: genau ein Container = genau ein FLYNK-Project.
 */
class FlynkContainer extends Model
{
    use SoftDeletes;

    protected $table = 'flynk_containers';

    protected $fillable = [
        'uuid', 'team_id', 'user_id',
        'name', 'description', 'status',
        'owner_entity_id', 'integration_connection_id',
        'external_id', 'external_url',
        'metadata', 'last_synced_at',
    ];

    protected $casts = [
        'status' => FlynkContainerStatus::class,
        'metadata' => 'array',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do { $uuid = UuidV7::generate(); } while (self::where('uuid', $uuid)->exists());
                $model->uuid = $uuid;
            }
            if (! $model->user_id) { $model->user_id = Auth::id(); }
            if (! $model->team_id) { $model->team_id = Auth::user()?->currentTeamRelation?->getRootTeam()?->id; }
        });
    }

    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function connection(): BelongsTo { return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id'); }

    public function events(): HasMany { return $this->hasMany(FlynkContainerEvent::class, 'flynk_container_id'); }
    public function syncStates(): HasMany { return $this->hasMany(FlynkSyncState::class, 'flynk_container_id'); }

    /** Verortung an Organisations-Knoten via Dimension-Links (Plattform-Standard). */
    public function dimensionLinks(): MorphMany
    {
        return $this->morphMany(OrganizationDimensionLink::class, 'linkable');
    }

    /** Die Organisations-Knoten, an denen dieser Container hängt. */
    public function linkedEntities(): Collection
    {
        return EntityDimensionBridge::linksForLinkables(['flynk_container'], [$this->id], true)
            ->map(fn ($link) => $link->entity)
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Primärer Knoten (erste Verortung) — für Anzeige und Kontext-Auflösung. */
    public function primaryEntity(): ?OrganizationEntity
    {
        return $this->linkedEntities()->first();
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /** Ist der Container mit einem FLYNK-Project verbunden? */
    public function isLinked(): bool
    {
        return ! empty($this->external_id)
            && $this->status !== FlynkContainerStatus::UNREGISTERED;
    }
}
