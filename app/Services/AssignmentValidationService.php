<?php

namespace App\Services;

use App\Enums\AssignmentRole;
use App\Enums\RefereeLevel;
use App\Helpers\RefereeLevelsHelper;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service per la validazione e il controllo qualità delle assegnazioni
 *
 * @phpstan-type ConflictRow array{
 *     referee: \App\Models\User,
 *     assignment1: \App\Models\Assignment,
 *     assignment2: \App\Models\Assignment,
 *     severity: string,
 * }
 */
class AssignmentValidationService
{
    /**
     * Ottieni un riepilogo completo di tutte le validazioni
     *
     * @return array{
     *     conflicts: int,
     *     missing_requirements: int,
     *     overassigned: int,
     *     underassigned: int,
     *     total_issues: int,
     * }
     */
    public function getValidationSummary(?int $zoneId = null, bool $nationalOnly = false): array
    {
        $conflicts = $this->getConflictsSummary($zoneId, $nationalOnly);
        $missingRequirements = $this->getMissingRequirementsSummary($zoneId, $nationalOnly);
        $overassigned = $this->getOverassignedCount($zoneId, $nationalOnly);
        $underassigned = $this->getUnderassignedCount($zoneId, $nationalOnly);

        return [
            'conflicts' => $conflicts,
            'missing_requirements' => $missingRequirements,
            'overassigned' => $overassigned,
            'underassigned' => $underassigned,
            'total_issues' => $conflicts + $missingRequirements + $overassigned + $underassigned,
        ];
    }

