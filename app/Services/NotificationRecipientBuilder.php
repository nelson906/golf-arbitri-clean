<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\InstitutionalEmail;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Fluent builder per costruire la lista destinatari di una notifica torneo.
 *
 * Usato da:
 *  - NotificationController::sendNationalNotification() (flusso CRC/SZR nazionale)
 *  - NotificationService::send() (flusso zonale a mail singola:
 *    TO = circolo, CC = arbitri + istituzionali + zona + email aggiuntive)
 *
 * Uso:
 *   $recipients = (new NotificationRecipientBuilder())
 *       ->addClub($tournament)
 *       ->addRefereesByIds([1, 2])
 *       ->addInstitutionalsByIds([3])
 *       ->addZone($tournament)
 *       ->build();
 *
 *   $toList  = $recipients['to'];    // array<{email, name}>
 *   $ccArray = $recipients['cc'];    // array<{email, name}> (formato canonico Laravel)
 *
 * NOTA (razionalizzazione 2026-06): rimossi addAssignedReferees() e
 * addObservers() — mai usati né testati; le route usano le varianti *ByIds.
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
        $email = Config::string('golf.emails.ufficio_campionati', 'campionati@federgolf.it');

        if ($email) {
            $this->addTo($email, 'Comitato Campionati');
        }

        return $this;
    }

    /**
     * Aggiunge il circolo del torneo in TO (flusso zonale unificato).
     */
    public function addClub(Tournament $tournament): static
    {
        $club = $tournament->club;

        if ($club?->email) {
            $this->addTo($club->email, $club->name ?? 'Circolo');
        }

        return $this;
    }

    // ── Metodi CC ─────────────────────────────────────────────────────────────

    /**
     * Aggiunge gli indirizzi istituzionali (attivi) con ID specifici in CC.
     *
     * @param  int[]  $emailIds
     */
    public function addInstitutionalsByIds(array $emailIds): static
    {
        if (empty($emailIds)) {
            return $this;
        }

        InstitutionalEmail::whereIn('id', $emailIds)
            ->where('is_active', true)
            ->each(fn ($e) => $this->addCc($e->email, $e->name ?? 'Istituzionale'));

        return $this;
    }

    /**
     * Aggiunge un indirizzo libero (email aggiuntiva dal form) in CC.
     */
    public function addCustomCc(string $email, ?string $name = null): static
    {
        $this->addCc($email, $name ?: $email);

        return $this;
    }

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
        $email = Config::string('golf.emails.crc', 'crc@federgolf.it');

        if ($email) {
            $this->addCc($email, 'CRC');
        }

        return $this;
    }

    /**
     * Aggiunge gli admin zonali (o nazionali: stessa implementazione,
     * il chiamante passa gli ID) con ID specifici in CC.
     *
     * FIX M1 (audit 2026-07): filtro user_type admin + is_active — prima
     * whereIn nudo: qualunque ID utente del DB finiva in CC.
     *
     * @param  int[]  $userIds
     */
    public function addZoneAdminsByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->whereIn('user_type', [
                UserType::ZoneAdmin->value,
                UserType::NationalAdmin->value,
                UserType::SuperAdmin->value,
            ])
            ->where('is_active', true)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge arbitri con ID specifici in CC.
     *
     * FIX M1 (audit 2026-07): aggiunto is_active (coerente con
     * addInstitutionalsByIds). Nessun filtro user_type: nel flusso zonale
     * gli ID arrivano già intersecati con le assegnazioni correnti.
     *
     * @param  int[]  $userIds
     */
    public function addRefereesByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->where('is_active', true)
            ->each(fn (User $u) => $this->addCc($u->email, $u->name));

        return $this;
    }

    /**
     * Aggiunge osservatori con ID specifici in CC.
     *
     * FIX M1 (audit 2026-07): aggiunto is_active.
     *
     * @param  int[]  $userIds
     */
    public function addObserversByIds(array $userIds): static
    {
        if (empty($userIds)) {
            return $this;
        }

        User::whereIn('id', $userIds)
            ->where('is_active', true)
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
        $email = trim($email); // spazi accidentali nei dati: stessa regola del controllo circolo
        if (! $this->isValidEmail($email, $name)) {
            return;
        }
        if (! $this->alreadyAdded($this->to, $email)) {
            $this->to[] = ['email' => $email, 'name' => $name];
        }

        // Un indirizzo riceve una sola copia (decisione 2026-10-04): se era gia'
        // in copia, resta solo come destinatario principale.
        $this->cc = array_values(array_filter(
            $this->cc,
            fn (array $existing) => strtolower($existing['email']) !== strtolower($email)
        ));
    }

    private function addCc(string $email, string $name): void
    {
        $email = trim($email);
        if (! $this->isValidEmail($email, $name)) {
            return;
        }
        // Gia' destinatario principale o gia' in copia: nessuna seconda copia
        if (! $this->alreadyAdded($this->to, $email) && ! $this->alreadyAdded($this->cc, $email)) {
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
     * Vero se l'indirizzo e' gia' nella lista (confronto senza maiuscole).
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
