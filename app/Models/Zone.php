<?php

// ============================================
// File: app/Models/Zone.php
// ============================================

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * RELAZIONI
     */
    public function clubs()
    {
        return $this->hasMany(Club::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function referees()
    {
        return $this->hasMany(User::class)->where('user_type', 'referee');
    }

    public function tournaments()
    {
        return $this->hasManyThrough(Tournament::class, Club::class);
    }
}
