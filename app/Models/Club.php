<?php
// ============================================
// File: app/Models/Club.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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
        'website',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
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
        if (Schema::hasColumn($this->getTable(), 'active')) {
            return $query->where('active', true);
        }
        return $query;
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
     * @param Builder $query
     * @param User|null $user
     * @return Builder
     */
    public function scopeVisible($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
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
