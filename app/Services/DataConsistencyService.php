<?php

namespace App\Services;

use App\Enums\AssignmentRole;
use App\Enums\RefereeLevel;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Controlli di coerenza sui dati (decisione 2026-10-09).
 *
 * I test usano dati inventati: verificano il codice, non i dati veri. Gli
 * ultimi problemi erano nei dati (tornei nazionali con tipo zonale, notifiche
 * mancanti dopo il caricamento FIG...). Questi controlli guardano i dati del
 * database in uso e ne elencano le anomalie; non correggono niente.
 *
 * Usati dalla pagina Sistema -> Controllo dati (super admin, anche su Aruba)
 * e dal comando `php artisan golf:controlla-dati`.
 *
 * @phpstan-type Row array{label: string, url: string|null}
 * @phpstan-type Check array{key: string, title: string, level: 'errore'|'verifica', why: string, rows: list<Row>}
 */
final class DataConsistencyService
{
    /** Parole che nel nome indicano una gara gestita dal CRC. */
    private const NATIONAL_NAME_PATTERNS = [
        'CAMPIONATO NAZIONALE', 'CAMPIONATO INTERNAZIONALE', "INTERNAZIONALE D'ITALIA",
        'TROFEO NAZIONALE', 'NAZIONALE MASCHILE', 'NAZIONALE FEMMINILE', 'TORNEO NAZIONALE DI QUALIFICA',
    ];

    /** @return list<Check> */
    public function run(): array
    {
        return [
            $this->nationalNameWithZonalType(),
            $this->zonalNameWithNationalType(),
            $this->tournamentsWithoutZone(),
            $this->tournamentsWithBadDates(),
            $this->duplicateTournaments(),
            $this->pastTournamentsWithoutClub(),
            $this->upcomingZonalWithoutClubEmail(),
            $this->assignmentsOfInactiveOrNonReferees(),
            $this->nationalRolesOnLowerLevels(),
            $this->doubleBookings(),
            $this->notifiedTypeMismatch(),
            $this->pastTournamentsNeverNotified(),
            $this->activeRefereesWithoutZoneOrLevel(),
            $this->possibleDuplicateReferees(),
            $this->inactiveTypesInUse(),
            $this->countsDisagreeBetweenPages(),
        ];
    }

    /**
     * @param  array<int, Row>  $rows
     * @param  'errore'|'verifica'  $level
     * @return Check
     */
    private function check(string $key, string $title, string $level, string $why, array $rows): array
    {
        $rows = array_values($rows);

        return compact('key', 'title', 'level', 'why', 'rows');
    }

    /** @return Row */
    private function tournamentRow(Tournament $t, string $extra = ''): array
    {
        $label = $t->start_date->format('d/m/Y').' — '.trim($t->name).' ('.($t->tournamentType->short_name ?? '?').')'
            .($extra !== '' ? ' — '.$extra : '');

        return ['label' => $label, 'url' => route('admin.tournaments.show', $t)];
    }

