<?php

// ============================================
// File: app/Models/User.php
// ============================================

namespace App\Models;

use App\Enums\RefereeLevel;
use App\Enums\UserType;
use Carbon\Carbon;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property string $password
 * @property UserType|null $user_type
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
        'is_active',
        'gender',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'user_type'         => UserType::class,
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Referee levels
     */
    public const LEVEL_ASPIRANTE = 'Aspirante';

    public const LEVEL_PRIMO_LIVELLO = '1_livello';

    public const LEVEL_REGIONALE = 'Regionale';

    public const LEVEL_NAZIONALE = 'Nazionale';

    public const LEVEL_INTERNAZIONALE = 'Internazionale';

    public const LEVEL_ARCHIVIO = 'Archivio';

    public const LEVELS = [
        self::LEVEL_ASPIRANTE => 'Aspirante',
        self::LEVEL_PRIMO_LIVELLO => 'Primo Livello',
        self::LEVEL_REGIONALE => 'Regionale',
        self::LEVEL_NAZIONALE => 'Nazionale',
        self::LEVEL_INTERNAZIONALE => 'Internazionale',
        self::LEVEL_ARCHIVIO => 'Archivio',
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
    public function scopeReferees(Builder $query): Builder
    {
        return $query->where('user_type', UserType::Referee->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope per filtrare utenti/arbitri visibili all'utente.
     *
     * Regole:
     * - super_admin:    vede tutto
     * - national_admin: solo arbitri nazionali/internazionali
     * - admin zonale:   solo arbitri della propria zona
     */
    public function scopeVisible(Builder $query, ?self $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user || ! $user->user_type) {
            return $query->whereRaw('1 = 0');
        }

        $type = $user->user_type; // già castato a UserType

        // Super admin vede tutto
        if ($type->seesEverything()) {
            return $query;
        }

        // National admin vede solo arbitri nazionali/internazionali
        if ($type === UserType::NationalAdmin) {
            $nationalLevels = array_map(
                fn (RefereeLevel $l) => $l->value,
                array_filter(RefereeLevel::cases(), fn (RefereeLevel $l) => $l->isNational())
            );

            return $query->whereIn('level', $nationalLevels);
        }

        // Admin zonale vede solo arbitri della propria zona
        if ($type === UserType::ZoneAdmin && $user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }

    // ── Metodi di ruolo ──────────────────────────────────────────────────────

    /**
     * L'utente è un qualsiasi tipo di admin (non referee).
     * Delega all'Enum UserType per essere il single source of truth.
     */
    public function isAdmin(): bool
    {
        return $this->user_type?->isAdmin() ?? false;
    }

    public function isReferee(): bool
    {
        return $this->user_type === UserType::Referee;
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === UserType::SuperAdmin;
    }

    public function isNationalAdmin(): bool
    {
        return $this->user_type?->isNational() ?? false;
    }

    public function isZoneAdmin(): bool
    {
        return $this->user_type === UserType::ZoneAdmin;
    }

    /**
     * L'arbitro ha accesso ai tornei nazionali (livello Nazionale o Internazionale).
     */
    public function isNationalReferee(): bool
    {
        if (! $this->isReferee() || ! $this->level) {
            return false;
        }

        $level = RefereeLevel::tryFrom($this->level);

        return $level?->isNational() ?? false;
    }

    /**
     * Verifica compatibilità con codice legacy che usa stringhe di ruolo.
     *
     * @deprecated Preferire i metodi tipizzati isAdmin(), isReferee(), ecc.
     */
    public function hasRole(string $role): bool
    {
        return match ($role) {
            'super_admin'   => $this->isSuperAdmin(),
            'national_admin' => $this->isNationalAdmin(),
            'zone_admin'                            => $this->isZoneAdmin(),
            'admin', 'administrator'                => $this->isAdmin(),
            'referee'       => $this->isReferee(),
            default         => $this->user_type?->value === $role,
        };
    }

    /**
     * @deprecated Preferire i metodi tipizzati isAdmin(), isReferee(), ecc.
     *
     * @param  string|string[]  $roles
     */
    public function hasAnyRole(string|array $roles): bool
    {
        foreach ((array) $roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
