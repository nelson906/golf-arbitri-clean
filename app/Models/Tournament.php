<?php
// ============================================
// File: app/Models/Tournament.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'club_id',
        'tournament_type_id',
        'date',           // se esiste
        'status',         // se esiste
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
 ];
    /**
     * Tournament statuses
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_ASSIGNED = 'assigned';
    const STATUS_COMPLETED = 'completed';

    const STATUSES = [
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

    // Getter per zone_id (per retrocompatibilità)
    public function getZoneIdAttribute()
    {
        return $this->club ? $this->club->zone_id : null;
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
        $userField = \Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';

        return $this->belongsToMany(User::class, 'assignments', 'tournament_id', $userField)
            ->withPivot('role', 'status', 'notes')
            ->withTimestamps();
    }

    // Disponibilità dichiarate
    public function availabilities()
    {
        if (\Schema::hasTable('availabilities')) {
            return $this->hasMany(Availability::class);
        }
        return $this->hasMany(Availability::class);
    }

    /**
     * SCOPES
     */

    public function scopeActive($query)
    {
        if (\Schema::hasColumn($this->getTable(), 'status')) {
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
 * Verifica se il torneo è modificabile
 */
public function isEditable()
{
    // Logica semplice: modificabile se non ha una data o se la data è futura
    if (\Schema::hasColumn('tournaments', 'date')) {
        return !$this->date || $this->date >= now()->startOfDay();
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
