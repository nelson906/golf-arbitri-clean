<?php

// ============================================
// File: app/Models/Tournament.php
// ============================================

namespace App\Models;

use App\Enums\TournamentStatus;
use App\Support\TournamentVisibility;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property int $id
 * @property int|null $club_id  null = torneo T.B.A. (circolo da assegnare)
 * @property string $name
 * @property int $tournament_type_id
 * @property int|null $zone_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date  a schema e' NOT NULL, ma
 *   tests/Unit/Services/AssignmentDateConflictNullEndDateTest lo mette a null
 *   in memoria per coprire il fallback di datesOverlap(): resta nullable
 * @property Carbon $availability_deadline
 * @property TournamentStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club|null $club  null sui tornei T.B.A. e finche' la relation non e' caricata
 *   su model non persistiti o forzata con setRelation() — vedi
 *   tests/Unit/Services/NotificationPreviewNullClubTest.php (FIX C3)
 * @property-read Zone|null $zone
 * @property-read TournamentType|null $tournamentType
 * @property-read Collection<int, Assignment> $assignments
 * @property-read Collection<int, Availability> $availabilities
 * @property-read Collection<int, User> $referees
 * @property-read string|null $date_range
 * @property-read string|null $status_color
 * @property-read int $required_referees
 * @property-read Collection<int, User> $assignedReferees
 *
 * Attributo NON persistito, calcolato e iniettato da
 * TournamentControllerTrait::addDeadlineInfo() prima del render della view.
 * @property int|null $days_until_deadline
 *
 * @method static Builder|Tournament visible(?User $user = null)
 * @method static Builder|Tournament upcoming()
 * @method static Builder|Tournament active()
 */