    /**
     * Rileva conflitti di date nelle assegnazioni
     * @return \Illuminate\Support\Collection<int, ConflictRow>
     */
    public function detectDateConflicts(?int $zoneId = null, bool $nationalOnly = false): Collection
    {
        $query = Assignment::with(['user', 'tournament.club', 'tournament.zone'])
            ->whereHas('tournament', function ($q) use ($zoneId, $nationalOnly) {
                // Solo tornei non ancora conclusi (lo stato del torneo non esiste piu')
                $q->where('end_date', '>=', now()->startOfDay());
                if ($zoneId) {
                    // Zona dal circolo o, per i tornei T.B.A., dalla colonna (D10)
                    $q->where(fn ($z) => $z
                        ->whereHas('club', fn ($c) => $c->where('zone_id', $zoneId))
                        ->orWhere('zone_id', $zoneId));
                }
                // CRC: solo tornei nazionali (stessa regola di TournamentVisibility)
                if ($nationalOnly) {
                    $q->whereHas('tournamentType', fn ($tt) => $tt->where('is_national', true));
                }
            });

        $assignments = $query->get();

        /** @var \Illuminate\Support\Collection<int, ConflictRow> $conflicts */
        $conflicts = collect();

        // Raggruppa per arbitro
        $byReferee = $assignments->groupBy('user_id');

        foreach ($byReferee as $userId => $refereeAssignments) {
            // Ordina per data
            // values(): sortBy conserva le chiavi, e slice($index + 1) le usa
            // come posizioni (un'assegnazione fuori ordine veniva confrontata
            // con se stessa e il conflitto vero si perdeva)
            $sorted = $refereeAssignments->sortBy(function ($a) {
                return $a->tournament->start_date;
            })->values();

            // Cerca sovrapposizioni
            foreach ($sorted as $index => $assignment) {
                $nextAssignments = $sorted->slice($index + 1);

                foreach ($nextAssignments as $nextAssignment) {
                    if ($this->datesOverlap($assignment, $nextAssignment)) {
                        $conflicts->push([
                            'referee' => $assignment->user,
                            'assignment1' => $assignment,
                            'assignment2' => $nextAssignment,
                            'severity' => $this->calculateConflictSeverity($assignment, $nextAssignment),
                        ]);
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Trova tornei con requisiti mancanti
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function findMissingRequirements(?int $zoneId = null, bool $nationalOnly = false): Collection
    {
        $query = Tournament::with(['tournamentType', 'assignments.user', 'club.zone'])
            ->where('end_date', '>=', now()->startOfDay());

        if ($zoneId) {
            $query->where(fn ($z) => $z
                ->whereHas('club', fn ($q) => $q->where('zone_id', $zoneId))
                ->orWhere('zone_id', $zoneId));
        }

        // CRC: solo tornei nazionali (stessa regola di TournamentVisibility)
        if ($nationalOnly) {
            $query->whereHas('tournamentType', fn ($tt) => $tt->where('is_national', true));
        }

        $tournaments = $query->get();
        $issues = collect();

        foreach ($tournaments as $tournament) {
            $tournamentIssues = [];

            // Skip tornei senza tipo associato (non si può validare senza requisiti)
            if (! $tournament->tournamentType) {
                continue;
            }

            // Controlla numero minimo arbitri
            $minReferees = $tournament->tournamentType->min_referees ?? 0;
            if ($tournament->assignments->count() < $minReferees) {
                $tournamentIssues[] = [
                    'type' => 'min_referees',
                    'message' => "Arbitri assegnati: {$tournament->assignments->count()}, richiesti: {$minReferees}",
                    'severity' => 'high',
                ];
            }

            // Controlla livello arbitri (usa RefereeLevelsHelper per normalizzazione)
            $requiredLevel = RefereeLevelsHelper::normalize($tournament->tournamentType->required_level ?? '');
            $levels = array_keys(RefereeLevelsHelper::DB_ENUM_VALUES);
            $requiredIndex = array_search($requiredLevel, $levels);

            $inadequateReferees = $tournament->assignments->filter(function ($assignment) use ($levels, $requiredIndex) {
                $normalizedUserLevel = RefereeLevelsHelper::normalize($assignment->user->level);
                $userIndex = array_search($normalizedUserLevel, $levels);

                return $userIndex === false || $userIndex < $requiredIndex;
            });

            if ($inadequateReferees->count() > 0) {
                $tournamentIssues[] = [
                    'type' => 'referee_level',
                    'message' => "{$inadequateReferees->count()} arbitri non hanno il livello richiesto ({$requiredLevel})",
                    'severity' => 'high',
                    'referees' => $inadequateReferees->pluck('user.name'),
                ];
            }

            // Controlla zona per tornei non nazionali
            if (! $tournament->tournamentType->is_national) {
                $wrongZoneReferees = $tournament->assignments->filter(function ($assignment) use ($tournament) {
                    return $assignment->user->zone_id !== $tournament->zone_id;
                });

                if ($wrongZoneReferees->count() > 0) {
                    $tournamentIssues[] = [
                        'type' => 'wrong_zone',
                        'message' => "{$wrongZoneReferees->count()} arbitri appartengono a zone diverse",
                        'severity' => 'medium',
                        'referees' => $wrongZoneReferees->pluck('user.name'),
                    ];
                }
            }

            // Controlla presenza ruoli chiave
            $roles = $tournament->assignments->pluck('role');
            // D11: nazionale secondo is_national, unica fonte di verita'
            if (! $roles->contains(AssignmentRole::TournamentDirector->value) && ($tournament->tournamentType->is_national ?? false)) {
                $tournamentIssues[] = [
                    'type' => 'missing_role',
                    'message' => 'Manca il Direttore di Torneo',
                    'severity' => 'high',
                ];
            }

            if (! empty($tournamentIssues)) {
                $issues->push([
                    'tournament' => $tournament,
                    'issues' => $tournamentIssues,
                    'total_severity' => $this->calculateTotalSeverity($tournamentIssues),
                ]);
            }
        }

        return $issues->sortByDesc('total_severity');
    }

    /**
     * Su quale conteggio si giudica il carico (decisione 2026-10-08):
     * il CRC vede e giudica solo i tornei nazionali; admin di zona e super
     * admin vedono zonali e nazionali e giudicano sul totale (la zona designa
     * anche gli osservatori sui nazionali).
     *
     * @return 'national'|'total'
     */
    public function countBasis(?int $zoneId, bool $nationalOnly): string
    {
        return $nationalOnly ? 'national' : 'total';
    }

    /**
     * Arbitri attivi con le designazioni dell'anno contate per tipo di torneo:
     * zonal_count (tornei zonali), national_count (tornei nazionali, osservatori
     * compresi), national_observers (di cui osservatori).
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function refereesWithCounts(?int $zoneId, bool $nationalOnly): \Illuminate\Database\Eloquent\Builder
    {
        $year = (int) date('Y');
        $onType = fn (bool $national) => fn ($q) => $q->whereHas('tournament', fn ($t) => $t
            ->whereYear('start_date', $year)
            ->whereHas('tournamentType', fn ($tt) => $tt->where('is_national', $national)));

        $query = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount([
                'assignments as zonal_count' => $onType(false),
                'assignments as national_count' => $onType(true),
                'assignments as national_observers' => fn ($q) => $onType(true)($q)
                    ->where('role', \App\Enums\AssignmentRole::Observer->value),
            ]);

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        // CRC: solo arbitri di livello Nazionale e Internazionale
        if ($nationalOnly) {
            $query->whereIn('level', [RefereeLevel::Nazionale->value, RefereeLevel::Internazionale->value]);
        }

        return $query;
    }

    /** Il conteggio che conta per chi guarda (vedi countBasis). */
    private function basisCount(User $referee, string $basis): int
    {
        $zonal = \App\Support\Untrusted::int($referee->getAttribute('zonal_count'));
        $national = \App\Support\Untrusted::int($referee->getAttribute('national_count'));

        return $basis === 'national' ? $national : $zonal + $national;
    }

    /**
     * Righe comuni alle due liste.
     *
     * @return array{referee: User, assignments_count: int, zonal_count: int, national_count: int, national_observers: int}
     */
    private function countRow(User $referee, string $basis): array
    {
        return [
            'referee' => $referee,
            'assignments_count' => $this->basisCount($referee, $basis),
            'zonal_count' => \App\Support\Untrusted::int($referee->getAttribute('zonal_count')),
            'national_count' => \App\Support\Untrusted::int($referee->getAttribute('national_count')),
            'national_observers' => \App\Support\Untrusted::int($referee->getAttribute('national_observers')),
        ];
    }

    /**
     * Trova arbitri sovrassegnati
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function findOverassignedReferees(?int $zoneId = null, int $threshold = 5, bool $nationalOnly = false): Collection
    {
        $basis = $this->countBasis($zoneId, $nationalOnly);

        // Filtro dopo get(): HAVING su un conteggio di sottoquery non e' portabile
        $over = $this->refereesWithCounts($zoneId, $nationalOnly)
            ->with('zone')
            ->get()
            ->map(fn (User $referee) => $this->countRow($referee, $basis))
            ->filter(fn (array $row) => $row['assignments_count'] > $threshold)
            ->sortByDesc('assignments_count')
            ->values();

        $avg = $over->avg('assignments_count');

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
        $rows = $over->map(fn (array $row) => $row + [
            'over_threshold' => $row['assignments_count'] - $threshold,
            'workload_percentage' => $avg > 0 ? round(($row['assignments_count'] / $avg) * 100, 1) : 0,
        ]);

        return $rows;
    }

    /**
     * Trova arbitri sottoutilizzati
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function findUnderassignedReferees(?int $zoneId = null, int $threshold = 2, bool $nationalOnly = false): Collection
    {
        $basis = $this->countBasis($zoneId, $nationalOnly);

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
        $rows = $this->refereesWithCounts($zoneId, $nationalOnly)
            ->with('zone')
            ->get()
            ->map(fn (User $referee) => $this->countRow($referee, $basis))
            ->filter(fn (array $row) => $row['assignments_count'] < $threshold)
            ->sortBy('assignments_count')
            ->map(fn (array $row) => $row + [
                'under_threshold' => $threshold - $row['assignments_count'],
                'availability_status' => $this->checkAvailabilityStatus($row['referee']),
            ])
            ->values();

        return $rows;
    }

    /**
     * Suggerisci correzioni automatiche per i conflitti
     * @param  \Illuminate\Support\Collection<int, ConflictRow>  $conflicts
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function suggestConflictResolutions(Collection $conflicts): Collection
    {
        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $rows */
        $rows = $conflicts->map(function ($conflict) {
            $suggestions = [];

            // Suggerisci arbitri alternativi per assignment2
            $alternativeReferees = $this->findAlternativeReferees(
                $conflict['assignment2']->tournament,
                $conflict['referee']->id
            );

            if ($alternativeReferees->count() > 0) {
                $suggestions[] = [
                    'action' => 'replace_referee',
                    'assignment_id' => $conflict['assignment2']->id,
                    'current_referee' => $conflict['referee'],
                    'alternative_referees' => $alternativeReferees->take(3),
                    'priority' => 'high',
                ];
            }

            // Se è un conflitto minore, suggerisci di verificare orari
            if ($conflict['severity'] === 'low') {
                $suggestions[] = [
                    'action' => 'verify_timing',
                    'message' => 'I tornei potrebbero non sovrapporsi se uno finisce prima dell\'altro',
                    'priority' => 'low',
                ];
            }

            return array_merge($conflict, ['suggestions' => $suggestions]);
        });

        return $rows;
    }

    // ============ METODI PRIVATI HELPER ============

    private function datesOverlap(Assignment $a1, Assignment $a2): bool
    {
        $start1 = Carbon::parse($a1->tournament->start_date);
        // Se end_date è null, Carbon::parse(null) restituisce la data corrente causando falsi conflitti.
        // Usiamo la fine del giorno di start_date come fallback sicuro.
        $end1 = $a1->tournament->end_date
            ? Carbon::parse($a1->tournament->end_date)
            : Carbon::parse($a1->tournament->start_date)->endOfDay();

        $start2 = Carbon::parse($a2->tournament->start_date);
        $end2 = $a2->tournament->end_date
            ? Carbon::parse($a2->tournament->end_date)
            : Carbon::parse($a2->tournament->start_date)->endOfDay();

        return $start1->lte($end2) && $start2->lte($end1);
    }

    private function calculateConflictSeverity(Assignment $a1, Assignment $a2): string
    {
        $start1 = Carbon::parse($a1->tournament->start_date);
        $end1 = $a1->tournament->end_date
            ? Carbon::parse($a1->tournament->end_date)
            : Carbon::parse($a1->tournament->start_date)->endOfDay();

        $start2 = Carbon::parse($a2->tournament->start_date);
        $end2 = $a2->tournament->end_date
            ? Carbon::parse($a2->tournament->end_date)
            : Carbon::parse($a2->tournament->start_date)->endOfDay();

        // Calcola i giorni di effettiva sovrapposizione (non la differenza tra start_date)
        $overlapStart = $start1->max($start2);
        $overlapEnd = $end1->min($end2);
        $overlapDays = (int) $overlapStart->diffInDays($overlapEnd);

        if ($overlapDays >= 2) {
            return 'high'; // Sovrapposizione di 2+ giorni
        } elseif ($overlapDays >= 1) {
            return 'medium'; // Sovrapposizione di 1 giorno
        }

        return 'low'; // Sovrapposizione parziale nello stesso giorno
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     */
    private function calculateTotalSeverity(array $issues): int
    {
        $score = 0;
        foreach ($issues as $issue) {
            $score += match ($issue['severity']) {
                'high' => 3,
                'medium' => 2,
                'low' => 1,
                default => 0,
            };
        }

        return $score;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    private function findAlternativeReferees(Tournament $tournament, int $excludeUserId): Collection
    {
        // Usa RefereeLevelsHelper per normalizzazione livelli (null-safe: tournamentType può essere null)
        $requiredLevel = RefereeLevelsHelper::normalize($tournament->tournamentType->required_level ?? '');
        $levels = array_keys(RefereeLevelsHelper::DB_ENUM_VALUES);
        $requiredIndex = array_search($requiredLevel, $levels);

        $query = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->where('id', '!=', $excludeUserId)
            ->whereNotIn('id', $tournament->assignments->pluck('user_id'));

        // Filtra per livello (usa i valori ENUM del database)
        $acceptableLevels = array_slice($levels, $requiredIndex !== false ? $requiredIndex : 0);
        $query->whereIn('level', $acceptableLevels);

        // Filtra per zona se non nazionale (null-safe: se tournamentType è null, tratta come non nazionale)
        if (! ($tournament->tournamentType->is_national ?? false)) {
            $query->where('zone_id', $tournament->zone_id);
        }

        // Escludi arbitri con conflitti nella stessa data
        $query->whereDoesntHave('assignments', function ($q) use ($tournament) {
            $q->whereHas('tournament', function ($tq) use ($tournament) {
                $tq->where(function ($dateQuery) use ($tournament) {
                    $dateQuery->whereBetween('start_date', [
                        $tournament->start_date,
                        $tournament->end_date,
                    ])->orWhereBetween('end_date', [
                        $tournament->start_date,
                        $tournament->end_date,
                    ]);
                });
            });
        });

        return $query->withCount('assignments')->orderBy('assignments_count')->get();
    }

    private function checkAvailabilityStatus(User $referee): string
    {
        // Considera solo disponibilità per tornei dell'anno corrente o futuri,
        // per evitare che disponibilità di anni passati vengano conteggiate come "disponibile".
        $hasAvailabilities = $referee->availabilities()
            ->whereHas('tournament', function ($q) {
                $q->whereYear('start_date', date('Y'))
                    ->orWhere('start_date', '>=', now());
            })
            ->exists();

        return $hasAvailabilities ? 'available' : 'unavailable';
    }

    private function getConflictsSummary(?int $zoneId, bool $nationalOnly): int
    {
        return $this->detectDateConflicts($zoneId, $nationalOnly)->count();
    }

    private function getMissingRequirementsSummary(?int $zoneId, bool $nationalOnly): int
    {
        return $this->findMissingRequirements($zoneId, $nationalOnly)->count();
    }

    private function getOverassignedCount(?int $zoneId, bool $nationalOnly, int $threshold = 5): int
    {
        return $this->findOverassignedReferees($zoneId, $threshold, $nationalOnly)->count();
    }

    private function getUnderassignedCount(?int $zoneId, bool $nationalOnly, int $threshold = 2): int
    {
        return $this->findUnderassignedReferees($zoneId, $threshold, $nationalOnly)->count();
    }
}
