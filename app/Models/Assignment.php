<?php

// ============================================
// File: app/Models/Assignment.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'referee_id',
        'role',
        'status',
        'notes',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
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
        if (\Schema::hasColumn('assignments', 'user_id')) {
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