class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'club_id',
        'name',
        'tournament_type_id',
        'zone_id', // Manteniamo per compatibilità - viene popolato automaticamente da club->zone_id
        'start_date',
        'end_date',
        'availability_deadline',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date'            => 'datetime',
        'end_date'              => 'datetime',
        'availability_deadline' => 'datetime',
        'status'                => TournamentStatus::class,
    ];

    /**
     * Attributi da appendere automaticamente quando il model viene serializzato
     */
    protected $appends = [
        'zone_id',
    ];

    /**
     * Default attribute values.

     * I nuovi tornei sono visibili (open) di default.

     * Solo se specificato esplicitamente saranno in bozza (draft).
     */
    protected $attributes = [
        'status' => 'open', // TournamentStatus::Open — il cast converte automaticamente
    ];

    /**
     * Tournament statuses
     *
     * @deprecated Usare \App\Enums\TournamentStatus al posto di queste costanti.
     *             Queste rimangono solo per retrocompatibilità con codice legacy.
     */
    /** @deprecated Use TournamentStatus::Draft->value */
    public const STATUS_DRAFT = 'draft';

    /** @deprecated Use TournamentStatus::Open->value */
    public const STATUS_OPEN = 'open';

    /** @deprecated Use TournamentStatus::Closed->value */
    public const STATUS_CLOSED = 'closed';

    /** @deprecated Use TournamentStatus::Assigned->value */
    public const STATUS_ASSIGNED = 'assigned';

    /** @deprecated Use TournamentStatus::Completed->value */
    public const STATUS_COMPLETED = 'completed';

    /** @deprecated Use TournamentStatus::Cancelled->value */
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @deprecated Usare TournamentStatus::selectOptions() o TournamentStatus::cases().
     */
    public const STATUSES = [
        self::STATUS_DRAFT => 'Bozza',
        self::STATUS_OPEN => 'Aperto',
        self::STATUS_CLOSED => 'Chiuso',
        self::STATUS_ASSIGNED => 'Assegnato',
        self::STATUS_COMPLETED => 'Completato',
        self::STATUS_CANCELLED => 'Annullato',
    ];

    /**
     * RELAZIONI
     */

    // Relazione con circolo
    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    // Relazione con zona (attraverso il club)
    /**
     * @return HasOneThrough<Zone, Club, $this>
     */
    public function zone(): HasOneThrough
    {
        return $this->hasOneThrough(
            Zone::class,
            Club::class,
            'id',        // Foreign key on clubs table
            'id',        // Foreign key on zones table
            'club_id',   // Local key on tournaments table
            'zone_id'    // Local key on clubs table
        );
    }

    /**
     * Getter per zone_id - preferisce il valore del club se disponibile
     *
     * IMPORTANTE: zone_id è salvato nel DB per performance, ma questo accessor
     * garantisce che venga sempre letto dal club associato quando disponibile,
     * mantenendo la sincronizzazione. Durante il salvataggio, zone_id viene
     * popolato dal controller in base al club selezionato.
     *
     * @return int|null
     */
    public function getZoneIdAttribute()
    {
        // Se il club è già caricato, usa sempre quello (source of truth)
        if ($this->relationLoaded('club') && $this->club) {
            return $this->club->zone_id;
        }

        // Se c'è un valore nel DB, usalo (evita query extra)
        $stored = $this->attributes['zone_id'] ?? null;
        if (is_int($stored) || (is_string($stored) && is_numeric($stored))) {
            return (int) $stored;
        }

        // Fallback: carica il club per ottenere la zona
        if ($this->club_id) {
            $club = $this->club()->first();

            return $club ? $club->zone_id : null;
        }

        return null;
    }

    // Relazione con tipo torneo
    /**
     * @return BelongsTo<TournamentType, $this>
     */
    public function tournamentType(): BelongsTo
    {
        return $this->belongsTo(TournamentType::class);
    }

    // Alias
    /**
     * @return BelongsTo<TournamentType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->tournamentType();
    }

    // Assegnazioni
    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    // Arbitri assegnati
    /**
     * @return BelongsToMany<User, $this>
     */
    public function referees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'assignments', 'tournament_id', 'user_id')
            ->withPivot('role', 'notes')
            ->withTimestamps();
    }

    // Disponibilità dichiarate
    /**
     * @return HasMany<Availability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    // Notifiche del torneo
    /**
     * @return HasMany<TournamentNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(TournamentNotification::class);
    }

    // Ultima notifica (relazione comoda) - per gare zonali
    /**
     * @return HasOne<TournamentNotification, $this>
     */
    public function notification(): HasOne
    {
        return $this->hasOne(TournamentNotification::class)->latestOfMany();
    }

    // Notifica CRC per gare nazionali (arbitri designati)
    /**
     * @return HasOne<TournamentNotification, $this>
     */
    public function crcNotification(): HasOne
    {
        return $this->hasOne(TournamentNotification::class)
            ->where('notification_type', 'crc_referees')
            ->latestOfMany();
    }

    // Notifica ZONA per gare nazionali (osservatori)
    /**
     * @return HasOne<TournamentNotification, $this>
     */
    public function zoneNotification(): HasOne
    {
        return $this->hasOne(TournamentNotification::class)
            ->where('notification_type', 'zone_observers')
            ->latestOfMany();
    }

    // Verifica se ha notifiche nazionali inviate
    public function hasNationalNotifications(): bool
    {
        return $this->notifications()
            ->whereIn('notification_type', ['crc_referees', 'zone_observers'])
            ->where('status', 'sent')
            ->exists();
    }

    // ── SCOPES ──────────────────────────────────────────────────────
    /**
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TournamentStatus::activeValues());
    }

    /**
     * Scope a query to only include upcoming tournaments.
     *
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>=', Carbon::today());
    }

    /**
     * Scope per filtrare tornei visibili all'utente.
     * Delega a TournamentVisibility (single source of truth).
     *
     * @see \App\Support\TournamentVisibility per le regole complete
     * @param  Builder<Tournament>  $query
     * @return Builder<Tournament>
     */
    public function scopeVisible(Builder $query, ?User $user = null): Builder
    {
        return TournamentVisibility::apply($query, $user);
    }

    /**
     * Verifica se il torneo è modificabile.
     * Delega la logica di stato all'Enum TournamentStatus.
     */
    public function isEditable(): bool
    {
        // Un torneo è sempre modificabile dall'admin, tranne se Completed o Cancelled.
        // Rimosso il vincolo sulla data: un admin deve poter correggere dati anche dopo lo svolgimento.
        return $this->status->isEditable();
    }

    /**
     * Verifica se il torneo è modificabile dall'utente specificato.
     * Il super_admin bypassa qualunque vincolo di stato — può modificare anche
     * tornei Completati o Annullati per correggere dati storici.
     */
    public function isEditableBy(\App\Models\User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->isEditable();
    }

    /**
     * Get the required number of referees from tournament type
     */
    public function getRequiredRefereesAttribute(): int
    {
        return $this->tournamentType->min_referees ?? 1;
    }

    /**
     * Check if tournament needs referees.
     * Se la relazione è già eager-loaded usa la collection in memoria (zero query extra).
     */
    public function needsReferees(): bool
    {
        $assignedCount = $this->relationLoaded('assignments')
            ? $this->assignments->count()
            : ($this->assignments_count ?? $this->assignments()->count());

        return $assignedCount < $this->required_referees;
    }

    // ── Notifica nazionale ────────────────────────────────────────────────────

    /**
     * Verifica se il torneo ha notifiche nazionali inviate (usa i tipi tipizzati).
     */
    public function hasNationalNotificationsSent(): bool
    {
        return $this->notifications()
            ->whereIn('notification_type', ['crc_referees', 'zone_observers'])
            ->where('status', 'sent')
            ->exists();
    }
}
