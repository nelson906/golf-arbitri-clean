<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tournament_id
 * @property string $status
 * @property int $total_recipients
 * @property string|null $referee_list
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property int|null $sent_by
 * @property array<array-key, mixed>|null $details
 * @property array<array-key, mixed>|null $templates_used
 * @property string|null $error_message
 * @property array<array-key, mixed>|null $attachments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $recipients_list
 * @property-read array $stats
 * @property-read string $status_formatted
 * @property-read string $templates_formatted
 * @property-read string $time_ago
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $individualNotifications
 * @property-read int|null $individual_notifications_count
 * @property-read \App\Models\User|null $sentBy
 * @property-read \App\Models\Tournament $tournament
 * @method static \Database\Factories\TournamentNotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification forZone($zoneId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification sent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification today()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereAttachments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereDetails($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereRefereeList($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereSentBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereTemplatesUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereTotalRecipients($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TournamentNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'status',
        'total_recipients',
        'sent_at',
        'sent_by',
        'details',
        'templates_used',
        'error_message',
        'attachments',
        'referee_list',  // ← AGGIUNGI QUESTA RIGA
        'prepared_at',   // ← E ANCHE QUESTA SE MANCA
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'details' => 'array',
        'templates_used' => 'array',
        'attachments' => 'json'
    ];

    /**
     * 🏆 Relazione con torneo
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * 👤 Relazione con utente che ha inviato
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * 📧 Relazione con notifiche individuali per dettagli
     */
    public function individualNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'tournament_id', 'tournament_id')
            ->when($this->sent_at, function ($query) {
                $query->where('created_at', '>=', $this->sent_at->subMinutes(5))
                    ->where('created_at', '<=', $this->sent_at->addMinutes(5));
            });
    }
    /**
     * 📊 Scope: Solo notifiche inviate con successo
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * 📊 Scope: Solo notifiche fallite
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * 📊 Scope: Notifiche di oggi
     */
    public function scopeToday($query)
    {
        return $query->whereDate('sent_at', today());
    }

    /**
     * 📊 Scope: Notifiche per zona
     */
    public function scopeForZone($query, $zoneId)
    {
        return $query->whereHas('tournament', function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId);
        });
    }

    /**
     * ✅ Accessor: Stato formattato
     */
    public function getStatusFormattedAttribute(): string
    {
        $statuses = [
            'sent' => '✅ Inviato',
            'partial' => '⚠️ Parziale',
            'failed' => '❌ Fallito',
            'pending' => '⏳ In attesa'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * 📊 Accessor: Statistiche dettagliate
     */
    public function getStatsAttribute(): array
    {
        $details = $this->details ?? [];

        // Se details è una stringa, decodificala
        if (is_string($details)) {
            $details = json_decode($details, true) ?? [];
        }

        // Gestisce sia il formato semplice che quello complesso
        if (isset($details['sent'])) {
            // Formato semplice: {"sent":4,"arbitri":3,"club":1}
            return [
                'club_sent' => $details['club'] ?? 0,
                'club_failed' => 0,
                'referees_sent' => $details['arbitri'] ?? 0,
                'referees_failed' => 0,
                'institutional_sent' => 0,
                'institutional_failed' => 0,
                'total_sent' => $details['sent'] ?? $this->total_recipients ?? 0,
                'total_failed' => 0,
                'success_rate' => 100.0
            ];
        }

        // Formato complesso originale
        return [
            'club_sent' => $details['club']['sent'] ?? 0,
            'club_failed' => $details['club']['failed'] ?? 0,
            'referees_sent' => $details['referees']['sent'] ?? 0,
            'referees_failed' => $details['referees']['failed'] ?? 0,
            'institutional_sent' => $details['institutional']['sent'] ?? 0,
            'institutional_failed' => $details['institutional']['failed'] ?? 0,
            'total_sent' => $this->total_recipients ?? 0,
            'total_failed' => ($details['club']['failed'] ?? 0) +
                ($details['referees']['failed'] ?? 0) +
                ($details['institutional']['failed'] ?? 0),
            'success_rate' => $this->calculateSuccessRate()
        ];
    }

    /**
     * 🎯 Accessor: Template utilizzati formattati
     */
    public function getTemplatesFormattedAttribute(): string
    {
        $templates = $this->templates_used ?? [];
        $formatted = [];

        if (isset($templates['club'])) {
            $formatted[] = "Circolo: {$templates['club']}";
        }
        if (isset($templates['referee'])) {
            $formatted[] = "Arbitri: {$templates['referee']}";
        }
        if (isset($templates['institutional'])) {
            $formatted[] = "Istituzionali: {$templates['institutional']}";
        }

        return implode(' | ', $formatted);
    }

    /**
     * 📧 Accessor: Lista destinatari
     */
    public function getRecipientsListAttribute(): string
    {
        $stats = $this->stats;
        $recipients = [];

        if ($stats['club_sent'] > 0) {
            $recipients[] = "1 circolo";
        }
        if ($stats['referees_sent'] > 0) {
            $recipients[] = "{$stats['referees_sent']} arbitri";
        }
        if ($stats['institutional_sent'] > 0) {
            $recipients[] = "{$stats['institutional_sent']} istituzionali";
        }

        return implode(', ', $recipients);
    }

    /**
     * ⏰ Accessor: Tempo trascorso
     */
    public function getTimeAgoAttribute(): string
    {
        if (!$this->sent_at) return 'Mai inviato';

        return $this->sent_at->diffForHumans();
    }

    /**
     * 🔄 Metodo: Può essere reinviato?
     */
    public function canBeResent(): bool
    {
        // Permetti sempre reinvio dopo 1 ora per testing
        if ($this->sent_at && $this->sent_at->lt(now()->subHour())) {
            return true;
        }

        return true;
    }
    /**
     * ❌ Metodo: Ha errori?
     */
    public function hasErrors(): bool
    {
        return !empty($this->error_message) ||
            ($this->details['failed'] ?? 0) > 0;
    }

    /**
     * 📊 Metodo: Calcola percentuale successo
     */
    private function calculateSuccessRate(): float
    {
        $stats = $this->details ?? [];
        $totalSent = $this->total_recipients ?? 0;
        $totalFailed = ($stats['club']['failed'] ?? 0) +
            ($stats['referees']['failed'] ?? 0) +
            ($stats['institutional']['failed'] ?? 0);

        if ($totalSent == 0) return 0;

        return round((($totalSent - $totalFailed) / $totalSent) * 100, 1);
    }

    /**
     * 🔍 Metodo: Ottieni dettagli errori
     */
    public function getErrorDetails(): array
    {
        $errors = [];
        $details = $this->details ?? [];

        foreach (['club', 'referees', 'institutional'] as $type) {
            if (isset($details[$type]['errors'])) {
                $errors[$type] = $details[$type]['errors'];
            }
        }

        return $errors;
    }

    /**
     * 📊 Metodo statico: Statistiche globali
     */
    public static function getGlobalStats(): array
    {
        return [
            'total_tournaments_notified' => self::count(),
            'total_recipients_reached' => self::sum('total_recipients'),
            'success_rate' => self::calculateGlobalSuccessRate(),
            'this_month' => self::whereMonth('sent_at', now()->month)->count(),
            'this_week' => self::whereBetween('sent_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'today' => self::whereDate('sent_at', today())->count()
        ];
    }

    /**
     * 📊 Metodo statico: Calcola percentuale successo globale
     */
    private static function calculateGlobalSuccessRate(): float
    {
        $total = self::sum('total_recipients');
        $sent = self::where('status', 'sent')->sum('total_recipients');

        return $total > 0 ? round(($sent / $total) * 100, 1) : 0;
    }
public function getAttachmentsAttribute($value)
{
    if (is_string($value)) {
        return json_decode($value, true) ?? [];
    }
    return $value ?? [];
}

public function setAttachmentsAttribute($value)
{
    $this->attributes['attachments'] = is_array($value) ? json_encode($value) : $value;
}

}
