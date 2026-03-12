<?php

namespace App\Services;

use App\Enums\AssignmentRole;
use App\Enums\UserType;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Fluent builder per costruire la lista destinatari di una notifica torneo.
 *
 * Elimina la duplicazione tra sendNationalNotification() e resendNationalNotification()
 * in NotificationController.
 *
 * Uso:
 *   $recipients = (new NotificationRecipientBuilder())
 *       ->addCampionati()
 *       ->addZone($tournament)
 *       ->addZoneAdmins($tournament)
 *       ->addAssignedReferees($tournament)
 *       ->build();
 *
 *   $toList  = $recipients['to'];    // array<{email, name}>
 *   $ccArray = $recipients['cc'];    // array<email => name> (formato Laravel Mail cc)
 */
class NotificationRecipientBuilder
{
    /** @var array<array{email: string, name: string}> */
    private array $to = [];

    /** @var array<array{email: string, name: string}> */
    private array $cc = [];

    // ── Metodi TO ─────────────────────────────────────────────────────────────

    /**
     * Aggiunge l'Ufficio Campionati Federgolf in TO.
     */
    public function addCampionati(): static
    {
        $email = config('golf.emails.ufficio_campionati', 'campionati@federgolf.it');

        if ($email) {
            $this->addTo($email, 'Comitato Campionati');
        }

        return $this;
    }

    // ── Metodi CC ─────────────────────────────────────────────────────────────

    /**
     * Aggiunge la zona del torneo in CC.
     */
    public function addZone(Tournament $tournament): static
    {
        $zone = $tournament->club?->zone;

        if ($zone?->email) {
            $this->addCc($zone->email, $zone->name);
        }

        return $this;
    }

    /**
     * Aggiunge il CRC (Comitato Regionale Campionati) in CC.
     */
    public function addCrc(): static
    {
        $email = config('golf.emails.crc', 'crc@federgolf.it');

        if ($email) {
            $this->addCc($email, 'CRC');
        }

        return $this;
    }

    /**
     * Aggiunge gli admin zonali della zona del torneo in CC.
     */
    public function addZoneAdmins(Tournament $tournament): static
    {
        User::where('user_type', UserType::ZoneAdmin->value)
            ->where('zone_id', $tournament->club?->zone_id)
            ->where('is_active', true)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge gli admin nazionali in CC.
     */
    public function addNationalAdmins(): static
    {
        User::where('user_type', UserType::NationalAdmin->value)
            ->where('is_active', true)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge gli admin zonali con ID specifici in CC.
     *
     * @param  int[]  $userIds
     */
    public function addZoneAdminsByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge gli arbitri assegnati al torneo in CC.
     */
    public function addAssignedReferees(Tournament $tournament): static
    {
        $assignments = $tournament->relationLoaded('assignments')
            ? $tournament->assignments
            : $tournament->assignments()->with('user')->get();

        $assignments->each(function ($assignment) {
            if ($assignment->user) {
                $this->addCc($assignment->user->email, $assignment->user->name);
            }
        });

        return $this;
    }

    /**
     * Aggiunge arbitri con ID specifici in CC.
     *
     * @param  int[]  $userIds
     */
    public function addRefereesByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge gli osservatori (ruolo Osservatore) del torneo in CC.
     */
    public function addObservers(Tournament $tournament): static
    {
        $assignments = $tournament->relationLoaded('assignments')
            ? $tournament->assignments->where('role', AssignmentRole::Observer->value)
            : $tournament->assignments()->where('role', AssignmentRole::Observer->value)->with('user')->get();

        $assignments->each(function ($assignment) {
            if ($assignment->user) {
                $this->addCc($assignment->user->email, $assignment->user->name);
            }
        });

        return $this;
    }

    /**
     * Aggiunge osservatori con ID specifici in CC.
     *
     * @param  int[]  $userIds
     */
    public function addObserversByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    // ── Build ─────────────────────────────────────────────────────────────────

    /**
     * Restituisce i destinatari costruiti.
     *
     * @return array{
     *   to: array<array{email: string, name: string}>,
     *   cc: array<string, string>,
     *   allNames: string[],
     *   total: int,
     *   isEmpty: bool
     * }
     */
    public function build(): array
    {
        // Formato CC per Laravel Mail: [email => name]
        $ccArray = [];
        foreach ($this->cc as $recipient) {
            $ccArray[$recipient['email']] = $recipient['name'];
        }

        $allNames = array_merge(
            array_column($this->to, 'name'),
            array_column($this->cc, 'name')
        );

        return [
            'to'       => $this->to,
            'cc'       => $ccArray,
            'allNames' => $allNames,
            'total'    => count($this->to) + count($this->cc),
            'isEmpty'  => empty($this->to) && empty($this->cc),
        ];
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function addTo(string $email, string $name): void
    {
        if ($email && ! $this->alreadyAdded($this->to, $email)) {
            $this->to[] = ['email' => $email, 'name' => $name];
        }
    }

    private function addCc(string $email, string $name): void
    {
        if ($email && ! $this->alreadyAdded($this->cc, $email)) {
            $this->cc[] = ['email' => $email, 'name' => $name];
        }
    }

    /**
     * Evita duplicati nella stessa lista.
     *
     * @param  array<array{email: string, name: string}>  $list
     */
    private function alreadyAdded(array $list, string $email): bool
    {
        foreach ($list as $existing) {
            if (strtolower($existing['email']) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
