<?php

// ============================================
// File: app/Models/Zone.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'description',
        'is_national',
        'is_active',
    ];

    /**
     * RELAZIONI
     */
    public function clubs()
    {
        return $this->hasMany(Club::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function referees()
    {
        return $this->hasMany(User::class)->where('user_type', 'referee');
    }

    public function tournaments()
    {
        return $this->hasManyThrough(Tournament::class, Club::class);
    }
}
