<?php

// ============================================
// File: app/Models/Assignment.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'referee_id',
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

    // Relazione principale con user
    public function user()
    {
        if (Schema::hasColumn('assignments', 'user_id')) {
            return $this->belongsTo(User::class, 'user_id');
        } else {
            return $this->belongsTo(User::class, 'referee_id');
        }
    }

    // Alias per retrocompatibilità
    public function referee()
    {
        return $this->user();
    }
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    /**
     * ACCESSORS
     */

    // Ottieni user_id indipendentemente dal nome del campo
    public function getUserIdAttribute()
    {
        if (isset($this->attributes['user_id'])) {
            return $this->attributes['user_id'];
        }
        if (isset($this->attributes['referee_id'])) {
            return $this->attributes['referee_id'];
        }
        return null;
    }
}
