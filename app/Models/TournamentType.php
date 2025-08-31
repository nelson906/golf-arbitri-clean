<?php

// ============================================
// File: app/Models/TournamentType.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TournamentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_national',
        'description',
    ];

    protected $casts = [
        'is_national' => 'boolean',
    ];

    /**
     * RELAZIONI
     */

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
}
