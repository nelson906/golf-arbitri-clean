<?php
// ============================================
// File: app/Models/Club.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        if (\Schema::hasColumn($this->getTable(), 'active')) {
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

}
