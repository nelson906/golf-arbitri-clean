<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

        // Scopes
    public function scopeReferees($query) {
        return $query->where('user_type', 'referee');
    }

    public function scopeActive($query) {
        return $query->where('is_active', true);
    }

    public function scopeInZone($query, $zoneId) {
        return $query->where('zone_id', $zoneId);
    }

    // Relazioni
    public function zone() {
        return $this->belongsTo(Zone::class);
    }

    public function availabilities() {
        return $this->hasMany(Availability::class);
    }

    public function assignments() {
        return $this->hasMany(Assignment::class);
    }

    // Accessors
    public function getIsRefereeAttribute() {
        return $this->user_type === 'referee';
    }

    public function getCanManageZoneAttribute() {
        return in_array($this->user_type, ['zone_admin', 'super_admin']);
    }

    public function getCanManageCrcAttribute() {
        return in_array($this->user_type, ['national_admin', 'super_admin']);
    }

}
