<?php
// ============================================
// File: app/Models/User.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'zone_id',
        'referee_code',
        'level',
        'phone',
        'active',
        'is_active',  // alcuni DB usano questo
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
        'is_active' => 'boolean',
    ];
    /**
     * Referee levels
     */
    const LEVEL_ASPIRANTE = 'aspirante';
    const LEVEL_PRIMO_LIVELLO = 'primo_livello';
    const LEVEL_REGIONALE = 'regionale';
    const LEVEL_NAZIONALE = 'nazionale';
    const LEVEL_INTERNAZIONALE = 'internazionale';

    const LEVELS = [
        self::LEVEL_ASPIRANTE => 'Aspirante',
        self::LEVEL_PRIMO_LIVELLO => 'Primo Livello',
        self::LEVEL_REGIONALE => 'Regionale',
        self::LEVEL_NAZIONALE => 'Nazionale',
        self::LEVEL_INTERNAZIONALE => 'Internazionale',
    ];

    /**
     * Referee categories
     */
    const CATEGORY_MASCHILE = 'maschile';
    const CATEGORY_FEMMINILE = 'femminile';
    const CATEGORY_MISTO = 'misto';

    const CATEGORIES = [
        self::CATEGORY_MASCHILE => 'Maschile',
        self::CATEGORY_FEMMINILE => 'Femminile',
        self::CATEGORY_MISTO => 'Misto',
    ];

    /**
     * RELAZIONI
     */

    // Zona
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    // Assegnazioni
    public function assignments()
    {
        $foreignKey = \Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';
        return $this->hasMany(Assignment::class, $foreignKey);
    }

    // Disponibilità
    public function availabilities()
    {
        if (\Schema::hasTable('availabilities')) {
            $foreignKey = \Schema::hasColumn('availabilities', 'user_id') ? 'user_id' : 'referee_id';
            return $this->hasMany(Availability::class, $foreignKey);
        }
        // Return empty collection if table doesn't exist
        return $this->newCollection();
    }

    // Tornei (attraverso assignments)
    public function tournaments()
    {
        $userField = \Schema::hasColumn('assignments', 'user_id') ? 'user_id' : 'referee_id';

        return $this->belongsToMany(Tournament::class, 'assignments', $userField, 'tournament_id')
            ->withPivot('role', 'status', 'notes')
            ->withTimestamps();
    }

    // NON c'è una relazione 'referee' su User stesso!
    // Se il codice cerca $user->referee, probabilmente è un errore

    /**
     * SCOPES
     */

    public function scopeReferees($query)
    {
        return $query->where('user_type', 'referee');
    }

    public function scopeActive($query)
    {
        if (\Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where('is_active', true);
        } elseif (\Schema::hasColumn($this->getTable(), 'active')) {
            return $query->where('active', true);
        }
        return $query;
    }

    /**
     * ACCESSORS
     */

    // Normalizza active/is_active
    public function getIsActiveAttribute()
    {
        if (isset($this->attributes['is_active'])) {
            return $this->attributes['is_active'];
        }
        if (isset($this->attributes['active'])) {
            return $this->attributes['active'];
        }
        return true; // default
    }
}
