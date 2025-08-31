<?php

// ============================================
// File: app/Models/TournamentType.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TournamentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_national',
        'description',
    ];

    protected $casts = [
        'is_national' => 'boolean',
    ];

    /**
     * RELAZIONI
     */

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }
        /**
     * Scope a query to only include active types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    /**
     * Scope a query to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('name'); // Solo nome
    }

    /**
     * Get available for zones
     */
    public function isAvailableForZone($zoneId): bool
    {
        if ($this->is_national || $this->visibility_zones === 'all') {
            return true;
        }

        if (is_array($this->visibility_zones)) {
            return in_array($zoneId, $this->visibility_zones);
        }

        return false;
    }

}
