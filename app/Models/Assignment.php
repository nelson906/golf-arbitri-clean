<?php

// ============================================
// File: app/Models/Assignment.php
// ============================================

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property string|null $role
 * @property Carbon|null $assigned_at
 * @property int $assigned_by
 * @property string|null $status
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property bool $is_confirmed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament  FK NOT NULL
 * @property-read User $user  FK NOT NULL
 * @property-read User $referee  alias di user
 * @property-read User $assignedBy  FK NOT NULL
 *
 * Attributi NON persistiti: copiati dallo User collegato da
 * AssignmentController::getAvailableReferees() per comodita' della view.
 * @property string|null $name
 * @property string|null $email
 * @property string|null $referee_code
 * @property string|null $level
 */
class Assignment extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentFactory> */
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

    // ── RELAZIONI ───────────────────────────────────────────────────
    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Relazione con l'utente/arbitro assegnato
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias per retrocompatibilità con codice legacy
     *
     * @deprecated Usare user() invece
     * @return BelongsTo<User, $this>
     */
    public function referee(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Relazione con l'utente che ha creato l'assegnazione
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Determina il nome del campo utente (user_id o referee_id)
     * per retrocompatibilità con database legacy
     */
    public static function getUserField(): string
    {
        return 'user_id';
    }
}
