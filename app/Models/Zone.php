<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'description',
        'is_national',
        'is_active'
    ];

    protected $casts = [
        'is_national' => 'boolean',
        'is_active' => 'boolean',
    ];
}
