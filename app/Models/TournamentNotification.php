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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentNotification query()
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
        'generated_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'content' => 'array',
        'documents' => 'array',
        'metadata' => 'array',
        'details' => 'array',
        'attachments' => 'array',
        'sent_at' => 'datetime',
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
     * Nomi dei designati che riguardano la notifica: zonale tutti; CRC arbitri
     * e Direttore (non gli osservatori); SZR solo gli osservatori.
     *
     * @param  iterable<int, \App\Models\Assignment>  $assignments
     */
    public static function refereeListFor(iterable $assignments, ?string $notificationType): string
    {
        $observer = \App\Enums\AssignmentRole::Observer->value;

        return collect($assignments)
            ->filter(fn ($a) => match ($notificationType) {
                'crc_referees' => $a->role !== $observer,
                'zone_observers' => $a->role === $observer,
                default => true,
            })
            ->map(fn ($a) => $a->user->name ?? null)
            ->filter()
            ->implode(', ');
    }

    /**
     * L'ultimo tentativo di invio non e' partito (nessuna mail spedita).
     * Decisione 2026-10-07: una notifica non inviata deve essere ben visibile.
     */
    public function isNotSent(): bool
    {
        return $this->status === 'failed';
    }

    /** Etichetta dello stato per le pagine. */
    public function stateLabel(): string
    {
        return match ($this->status) {
            'sent' => 'Inviata',
            'partial' => 'Inviata in parte',
            'failed' => 'Non inviata',
            default => 'Da inviare',
        };
    }

    /** Quando e' stato fatto l'ultimo tentativo di invio (anche fallito). */
    public function lastAttemptAt(): ?\Illuminate\Support\Carbon
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $value = $metadata['last_attempt_at'] ?? null;

        return is_string($value) && $value !== '' ? \Illuminate\Support\Carbon::parse($value) : null;
    }

    /**
     * Indirizzi accettati dal server di posta nell'ultimo invio (circolo o
     * Comitato Campionati piu' le copie). 0 se nessuna mail e' partita.
     */
    public function recipientsReached(): int
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $value = $metadata['success_count'] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Motivo dell'ultimo invio non riuscito, se c'e'. */
    public function lastError(): ?string
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $value = $metadata['last_error'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
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
     * 📝 Relazione con le clausole selezionate
     *
     * @return HasMany<NotificationClauseSelection, $this>
     */
    public function clauseSelections(): HasMany
    {
        return $this->hasMany(NotificationClauseSelection::class);
    }

}
