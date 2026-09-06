<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read NotificationClause $clause  FK NOT NULL
 * @property-read TournamentNotification $tournamentNotification  FK NOT NULL
 */
class NotificationClauseSelection extends Model
{

    protected $fillable = [
        'tournament_notification_id',
        'placeholder_code',
        'clause_id',
    ];

    // Relazioni
    /**
     * @return BelongsTo<TournamentNotification, $this>
     */
    public function tournamentNotification(): BelongsTo
    {
        return $this->belongsTo(TournamentNotification::class);
    }

    /**
     * @return BelongsTo<NotificationClause, $this>
     */
    public function clause(): BelongsTo
    {
        return $this->belongsTo(NotificationClause::class);
    }
}
