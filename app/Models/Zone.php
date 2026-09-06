<?php

// ============================================
// File: app/Models/Zone.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $description
 * @property bool|null $is_national
 * @property bool|null $is_active
 * @property-read Collection<int, Club> $clubs
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, User> $referees
 * @property-read Collection<int, Tournament> $tournaments
 */
class Zone extends Model
{
    /** @use HasFactory<\Database\Factories\ZoneFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'description',
        'is_national',
        'is_active',
    ];

    // ── SCOPES ──────────────────────────────────────────────────────
    /**
     * @param  Builder<Zone>  $query
     * @return Builder<Zone>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── RELAZIONI ───────────────────────────────────────────────────
    /**
     * @return HasMany<Club, $this>
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function referees(): HasMany
    {
        return $this->hasMany(User::class)->where('user_type', 'referee');
    }

    /**
     * @return HasManyThrough<Tournament, Club, $this>
     */
    public function tournaments(): HasManyThrough
    {
        return $this->hasManyThrough(Tournament::class, Club::class);
    }
}
