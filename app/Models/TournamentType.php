<?php

// ============================================
// File: app/Models/TournamentType.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'description',
        'is_national',
        'level',
        'required_level',
        'calendar_color',
        'min_referees',
        'max_referees',
        'sort_order',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_national' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
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
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get available for zones
     */
    public function isAvailableForZone($zoneId): bool
    {
        // For now, all tournament types are available to all zones
        // National types are marked with is_national = true
        // Zone-specific types are marked with is_national = false
        // Both should be visible to zone administrators
        return true;
    }
}
