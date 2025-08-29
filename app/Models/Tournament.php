<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'start_date', 'end_date', 'availability_deadline',
        'club_id', 'tournament_type_id', 'zone_id', 'status',
        'description', 'notes', 'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'availability_deadline' => 'datetime'
    ];

    // Relationships
    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function tournamentType()
    {
        return $this->belongsTo(TournamentType::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }

    // Scopes
    public function scopeInZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    // Constants
    const STATUSES = [
        'draft' => 'Bozza',
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'assigned' => 'Assegnato',
        'completed' => 'Completato',
        'cancelled' => 'Cancellato'
    ];
}
