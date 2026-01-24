<?php

// ============================================
// File: app/Models/User.php
// ============================================

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string $password
 * @property string|null $user_type
 * @property int|null $zone_id
 * @property string|null $referee_code
 * @property string|null $level
 * @property string|null $club_member
 * @property string|null $city
 * @property string|null $phone
 * @property bool $is_active
 * @property string|null $gender
 * @property string|null $notes
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Zone|null $zone
 * @property-read Collection<int, Assignment> $assignments
 * @property-read Collection<int, Availability> $availabilities
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read RefereeCareerHistory|null $careerHistory
 * @property-read int|null $assignments_count
 *
 * @method static Builder|User visible(?User $user = null)
 * @method static Builder|User referees()
 * @method static Builder|User active()
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use MustVerifyEmailTrait;
    use Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
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
    public const LEVEL_ASPIRANTE = 'aspirante';

    public const LEVEL_PRIMO_LIVELLO = 'primo_livello';

    public const LEVEL_REGIONALE = 'regionale';

    public const LEVEL_NAZIONALE = 'nazionale';

    public const LEVEL_INTERNAZIONALE = 'internazionale';

    public const LEVELS = [
        self::LEVEL_ASPIRANTE => 'Aspirante',
        self::LEVEL_PRIMO_LIVELLO => 'Primo Livello',
        self::LEVEL_REGIONALE => 'Regionale',
        self::LEVEL_NAZIONALE => 'Nazionale',
        self::LEVEL_INTERNAZIONALE => 'Internazionale',
    ];

    /**
     * Referee categories
     */
    public const CATEGORY_MASCHILE = 'maschile';

    public const CATEGORY_FEMMINILE = 'femminile';

    public const CATEGORY_MISTO = 'misto';

    public const CATEGORIES = [
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
        return $this->hasMany(Assignment::class, 'user_id');
    }

    // Disponibilità
    public function availabilities()
    {
        return $this->hasMany(Availability::class, 'user_id');
    }

    // Tornei (attraverso assignments)
    public function tournaments()
    {
        return $this->belongsToMany(Tournament::class, 'assignments', 'user_id', 'tournament_id')
            ->withPivot('role', 'notes')
            ->withTimestamps();
    }

    // Storico carriera
    public function careerHistory()
    {
        return $this->hasOne(RefereeCareerHistory::class);
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
        if (Schema::hasColumn($this->getTable(), 'is_active')) {
            return $query->where('is_active', true);
        } elseif (Schema::hasColumn($this->getTable(), 'active')) {
            return $query->where('active', true);
        }

        return $query;
    }

    /**
     * Scope per filtrare utenti/arbitri visibili all'utente.
     *
     * Regole:
     * - super_admin: vede tutto
     * - national_admin: solo arbitri nazionali/internazionali
     * - admin zonale: solo arbitri della propria zona
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeVisible($query, ?self $user = null)
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admin vede tutto
        if ($user->user_type === 'super_admin') {
            return $query;
        }

        // National admin vede solo arbitri nazionali/internazionali
        if ($user->user_type === 'national_admin') {
            return $query->whereIn('level', ['Nazionale', 'Internazionale']);
        }

        // Admin zonale vede solo arbitri della propria zona
        if ($user->user_type === 'admin' && $user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
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
        if (! isset($roleMapping[$role])) {
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
