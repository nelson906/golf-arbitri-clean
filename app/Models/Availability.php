<?php

// ============================================
// File: app/Models/Availability.php (se esiste)
// ============================================

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int|null $user_id
 * @property int|null $referee_id
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament|null $tournament
 * @property-read User|null $user
 * @property-read User|null $referee
 */
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
        if (Schema::hasColumn('availabilities', 'user_id')) {
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
