<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;

/**
 * Segna come "gia' notificato" un torneo il cui comitato viene da federgolf.it.
 *
 * Decisione 2026-10-08: se un comitato e' pubblicato sul sito FIG con i nomi,
 * le convocazioni sono state fatte (altrimenti FIG non potrebbe pubblicarle).
 * Quindi ogni caricamento da FIG (Carica comitati FIG, import guidato,
 * comando federgolf:import-committees) registra anche la notifica come
 * inviata, come faceva a mano il comando federgolf:mark-notified.
 *
 * Tipo: nazionale -> crc_referees, zonale -> null (fonte: is_national del tipo).
 * Una notifica gia' inviata (anche in parte) non si tocca; una bozza o una
 * non inviata dello stesso tipo diventa "inviata", senza crearne una seconda.
 */
final class FigNotificationMarker
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    /**
     * @param  string  $type  auto | crc_referees | zone_observers | zonal
     * @return self::CREATED|self::UPDATED|self::SKIPPED
     */
    public function mark(Tournament $tournament, string $source, string $type = 'auto'): string
    {
        $notificationType = match ($type) {
            'auto' => ($tournament->tournamentType->is_national ?? false) ? 'crc_referees' : null,
            'zonal' => null,
            default => $type,
        };

        $assignments = Assignment::with('user')->where('tournament_id', $tournament->id)->get();
        if ($assignments->isEmpty()) {
            return self::SKIPPED;
        }

        $existing = TournamentNotification::where('tournament_id', $tournament->id)
            ->where(fn ($q) => $notificationType === null
                ? $q->whereNull('notification_type')
                : $q->where('notification_type', $notificationType))
            ->orderByDesc('id')
            ->first();

        if ($existing && in_array($existing->status, ['sent', 'partial'], true)) {
            return self::SKIPPED;
        }

        $refereeList = TournamentNotification::refereeListFor($assignments, $notificationType);
        $sentAt = $assignments->max('assigned_at') ?? now();
        $sentBy = auth()->id()
            ?? User::where('user_type', 'super_admin')->value('id')
            ?? User::where('user_type', 'national_admin')->value('id');

        $attributes = [
            'status' => 'sent',
            'sent_at' => $sentAt,
            'sent_by' => $sentBy,
            'referee_list' => $refereeList,
            'details' => [
                'sent' => $assignments->count(),
                'arbitri' => $assignments->count(),
                'total_recipients' => $assignments->count(),
                'note' => 'Comitato pubblicato da FIG: convocazioni gia\' fatte',
            ],
        ];
        $metadata = [
            'source' => $source,
            'fig' => true,
        ];

        if ($existing) {
            $current = is_array($existing->metadata) ? $existing->metadata : [];
            $existing->update($attributes + ['metadata' => array_merge($current, $metadata)]);

            return self::UPDATED;
        }

        TournamentNotification::create($attributes + [
            'tournament_id' => $tournament->id,
            'notification_type' => $notificationType,
            'metadata' => $metadata,
        ]);

        return self::CREATED;
    }
}
