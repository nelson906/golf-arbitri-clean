<?php

// ============================================
// File: app/Models/Club.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $zone_id
 * @property string|null $code
 * @property string|null $city
 * @property string|null $province
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Zone $zone  FK NOT NULL
 * @property-read Collection<int, Tournament> $tournaments
 *
 * @method static Builder|Club visible(?User $user = null)
 * @method static Builder|Club active()
 * @method static Builder|Club ordered()
 */
class Club extends Model
{
    /** @use HasFactory<\Database\Factories\ClubFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'zone_id',
        'code',
        'city',
        'province',
        'address',
        'phone',
        'email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── RELAZIONI ───────────────────────────────────────────────────
    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return HasMany<Tournament, $this>
     */
    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    // ── SCOPES ──────────────────────────────────────────────────────
    /**
     * @param  Builder<Club>  $query
     * @return Builder<Club>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order by name
     *
     * @param  Builder<Club>  $query
     * @return Builder<Club>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * Scope per filtrare circoli visibili all'utente.
     *
     * Regole:
     * - super_admin/national_admin: vedono tutto
     * - admin zonale: solo circoli della propria zona
     *
     * @param  Builder<Club>  $query
     * @return Builder<Club>
     */
    public function scopeVisible(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admin e national admin vedono tutto
        if ($user->isNationalAdmin()) {
            return $query;
        }

        // Admin zonale vede solo circoli della propria zona
        if ($user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }
}
