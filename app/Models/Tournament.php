<?php

// ============================================
// File: app/Models/Tournament.php
// ============================================

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int|null $club_id
 * @property string $name
 * @property int|null $tournament_type_id
 * @property int|null $zone_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $availability_deadline
 * @property string $status
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
        'start_date' => 'datetime',
        'date' => 'date',
        'end_date' => 'datetime',
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

        'status' => self::STATUS_OPEN,

    ];

    /**
     * Tournament statuses
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Bozza',
        self::STATUS_OPEN => 'Aperto',
        self::STATUS_CLOSED => 'Chiuso',
        self::STATUS_ASSIGNED => 'Assegnato',
        self::STATUS_COMPLETED => 'Completato',
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
        if (Schema::hasTable('availabilities')) {
            return $this->hasMany(Availability::class);
        }

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
    public function scopeActive($query)
    {
        if (Schema::hasColumn($this->getTable(), 'status')) {
            return $query->where('status', 'active');
        }

        return $query;
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
     *
     * Regole:
     * - super_admin: vede tutto
     * - national_admin: solo tornei nazionali (is_national = true)
     * - admin zonale: solo tornei della propria zona
     * - referee nazionale/internazionale: propria zona + tornei nazionali
     * - referee 1_livello/regionale: solo propria zona
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeVisible($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // Nessun utente = nessun risultato
        }

        // Super admin vede tutto
        if ($user->user_type === 'super_admin') {
            return $query;
        }

        // National admin vede solo tornei nazionali
        if ($user->user_type === 'national_admin') {
            return $query->whereHas('tournamentType', fn ($q) => $q->where('is_national', true));
        }

        // Admin zonale vede solo la propria zona
        if ($user->user_type === 'admin') {
            return $query->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
        }

        // Referee
        if ($user->user_type === 'referee') {
            $isNational = in_array($user->level ?? '', ['Nazionale', 'Internazionale']);

            if ($isNational) {
                // Nazionale/Internazionale: propria zona + tornei nazionali
                return $query->where(function ($q) use ($user) {
                    $q->whereHas('club', fn ($sub) => $sub->where('zone_id', $user->zone_id))
                        ->orWhereHas('tournamentType', fn ($sub) => $sub->where('is_national', true));
                });
            } else {
                // 1_livello/Regionale: solo propria zona
                return $query->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
            }
        }

        // Fallback: filtra per zona se presente
        if ($user->zone_id) {
            return $query->whereHas('club', fn ($q) => $q->where('zone_id', $user->zone_id));
        }

        return $query;
    }

    /**
     * Verifica se il torneo è modificabile
     */
    public function isEditable()
    {
        // Logica semplice: modificabile se non ha una data o se la data è futura
        if (Schema::hasColumn('tournaments', 'date')) {
            return ! $this->date || $this->date >= now()->startOfDay();
        }

        // Se non c'è campo date, sempre modificabile
        return true;
    }

    /**
     * Check if tournament needs referees
     */
    public function needsReferees(): bool
    {
        $requiredReferees = $this->tournamentType->min_referees ?? 1;
        $assignedReferees = $this->assignments()->count();

        return $assignedReferees < $requiredReferees;
    }
}
