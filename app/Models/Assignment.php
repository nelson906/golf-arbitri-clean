<?php

// ============================================
// File: app/Models/Assignment.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'role',
        'assigned_at',
        'assigned_by',
        'status',
        'notes',
        'confirmed_at',
        'is_confirmed',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'assigned_at' => 'datetime',
        'is_confirmed' => 'boolean',
    ];

    /**
     * RELAZIONI
     */
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Relazione con l'utente/arbitro assegnato
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias per retrocompatibilità con codice legacy
     *
     * @deprecated Usare user() invece
     */
    public function referee()
    {
        return $this->user();
    }

    /**
     * Relazione con l'utente che ha creato l'assegnazione
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
