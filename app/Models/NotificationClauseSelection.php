<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationClauseSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_notification_id',
        'placeholder_code',
        'clause_id',
    ];

    // Relazioni
    public function tournamentNotification()
    {
        return $this->belongsTo(TournamentNotification::class);
    }

    public function clause()
    {
        return $this->belongsTo(NotificationClause::class);
    }
}
