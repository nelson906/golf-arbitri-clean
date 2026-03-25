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

/**
 * @property int $id
 * @property int|null $club_id
 * @property string $name
 * @property int|null $tournament_type_id
 * @property int|null $zone_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $availability_deadline
 * @property TournamentStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club|null $club
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
 * @method static Builder|Tournament visible(?User $user = null)
 * @method static Builder|Tournament upcoming()
 * @method static Builder|Tournament active()
 */
class Tournament extends Model
{
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
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    // Relazione con zona (attraverso il club)
    public function zone()
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
        if (isset($this->attributes['zone_id'])) {
            return $this->attributes['zone_id'];
        }

        // Fallback: carica il club per ottenere la zona
        if ($this->club_id) {
            $club = $this->club()->first();

            return $club ? $club->zone_id : null;
        }

        return null;
    }

    // Relazione con tipo torneo
    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    // Alias
    public function type()
    {
        return $this->tournamentType();
    }

    // Assegnazioni
    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    // Arbitri assegnati
    public function referees()
    {
        return $this->belongsToMany(User::class, 'assignments', 'tournament_id', 'user_id')
            ->withPivot('role', 'notes')
            ->withTimestamps();
    }

    // Disponibilità dichiarate
    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }

    // Notifiche del torneo
    public function notifications()
    {
        return $this->hasMany(TournamentNotification::class);
    }

    // Ultima notifica (relazione comoda) - per gare zonali
    public function notification()
    {
        return $this->hasOne(TournamentNotification::class)->latestOfMany();
    }

    // Notifica CRC per gare nazionali (arbitri designati)
    public function crcNotification()
    {
        return $this->hasOne(TournamentNotification::class)
            ->where('notification_type', 'crc_referees')
            ->latestOfMany();
    }

    // Notifica ZONA per gare nazionali (osservatori)
    public function zoneNotification()
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

    /**
     * SCOPES
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TournamentStatus::activeValues());
    }

    /**
     * Scope a query to only include upcoming tournaments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', Carbon::today());
    }

    /**
     * Scope per filtrare tornei visibili all'utente.
     * Delega a TournamentVisibility (single source of truth).
     *
     * @see \App\Support\TournamentVisibility per le regole complete
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
        // Non modificabile se l'Enum dice che lo stato non è editabile
        if (! $this->status->isEditable()) {
            return false;
        }

        // Modificabile solo se la data di inizio è futura o non impostata
        return ! $this->start_date || $this->start_date->gte(now()->startOfDay());
    }

    /**
     * Get the required number of referees from tournament type
     */
    public function getRequiredRefereesAttribute(): int
    {
        return $this->tournamentType?->min_referees ?? 1;
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
