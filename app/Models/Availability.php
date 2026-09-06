<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected $fillable = [
        'tournament_id',
        'user_id',
        'notes',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    // ── RELAZIONI ───────────────────────────────────────────────────
    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias per retrocompatibilità
     *
     * @deprecated Usare user() invece
     * @return BelongsTo<User, $this>
     */
    public function referee(): BelongsTo
    {
        return $this->user();
    }
}
