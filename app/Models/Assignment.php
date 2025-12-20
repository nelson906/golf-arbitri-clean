<?php

// ============================================
// File: app/Models/Assignment.php
// ============================================

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $user_id
 * @property string|null $role
 * @property Carbon|null $assigned_at
 * @property int|null $assigned_by
 * @property string|null $status
 * @property string|null $notes
 * @property Carbon|null $confirmed_at
 * @property bool $is_confirmed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament|null $tournament
 * @property-read User|null $user
 * @property-read User|null $referee
 * @property-read User|null $assignedBy
 */
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

    /**
     * Determina il nome del campo utente (user_id o referee_id)
     * per retrocompatibilità con database legacy
     */
    public static function getUserField(): string
    {
        return 'user_id';
    }
}