    /** @return Row */
    private function userRow(User $u, string $extra = ''): array
    {
        $label = $u->name.' ('.($u->level ?? 'senza livello').', '.($u->zone->name ?? 'senza zona').')'
            .($extra !== '' ? ' — '.$extra : '');

        return ['label' => $label, 'url' => route('admin.users.show', $u)];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Tournament> */
    private function tournaments(): \Illuminate\Database\Eloquent\Builder
    {
        return Tournament::with(['tournamentType', 'club'])->orderBy('start_date');
    }

    /** @return Check */
    private function nationalNameWithZonalType(): array
    {
        $rows = $this->tournaments()
            ->whereHas('tournamentType', fn ($q) => $q->where('is_national', false))
            ->where(function ($q) {
                foreach (self::NATIONAL_NAME_PATTERNS as $p) {
                    $q->orWhere('name', 'like', '%'.$p.'%');
                }
            })
            ->get()
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('nome_nazionale_tipo_zonale', 'Nome da gara nazionale ma tipo zonale', 'verifica',
            'Il tipo decide chi gestisce il torneo: con un tipo zonale il CRC non lo vede, non lo conta e non lo notifica.', $rows);
    }

    /** @return Check */
    private function zonalNameWithNationalType(): array
    {
        $rows = $this->tournaments()
            ->whereHas('tournamentType', fn ($q) => $q->where('is_national', true))
            ->where(fn ($q) => $q
                ->where('name', 'like', '%REGIONALE%')
                ->orWhere('name', 'like', '%GIOVANILE%')
                ->orWhere('name', 'like', '%GARA NAZIONALE 54%')
                ->orWhere('name', 'like', '%GARA NAZIONALE 36%')
                ->orWhere('name', 'like', '%TGF%'))
            ->get()
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('nome_zonale_tipo_nazionale', 'Nome da gara zonale ma tipo nazionale', 'verifica',
            'Gare regionali, giovanili e Gare Nazionali 54/36 sono zonali: con un tipo nazionale le gestirebbe il CRC.', $rows);
    }

    /** @return Check */
    private function tournamentsWithoutZone(): array
    {
        $rows = $this->tournaments()
            ->whereNull('club_id')->whereNull('zone_id')
            ->get()
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('torneo_senza_zona', 'Tornei senza circolo e senza zona', 'errore',
            'Senza zona nessun admin di zona vede il torneo.', $rows);
    }

    /** @return Check */
    private function tournamentsWithBadDates(): array
    {
        $rows = $this->tournaments()
            ->get()
            ->filter(fn (Tournament $t) => ($t->end_date && $t->end_date->lt($t->start_date))
                || ($t->availability_deadline && $t->availability_deadline->gt($t->start_date->copy()->endOfDay())))
            ->map(fn (Tournament $t) => $this->tournamentRow($t, $t->end_date && $t->end_date->lt($t->start_date)
                ? 'fine prima dell\'inizio'
                : 'scadenza disponibilità dopo l\'inizio'))
            ->values()->all();

        return $this->check('date_incoerenti', 'Date incoerenti', 'errore',
            'Fine prima dell\'inizio, o scadenza delle disponibilità dopo l\'inizio della gara.', $rows);
    }

    /** @return Check */
    private function duplicateTournaments(): array
    {
        $rows = $this->tournaments()
            ->get()
            // Stesso nome, data e circolo: il circuito Soldati, per esempio, ha
            // gare con lo stesso nome lo stesso giorno in circoli diversi
            ->groupBy(fn (Tournament $t) => mb_strtoupper(trim($t->name)).'|'.$t->start_date->toDateString().'|'.($t->club_id ?? 'tba'))
            ->filter(fn (Collection $g) => $g->count() > 1)
            ->flatMap(fn (Collection $g) => $g->map(fn (Tournament $t) => $this->tournamentRow($t, ($t->club->name ?? 'T.B.A.'))))
            ->values()->all();

        return $this->check('tornei_doppi', 'Tornei doppi (stesso nome, data e circolo)', 'verifica',
            'Spesso nascono da caricamenti ripetuti: designazioni e notifiche si dividono tra le due copie.', $rows);
    }

    /** @return Check */
    private function pastTournamentsWithoutClub(): array
    {
        $rows = $this->tournaments()
            ->whereNull('club_id')
            ->where('start_date', '<', now()->startOfDay())
            ->get()
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('passati_senza_circolo', 'Tornei già giocati ancora senza circolo (T.B.A.)', 'verifica',
            'Il circolo non è mai stato indicato: la notifica zonale non può partire.', $rows);
    }

    /** @return Check */
    private function upcomingZonalWithoutClubEmail(): array
    {
        $rows = $this->tournaments()
            ->where('start_date', '>=', now()->startOfDay())
            ->whereHas('tournamentType', fn ($q) => $q->where('is_national', false))
            ->whereHas('club')
            ->get()
            ->filter(fn (Tournament $t) => ! NotificationService::clubHasValidEmail($t))
            ->map(fn (Tournament $t) => $this->tournamentRow($t, $t->club->name ?? ''))
            ->values()->all();

        return $this->check('circolo_senza_email', 'Prossimi tornei zonali con circolo senza email valida', 'errore',
            'Senza email del circolo la notifica zonale non si prepara e non parte.', $rows);
    }

    /** @return Check */
    private function assignmentsOfInactiveOrNonReferees(): array
    {
        $rows = Assignment::with(['user.zone', 'tournament'])
            ->whereHas('user', fn ($q) => $q->where('is_active', false)
                ->orWhere('user_type', '!=', 'referee')
                ->orWhere('level', RefereeLevel::Archivio->value))
            ->get()
            ->map(fn (Assignment $a) => $this->userRow($a->user, 'designato a '.trim($a->tournament->name ?? '?')
                .($a->user->is_active ? '' : ' (non attivo)')))
            ->values()->all();

        return $this->check('designati_non_attivi', 'Designazioni di account non attivi, non arbitri o in archivio', 'errore',
            'Queste designazioni non compaiono nei conteggi degli arbitri attivi.', $rows);
    }

    /** @return Check */
    private function nationalRolesOnLowerLevels(): array
    {
        $rows = Assignment::with(['user.zone', 'tournament.tournamentType'])
            ->where('role', '!=', AssignmentRole::Observer->value)
            ->whereHas('tournament.tournamentType', fn ($q) => $q->where('is_national', true))
            ->whereHas('user', fn ($q) => $q->whereNotIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]))
            ->get()
            ->map(fn (Assignment $a) => $this->userRow($a->user, $a->role.' a '.trim($a->tournament->name ?? '?')))
            ->values()->all();

