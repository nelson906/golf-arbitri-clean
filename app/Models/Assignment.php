<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'tournament_id', 'role', 'notes', 'assigned_by', 'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // Constants
    const ROLES = [
        'Direttore di Torneo' => 'Direttore di Torneo',
        'Arbitro' => 'Arbitro',
        'Osservatore' => 'Osservatore'
    ];
}
