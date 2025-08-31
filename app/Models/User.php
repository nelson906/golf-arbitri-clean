<?php
// app/Models/User.php - AGGIORNATO per schema unificato

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'email', 'password', 'user_type',
        'referee_code', 'level', 'gender', 'certified_date', 'zone_id',
        'phone', 'city', 'address', 'postal_code', 'tax_code', 'badge_number',
        'first_certification_date', 'last_renewal_date', 'expiry_date', 'bio',
        'experience_years', 'qualifications', 'languages', 'specializations',
        'preferences', 'is_active', 'available_for_international',
        'total_tournaments', 'tournaments_current_year', 'profile_completed_at'
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'certified_date' => 'date',
        'first_certification_date' => 'date',
        'last_renewal_date' => 'date',
        'expiry_date' => 'date',
        'qualifications' => 'array',
        'languages' => 'array',
        'specializations' => 'array',
        'preferences' => 'array',
        'is_active' => 'boolean',
        'available_for_international' => 'boolean',
        'last_login_at' => 'datetime',
        'profile_completed_at' => 'datetime'
    ];

    // Relationships
    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function availabilities()
    {
        return $this->hasMany(Availability::class);
    }

   public function createdTournaments()
    {
        return $this->hasMany(Tournament::class, 'created_by');
    }


    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInZone($query, $zoneId)
    {
        return $query->where('zone_id', $zoneId);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    // Accessors
    public function getIsRefereeAttribute()
    {
        return $this->user_type === 'referee';
    }

    public function getIsAdminAttribute()
    {
        return in_array($this->user_type, ['admin', 'national_admin', 'super_admin']);
    }

    public function getCanManageZoneAttribute()
    {
        return in_array($this->user_type, ['admin', 'super_admin']);
    }

    public function getCanManageNationalAttribute()
    {
        return in_array($this->user_type, ['national_admin', 'super_admin']);
    }

    public function getFullNameAttribute()
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }
        return $this->name;
    }
public function careerHistory()
{
    return $this->hasOne(RefereeCareerHistory::class);
}

public function scopeReferees($query)
{
    return $query->where('user_type', 'referee');
}

public function scopeAdmins($query)
{
    return $query->whereIn('user_type', ['admin', 'super_admin', 'national_admin']);
}

    // Constants
    const USER_TYPES = [
        'super_admin' => 'Super Admin',
        'national_admin' => 'National Admin',
        'admin' => 'Zone Admin',
        'referee' => 'Referee'
    ];

    const LEVELS = [
        'Aspirante' => 'Aspirante',
        '1_livello' => 'Primo Livello',
        'Regionale' => 'Regionale',
        'Nazionale' => 'Nazionale',
        'Internazionale' => 'Internazionale',
        'Archivio' => 'Archivio'
    ];

    const GENDERS = [
        'male' => 'Maschile',
        'female' => 'Femminile',
        'mixed' => 'Misto'
    ];
}