        return $this->check('nazionali_livello_basso', 'Arbitri non nazionali designati come Arbitro o DT su gare nazionali', 'verifica',
            'Il Regolamento lo consente per valutare un Regionale: qui per controllo.', $rows);
    }

    /** @return Check */
    private function doubleBookings(): array
    {
        $rows = app(AssignmentValidationService::class)->detectDateConflicts()
            ->map(fn (array $c) => $this->userRow($c['referee'],
                trim($c['assignment1']->tournament->name).' / '.trim($c['assignment2']->tournament->name)))
            ->values()->all();

        return $this->check('date_sovrapposte', 'Arbitri designati su gare con date sovrapposte (da oggi in poi)', 'errore',
            'Lo stesso arbitro in due gare negli stessi giorni.', $rows);
    }

    /** @return Check */
    private function notifiedTypeMismatch(): array
    {
        $rows = TournamentNotification::with('tournament.tournamentType')
            ->get()
            ->filter(function (TournamentNotification $n) {
                $national = $n->tournament->tournamentType->is_national ?? false;

                return $national ? $n->notification_type === null : $n->notification_type !== null;
            })
            ->map(fn (TournamentNotification $n) => $this->tournamentRow($n->tournament,
                'notifica '.($n->notification_type ?? 'zonale').' ('.$n->status.')'))
            ->values()->all();

        return $this->check('notifica_tipo_errato', 'Notifiche del tipo sbagliato rispetto al torneo', 'errore',
            'Notifica zonale su un torneo nazionale o viceversa: di solito il tipo del torneo è cambiato dopo.', $rows);
    }

    /** @return Check */
    private function pastTournamentsNeverNotified(): array
    {
        $rows = $this->tournaments()
            ->where('start_date', '<', now()->startOfDay())
            ->whereHas('assignments')
            ->with('notifications')
            ->get()
            ->filter(function (Tournament $t) {
                $national = $t->tournamentType->is_national ?? false;

                return ! $t->notifications->contains(fn (TournamentNotification $n) => in_array($n->status, ['sent', 'partial'], true)
                    && ($national ? $n->notification_type === 'crc_referees' : $n->notification_type === null));
            })
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('mai_notificati', 'Tornei già giocati, con arbitri, mai notificati', 'verifica',
            'Se il comitato viene da FIG, la notifica va segnata come inviata (script fig-segna-notificati.sql).', $rows);
    }

    /** @return Check */
    private function activeRefereesWithoutZoneOrLevel(): array
    {
        $rows = User::with('zone')
            ->where('user_type', 'referee')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('zone_id')->orWhereNull('level'))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->userRow($u))
            ->values()->all();

        return $this->check('arbitri_senza_zona_livello', 'Arbitri attivi senza zona o senza livello', 'errore',
            'Senza zona non li vede nessuna SZR; senza livello non si sa su quali gare contarli.', $rows);
    }

    /** @return Check */
    private function possibleDuplicateReferees(): array
    {
        $rows = User::with('zone')
            ->where('user_type', 'referee')
            ->get()
            ->groupBy(fn (User $u) => mb_strtoupper(trim((string) $u->last_name)).'|'.mb_strtoupper(trim((string) $u->first_name)))
            ->filter(fn (Collection $g, string $key) => $g->count() > 1 && $key !== '|')
            ->flatMap(fn (Collection $g) => $g->map(fn (User $u) => $this->userRow($u, ($u->is_active ? 'attivo' : 'non attivo').', '.$u->email)))
            ->values()->all();

        return $this->check('arbitri_doppi', 'Possibili arbitri doppi (stesso nome e cognome)', 'verifica',
            'Il caricamento FIG abbina i nomi: con due account le designazioni si dividono.', $rows);
    }

    /** @return Check */
    private function inactiveTypesInUse(): array
    {
        $rows = $this->tournaments()
            ->where('start_date', '>=', now()->startOfDay())
            ->whereHas('tournamentType', fn ($q) => $q->where('is_active', false))
            ->get()
            ->map(fn (Tournament $t) => $this->tournamentRow($t))
            ->values()->all();

        return $this->check('tipi_disattivati', 'Prossimi tornei con un tipo disattivato', 'verifica',
            'Il tipo non compare più nei menu: il torneo non si può ricreare uguale.', $rows);
    }

    /**
     * Stesso arbitro, stesso anno: scheda utente, curriculum e Carico arbitri
     * devono dare lo stesso numero di designazioni.
     *
     * @return Check
     */
    private function countsDisagreeBetweenPages(): array
    {
        $year = (int) now()->year;
        $career = app(RefereeCareerService::class);
        $workload = app(AssignmentValidationService::class)->refereeWorkload()->keyBy(fn (array $r) => $r['referee']->id);

        $referees = User::with(['zone', 'assignments.tournament'])
            ->where('user_type', 'referee')->where('is_active', true)
            ->get();
        $career->preload($referees);

        $rows = $referees
            ->map(function (User $u) use ($year, $career, $workload) {
                $live = $u->assignments->filter(fn (Assignment $a) => $a->tournament->start_date->year === $year)->count();
                $cv = $career->getYearData($u, $year)['total_tournaments'];
                $w = $workload->get($u->id);
                $load = $w ? $w['zonal_count'] + $w['national_count'] : 0;

                return $live === $cv && $live === $load ? null
                    : $this->userRow($u, "scheda {$live}, curriculum {$cv}, carico {$load}");
            })
            ->filter()
            ->values()->all();

        return $this->check('conteggi_discordanti', 'Designazioni dell\'anno contate in modo diverso tra le pagine', 'errore',
            'Scheda utente, curriculum e Carico arbitri devono dare lo stesso numero.', $rows);
    }
}
