<?php

namespace App\Services;

use App\Enums\AssignmentRole;
use App\Enums\UserType;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fluent builder per costruire la lista destinatari di una notifica torneo.
 *
 * Centralizza la costruzione dei destinatari per sendNationalNotification()
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
 *   $ccArray = $recipients['cc'];    // array<{email, name}> (formato canonico Laravel)
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
     *   cc: array<array{email: string, name: string}>,
     *   allNames: string[],
     *   total: int,
     *   isEmpty: bool
     * }
     */
    public function build(): array
    {
        // Formato CC canonico Laravel: array<{email, name}> — stesso del TO.
        // NOTA: il vecchio formato [email => name] funzionava solo con
        // Mail::raw + closure (Symfony Message::cc accetta entrambi). Con
        // Mail::to()->cc()->send(Mailable) il PendingMail::parseAddresses()
        // itera i VALUE come email e fallisce RFC 2822 sul name.
        $allNames = array_merge(
            array_column($this->to, 'name'),
            array_column($this->cc, 'name')
        );

        return [
            'to'       => $this->to,
            'cc'       => $this->cc,
            'allNames' => $allNames,
            'total'    => count($this->to) + count($this->cc),
            'isEmpty'  => empty($this->to) && empty($this->cc),
        ];
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function addTo(string $email, string $name): void
    {
        if (! $this->isValidEmail($email, $name)) {
            return;
        }
        if (! $this->alreadyAdded($this->to, $email)) {
            $this->to[] = ['email' => $email, 'name' => $name];
        }
    }

    private function addCc(string $email, string $name): void
    {
        if (! $this->isValidEmail($email, $name)) {
            return;
        }
        if (! $this->alreadyAdded($this->cc, $email)) {
            $this->cc[] = ['email' => $email, 'name' => $name];
        }
    }

    /**
     * Valida che la stringa sia un'email RFC-compliant.
     * Skippa silenziosamente con log warning gli indirizzi malformati
     * (es. dati corrotti dove un nome finisce nella colonna email).
     * Evita che un singolo dato cattivo blocchi l'intera notifica.
     */
    private function isValidEmail(?string $email, ?string $name): bool
    {
        if (empty($email)) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Log::warning('NotificationRecipientBuilder: indirizzo email malformato saltato', [
                'email' => $email,
                'name'  => $name,
            ]);
            return false;
        }

        return true;
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
