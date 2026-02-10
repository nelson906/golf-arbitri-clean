<?php

// ============================================
// File: app/Models/Club.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int|null $zone_id
 * @property string|null $code
 * @property string|null $city
 * @property string|null $province
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Zone|null $zone
 * @property-read Collection<int, Tournament> $tournaments
 *
 * @method static Builder|Club visible(?User $user = null)
 * @method static Builder|Club active()
 * @method static Builder|Club ordered()
 */
class Club extends Model
{
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

    /**
     * RELAZIONI
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    /**
     * SCOPES
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Order by name
     */
    public function scopeOrdered($query)
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
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeVisible($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admin e national admin vedono tutto
        if (in_array($user->user_type, ['super_admin', 'national_admin'])) {
            return $query;
        }

        // Admin zonale vede solo circoli della propria zona
        if ($user->zone_id) {
            return $query->where('zone_id', $user->zone_id);
        }

        return $query;
    }
}
