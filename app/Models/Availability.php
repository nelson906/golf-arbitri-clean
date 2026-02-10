<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament|null $tournament
 * @property-read User|null $user
 */
class Availability extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'user_id',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias per retrocompatibilità
     *
     * @deprecated Usare user() invece
     */
    public function referee()
    {
        return $this->user();
    }
}
