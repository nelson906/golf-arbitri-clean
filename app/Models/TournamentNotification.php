<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property string|null $error_message
 * @property array<array-key, mixed>|null $attachments
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read array<string, mixed> $stats
 * @property-read string $status_formatted
 * @property-read string $time_ago
 * @property-read \App\Models\User|null $sentBy
 * @property-read \App\Models\Tournament $tournament
 *
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
 *
 * @mixin \Eloquent
 */
class TournamentNotification extends Model
{

    protected $fillable = [
        'tournament_id',
        'notification_type', // null = zonale, 'crc_referees' = CRC nazionale, 'zone_observers' = ZONA nazionale
        'recipients',    // JSON: { club: true, referees: [ids], institutional: [ids] }
        'content',       // JSON: { subject, message }
        'documents',     // JSON: { convocation: filename, club_letter: filename }
        'metadata',      // JSON: { error, retry_count, etc }
        'details',       // JSON: statistiche invio
        'attachments',   // JSON: allegati
        'status',        // pending, sent, partial, failed
        'sent_by',
        'sent_at',
        'is_prepared',
        'referee_list',
        'workflow_status',
        'last_step_completed',
        'workflow_data',
        'prepared_at',
        'configured_at',
        'generated_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'content' => 'array',
        'documents' => 'array',
        'metadata' => 'array',
        'details' => 'array',
        'attachments' => 'array',
        'workflow_data' => 'array',
        'sent_at' => 'datetime',
        'prepared_at' => 'datetime',
        'configured_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    /**
     * 🏆 Relazione con torneo
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * 👤 Relazione con utente che ha inviato
     *
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    // NOTA (audit 2026-06): rimossa individualNotifications() — puntava al
    // model legacy Notification (eliminato); relazione mai letta in app/view.

    /**
     * 📊 Scope: Solo notifiche inviate con successo
     *
     * @param  Builder<TournamentNotification>  $query
     * @return Builder<TournamentNotification>
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', 'sent');
    }

    /**
     * 📊 Scope: Solo notifiche fallite
     *
     * @param  Builder<TournamentNotification>  $query
     * @return Builder<TournamentNotification>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * 📊 Scope: Notifiche di oggi
     *
     * @param  Builder<TournamentNotification>  $query
     * @return Builder<TournamentNotification>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('sent_at', today());
    }

    /**
     * 📊 Scope: Notifiche per zona
     *
     * @param  Builder<TournamentNotification>  $query
     * @return Builder<TournamentNotification>
     */
    public function scopeForZone(Builder $query, int $zoneId): Builder
    {
        return $query->whereHas('tournament.club', function ($q) use ($zoneId) {
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
            'pending' => '⏳ In attesa',
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * Normalizza la colonna JSON `details` in un array.
     *
     * `details` e' scritta da versioni diverse del NotificationService e non ha
     * una forma garantita: puo' essere un array, una stringa JSON (righe
     * storiche) o null. Qui si valida, non si dichiara.
     *
     * @return array<array-key, mixed>
     */
    private function detailsArray(): array
    {
        $details = $this->details;

        if (is_string($details)) {
            $details = json_decode($details, true);
        }

        return is_array($details) ? $details : [];
    }

    /**
     * Legge un intero da una struttura annidata non fidata.
     *
     * Ogni chiave mancante, o un valore non numerico, valgono 0: e' esattamente
     * cio' che facevano i `?? 0` a catena, ma senza rompersi quando il livello
     * intermedio non e' un array (formato "semplice" contro "complesso").
     *
     * @param  array<array-key, mixed>  $data
     */
    private static function intAt(array $data, string ...$path): int
    {
        $current = $data;

        foreach ($path as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return 0;
            }
            $current = $current[$key];
        }

        return is_numeric($current) ? (int) $current : 0;
    }

    /**
     * 📊 Accessor: Statistiche dettagliate
     *
     * @return array{
     *     club_sent: int,
     *     club_failed: int,
     *     referees_sent: int,
     *     referees_failed: int,
     *     institutional_sent: int,
     *     institutional_failed: int,
     *     total_sent: int,
     *     total_failed: int,
     *     success_rate: float,
     * }
     */
    public function getStatsAttribute(): array
    {
        $details = $this->detailsArray();

        // Gestisce sia il formato semplice che quello complesso
        if (isset($details['sent'])) {
            // Formato semplice: {"sent":4,"arbitri":3,"club":1}
            $totalSent = self::intAt($details, 'sent');

            return [
                'club_sent' => self::intAt($details, 'club'),
                'club_failed' => 0,
                'referees_sent' => self::intAt($details, 'arbitri'),
                'referees_failed' => 0,
                'institutional_sent' => 0,
                'institutional_failed' => 0,
                'total_sent' => $totalSent !== 0 ? $totalSent : self::intAt($details, 'total_recipients'),
                'total_failed' => 0,
                'success_rate' => 100.0,
            ];
        }

        // Formato complesso originale
        return [
            'club_sent' => self::intAt($details, 'club', 'sent'),
            'club_failed' => self::intAt($details, 'club', 'failed'),
            'referees_sent' => self::intAt($details, 'referees', 'sent'),
            'referees_failed' => self::intAt($details, 'referees', 'failed'),
            'institutional_sent' => self::intAt($details, 'institutional', 'sent'),
            'institutional_failed' => self::intAt($details, 'institutional', 'failed'),
            'total_sent' => self::intAt($details, 'total_recipients'),
            'total_failed' => self::intAt($details, 'club', 'failed')
                + self::intAt($details, 'referees', 'failed')
                + self::intAt($details, 'institutional', 'failed'),
            'success_rate' => $this->calculateSuccessRate(),
        ];
    }

    /**
     * ⏰ Accessor: Tempo trascorso
     */
    public function getTimeAgoAttribute(): string
    {
        if (! $this->sent_at) {
            return 'Mai inviato';
        }

        return $this->sent_at->diffForHumans();
    }

    /**
     * 🔄 Metodo: Può essere reinviato?
     *
     * FIX B-3: rimosso il doppio `return true` che rendeva la logica sempre vera
     * indipendentemente dall'orario di invio. Ora la regola è:
     *   - Notifiche mai inviate (sent_at null) → sempre reinviabili
     *   - Notifiche inviate da più di 1 ora    → reinviabili
     *   - Notifiche inviate da meno di 1 ora   → non reinviabili (debounce)
     */
    public function canBeResent(): bool
    {
        if (! $this->sent_at) {
            return true;
        }

        return $this->sent_at->lt(now()->subHour());
    }

    /**
     * ❌ Metodo: Ha errori?
     */
    public function hasErrors(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $details = $this->detailsArray();

        // `failed`/`errors` sono scritti come contatore int (NotificationService:212),
        // ma righe storiche possono contenere la lista degli errori: entrambe le
        // forme contavano come "ha errori" prima, ed entrambe contano ancora.
        $failed = $details['failed'] ?? $details['errors'] ?? 0;

        return ! empty($metadata['last_error'])
            || (is_numeric($failed) && (float) $failed > 0)
            || (is_array($failed) && $failed !== []);
    }

    /**
     * 📊 Metodo: Calcola percentuale successo
     */
    private function calculateSuccessRate(): float
    {
        $details = $this->detailsArray();
        $totalSent = self::intAt($details, 'total_recipients');
        $totalFailed = self::intAt($details, 'club', 'failed')
            + self::intAt($details, 'referees', 'failed')
            + self::intAt($details, 'institutional', 'failed');

        if ($totalSent === 0) {
            return 0.0;
        }

        return round((($totalSent - $totalFailed) / $totalSent) * 100, 1);
    }

    /**
     * 📊 Metodo statico: Statistiche globali
     *
     * @return array<string, mixed>
     */
    public static function getGlobalStats(): array
    {
        return [
            'total_tournaments_notified' => self::count(),
            'total_recipients_reached' => self::where('status', 'sent')->count(),
            'success_rate' => self::calculateGlobalSuccessRate(),
            'this_month' => self::whereMonth('sent_at', now()->month)->count(),
            'this_week' => self::whereBetween('sent_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
            'today' => self::whereDate('sent_at', today())->count(),
        ];
    }

    /**
     * 📊 Metodo statico: Calcola percentuale successo globale
     */
    private static function calculateGlobalSuccessRate(): float
    {
        $total = self::count();
        $sent = self::where('status', 'sent')->count();

        return $total > 0 ? round(($sent / $total) * 100, 1) : 0;
    }

    /**
     * 📝 Relazione con le clausole selezionate
     *
     * @return HasMany<NotificationClauseSelection, $this>
     */
    public function clauseSelections(): HasMany
    {
        return $this->hasMany(NotificationClauseSelection::class);
    }

    /**
     * 📝 Accessor: Ottieni clausole selezionate organizzate
     *
     * @return array<string, mixed>
     */
    public function getSelectedClausesAttribute(): array
    {
        return $this->clauseSelections()
            ->with('clause')
            ->get()
            ->mapWithKeys(function ($selection) {
                return [
                    $selection->placeholder_code => [
                        'content' => $selection->clause->content,
                        'title' => $selection->clause->title,
                        'category' => $selection->clause->category,
                    ],
                ];
            })
            ->toArray();
    }
}
