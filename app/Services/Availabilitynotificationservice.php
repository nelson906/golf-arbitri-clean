<?php

namespace App\Services;

use App\Mail\BatchAvailabilityAdminNotification;
use App\Mail\BatchAvailabilityNotification;
use App\Models\Availability;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service per gestire notifiche di disponibilità separate per zona/nazionale
 */
class AvailabilityNotificationService
{
    /**
     * Raggruppa disponibilità per tipo torneo (zonale/nazionale)
     *
     * @param  Collection<Availability>  $availabilities
     * @return array{zonal: Collection, national: Collection}
     */
    public function groupByTournamentType(Collection $availabilities): array
    {
        $grouped = $availabilities->groupBy(function ($availability) {
            return $availability->tournament->tournamentType->is_national ? 'national' : 'zonal';
        });

        return [
            'zonal' => $grouped->get('zonal', collect()),
            'national' => $grouped->get('national', collect()),
        ];
    }

    /**
     * Determina i destinatari corretti per le notifiche
     *
     * @param  Collection<Availability>  $availabilities
     * @return array{
     *   zone: array{email: string, availabilities: Collection}|null,
     *   crc: array{email: string, availabilities: Collection}|null,
     *   referee: array{email: string, availabilities: Collection}
     * }
     */
    public function determineRecipients(User $referee, Collection $availabilities): array
    {
        $grouped = $this->groupByTournamentType($availabilities);

        $recipients = [
            'zone' => null,
            'crc' => null,
            'referee' => [
                'email' => $referee->email,
                'availabilities' => $availabilities, // Tutte
            ],
        ];

        // Notifica zona se ci sono disponibilità zonali
        if ($grouped['zonal']->isNotEmpty()) {
            $recipients['zone'] = [
                'email' => $this->getZoneEmail($referee->zone_id),
                'availabilities' => $grouped['zonal'], // SOLO zonali
            ];
        }

        // Notifica CRC se ci sono disponibilità nazionali
        if ($grouped['national']->isNotEmpty()) {
            $recipients['crc'] = [
                'email' => $this->getCrcEmail(),
                'availabilities' => $grouped['national'], // SOLO nazionali
            ];
        }

        return $recipients;
    }

    /**
     * Invia notifiche separate a tutti i destinatari
     *
     * @param  Collection<Availability>  $availabilities
     * @return array{zone: bool, crc: bool, referee: bool}
     */
    public function sendSeparatedNotifications(User $referee, Collection $availabilities): array
    {
        $recipients = $this->determineRecipients($referee, $availabilities);
        $results = [];

        // Invia a zona (solo tornei zonali)
        if ($recipients['zone']) {
            $results['zone'] = $this->sendToZone(
                $recipients['zone']['email'],
                $referee,
                $recipients['zone']['availabilities']
            );
        }

        // Invia a CRC (solo tornei nazionali)
        if ($recipients['crc']) {
            $results['crc'] = $this->sendToCrc(
                $recipients['crc']['email'],
                $referee,
                $recipients['crc']['availabilities']
            );
        }

        // Invia all'arbitro (tutti i tornei)
        $results['referee'] = $this->sendToReferee(
            $referee,
            $availabilities
        );

        return $results;
    }

    /**
     * Invia notifica alla zona
     */
    protected function sendToZone(string $email, User $referee, Collection $availabilities): bool
    {
        try {
            if (empty($email)) {
                return false;
            }

            $tournaments = $availabilities
                ->map(fn ($availability) => $availability->tournament ?? null)
                ->filter()
                ->values();

            Mail::to($email)->send(new BatchAvailabilityAdminNotification(
                $referee,
                $tournaments,
                collect()
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Errore invio notifica disponibilità zona', [
                'referee_id' => $referee->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invia notifica al CRC
     */
    protected function sendToCrc(string $email, User $referee, Collection $availabilities): bool
    {
        try {
            if (empty($email)) {
                return false;
            }

            $tournaments = $availabilities
                ->map(fn ($availability) => $availability->tournament ?? null)
                ->filter()
                ->values();

            Mail::to($email)->send(new BatchAvailabilityAdminNotification(
                $referee,
                $tournaments,
                collect()
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Errore invio notifica disponibilità CRC', [
                'referee_id' => $referee->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Invia notifica all'arbitro
     */
    protected function sendToReferee(User $referee, Collection $availabilities): bool
    {
        try {
            if (empty($referee->email)) {
                return false;
            }

            $tournaments = $availabilities
                ->map(fn ($availability) => $availability->tournament ?? null)
                ->filter()
                ->values();

            Mail::to($referee->email)->send(new BatchAvailabilityNotification(
                $referee,
                $tournaments,
                collect()
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Errore invio conferma disponibilità arbitro', [
                'referee_id' => $referee->id,
                'email' => $referee->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Ottiene email della zona
     */
    protected function getZoneEmail(int $zoneId): string
    {
        $zoneEmails = [
            1 => 'szr1@federgolf.it',
            2 => 'szr2@federgolf.it',
            3 => 'szr3@federgolf.it',
            4 => 'szr4@federgolf.it',
            5 => 'szr5@federgolf.it',
            6 => 'szr6@federgolf.it',
            7 => 'szr7@federgolf.it',
        ];

        return $zoneEmails[$zoneId] ?? 'arbitri@federgolf.it';
    }

    /**
     * Ottiene email del CRC
     */
    protected function getCrcEmail(): string
    {
        return 'crc@federgolf.it';
    }
}
