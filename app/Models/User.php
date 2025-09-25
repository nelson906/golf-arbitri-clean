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
        'club_member', // nuovo campo
        'city',
        'phone',
        'active',
        'is_active',  // alcuni DB usano questo
        'gender',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
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

    return $this->belongsToMany(Tournament::class, 'assignments')
        ->withPivot('role', 'notes')  // <-- SENZA 'status'
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

    /**
     * Check if user has a specific role based on user_type
     */
    public function hasRole($role): bool
    {
        // Mapping dei ruoli al user_type per compatibilità
        $roleMapping = [
            'admin' => ['admin', 'national_admin', 'super_admin'],
            'zone_admin' => ['admin'], // zone_admin è un alias per admin
            'national_admin' => ['national_admin', 'super_admin'],
            'super_admin' => ['super_admin'],
            'referee' => ['referee'],
            'administrator' => ['admin', 'national_admin', 'super_admin'],
        ];

        // Se il ruolo non è mappato, verifica direttamente con user_type
        if (!isset($roleMapping[$role])) {
            return $this->user_type === $role;
        }

        // Verifica se user_type è incluso nel mapping del ruolo
        return in_array($this->user_type, $roleMapping[$role]);
    }

    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole($roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper methods for user type checking
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    public function isReferee(): bool
    {
        return $this->user_type === 'referee';
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    public function isNationalAdmin(): bool
    {
        return in_array($this->user_type, ['national_admin', 'super_admin']);
    }
}
