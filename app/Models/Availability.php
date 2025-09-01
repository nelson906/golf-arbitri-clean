<?php

// ============================================
// File: app/Models/Availability.php (se esiste)
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Availability extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'referee_id',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    /**
     * RELAZIONI
     */

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        if (\Schema::hasColumn('availabilities', 'user_id')) {
            return $this->belongsTo(User::class, 'user_id');
        } else {
            return $this->belongsTo(User::class, 'referee_id');
        }
    }

    // Alias
    public function referee()
    {
        return $this->user();
    }
}
