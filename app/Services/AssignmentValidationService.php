<?php

namespace App\Services;

use App\Helpers\RefereeLevelsHelper;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service per la validazione e il controllo qualità delle assegnazioni
 */
class AssignmentValidationService
{
    /**
     * Ottieni un riepilogo completo di tutte le validazioni
     */
    public function getValidationSummary(?int $zoneId = null): array
    {
        return [
            'conflicts' => $this->getConflictsSummary($zoneId),
            'missing_requirements' => $this->getMissingRequirementsSummary($zoneId),
            'overassigned' => $this->getOverassignedCount($zoneId),
            'underassigned' => $this->getUnderassignedCount($zoneId),
            'total_issues' => $this->getTotalIssuesCount($zoneId),
        ];
    }

    /**
     * Rileva conflitti di date nelle assegnazioni
     */
    public function detectDateConflicts(?int $zoneId = null): Collection
    {
        $query = Assignment::with(['user', 'tournament.club', 'tournament.zone'])
            ->whereHas('tournament', function ($q) use ($zoneId) {
                $q->whereIn('status', ['open', 'closed']);
                if ($zoneId) {
                    $q->where('zone_id', $zoneId);
                }
            });

        $assignments = $query->get();
        $conflicts = collect();

        // Raggruppa per arbitro
        $byReferee = $assignments->groupBy('user_id');

        foreach ($byReferee as $userId => $refereeAssignments) {
            // Ordina per data
            $sorted = $refereeAssignments->sortBy(function ($a) {
                return $a->tournament->start_date;
            });

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
     */
    public function findMissingRequirements(?int $zoneId = null): Collection
    {
        $query = Tournament::with(['tournamentType', 'assignments.user', 'zone'])
            ->whereIn('status', ['open', 'closed']);

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        $tournaments = $query->get();
        $issues = collect();

        foreach ($tournaments as $tournament) {
            $tournamentIssues = [];

            // Controlla numero minimo arbitri
            if ($tournament->assignments->count() < $tournament->tournamentType->min_referees) {
                $tournamentIssues[] = [
                    'type' => 'min_referees',
                    'message' => "Arbitri assegnati: {$tournament->assignments->count()}, richiesti: {$tournament->tournamentType->min_referees}",
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
            if (! $roles->contains('Direttore di Torneo') && $tournament->tournamentType->level === 'nazionale') {
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
     * Trova arbitri sovrassegnati
     */
    public function findOverassignedReferees(?int $zoneId = null, int $threshold = 5): Collection
    {
        $query = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount(['assignments' => function ($q) {
                $q->whereHas('tournament', function ($tq) {
                    $tq->whereIn('status', ['open', 'closed'])
                        ->whereYear('start_date', date('Y'));
                });
            }]);

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        // Filtering after get() for SQLite compatibility (HAVING on subquery count not supported)
        return $query
            ->with(['zone', 'assignments' => function ($q) {
                $q->whereHas('tournament', function ($tq) {
                    $tq->whereIn('status', ['open', 'closed'])
                        ->whereYear('start_date', date('Y'));
                })->with('tournament');
            }])
            ->get()
            ->filter(fn ($referee) => $referee->assignments_count > $threshold)
            ->sortByDesc('assignments_count')
            ->map(function ($referee) use ($threshold) {
                return [
                    'referee' => $referee,
                    'assignments_count' => $referee->assignments_count,
                    'over_threshold' => $referee->assignments_count - $threshold,
                    'workload_percentage' => $this->calculateWorkloadPercentage($referee),
                ];
            });
    }

    /**
     * Trova arbitri sottoutilizzati
     */
    public function findUnderassignedReferees(?int $zoneId = null, int $threshold = 2): Collection
    {
        $query = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount(['assignments' => function ($q) {
                $q->whereHas('tournament', function ($tq) {
                    $tq->whereIn('status', ['open', 'closed'])
                        ->whereYear('start_date', date('Y'));
                });
            }]);

        if ($zoneId) {
            $query->where('zone_id', $zoneId);
        }

        // Filtering after get() for SQLite compatibility (HAVING on subquery count not supported)
        return $query
            ->with(['zone'])
            ->get()
            ->filter(fn ($referee) => $referee->assignments_count < $threshold)
            ->sortBy('assignments_count')
            ->map(function ($referee) use ($threshold) {
                return [
                    'referee' => $referee,
                    'assignments_count' => $referee->assignments_count,
                    'under_threshold' => $threshold - $referee->assignments_count,
                    'availability_status' => $this->checkAvailabilityStatus($referee),
                ];
            });
    }

    /**
     * Suggerisci correzioni automatiche per i conflitti
     */
    public function suggestConflictResolutions(Collection $conflicts): Collection
    {
        return $conflicts->map(function ($conflict) {
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
    }

    /**
     * Applica correzioni automatiche (quando possibile)
     */
    public function applyAutomaticFixes(?int $zoneId = null): array
    {
        $fixed = [];
        $failed = [];

        // 1. Risolvi conflitti semplici sostituendo arbitri
        $conflicts = $this->detectDateConflicts($zoneId);

        foreach ($conflicts as $conflict) {
            if ($conflict['severity'] === 'high') {
                $alternatives = $this->findAlternativeReferees(
                    $conflict['assignment2']->tournament,
                    $conflict['referee']->id
                );

                if ($alternatives->count() > 0) {
                    try {
                        $conflict['assignment2']->update([
                            'user_id' => $alternatives->first()->id,
                        ]);
                        $fixed[] = [
                            'type' => 'conflict_resolved',
                            'tournament' => $conflict['assignment2']->tournament->name,
                            'old_referee' => $conflict['referee']->name,
                            'new_referee' => $alternatives->first()->name,
                        ];
                    } catch (\Exception $e) {
                        $failed[] = [
                            'type' => 'conflict_resolution_failed',
                            'tournament' => $conflict['assignment2']->tournament->name,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return [
            'fixed' => $fixed,
            'failed' => $failed,
            'summary' => [
                'total_fixed' => count($fixed),
                'total_failed' => count($failed),
            ],
        ];
    }

    // ============ METODI PRIVATI HELPER ============

    private function datesOverlap(Assignment $a1, Assignment $a2): bool
    {
        $start1 = Carbon::parse($a1->tournament->start_date);
        $end1 = Carbon::parse($a1->tournament->end_date);
        $start2 = Carbon::parse($a2->tournament->start_date);
        $end2 = Carbon::parse($a2->tournament->end_date);

        return $start1->lte($end2) && $start2->lte($end1);
    }

    private function calculateConflictSeverity(Assignment $a1, Assignment $a2): string
    {
        $start1 = Carbon::parse($a1->tournament->start_date);
        $start2 = Carbon::parse($a2->tournament->start_date);

        $daysDiff = abs($start1->diffInDays($start2));

        if ($daysDiff === 0) {
            return 'high'; // Stesso giorno
        } elseif ($daysDiff <= 1) {
            return 'medium'; // Giorni consecutivi
        }

        return 'low';
    }

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

    private function findAlternativeReferees(Tournament $tournament, int $excludeUserId): Collection
    {
        // Usa RefereeLevelsHelper per normalizzazione livelli
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

        // Filtra per zona se non nazionale
        if (! $tournament->tournamentType->is_national) {
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

    private function calculateWorkloadPercentage(User $referee): float
    {
        // Ottieni tutti gli arbitri attivi con il loro conteggio di assegnazioni
        $allReferees = User::where('user_type', 'referee')
            ->where('is_active', true)
            ->withCount(['assignments' => function ($q) {
                $q->whereHas('tournament', function ($tq) {
                    $tq->whereIn('status', ['open', 'closed'])
                        ->whereYear('start_date', date('Y'));
                });
            }])
            ->get();

        if ($allReferees->isEmpty()) {
            return 0;
        }

        // Calcola la media manualmente dalla collection
        $avgAssignments = $allReferees->avg('assignments_count');

        if ($avgAssignments == 0) {
            return 0;
        }

        return round(($referee->assignments_count / $avgAssignments) * 100, 1);
    }

    private function checkAvailabilityStatus(User $referee): string
    {
        // Controlla se l'arbitro ha dichiarato disponibilità (la presenza del record indica disponibilità)
        if (DB::getSchemaBuilder()->hasTable('availabilities')) {
            $hasAvailabilities = DB::table('availabilities')
                ->where('user_id', $referee->id)
                ->exists();

            return $hasAvailabilities ? 'available' : 'unavailable';
        }

        return 'unknown';
    }

    private function getConflictsSummary(?int $zoneId): int
    {
        return $this->detectDateConflicts($zoneId)->count();
    }

    private function getMissingRequirementsSummary(?int $zoneId): int
    {
        return $this->findMissingRequirements($zoneId)->count();
    }

    private function getOverassignedCount(?int $zoneId, int $threshold = 5): int
    {
        return $this->findOverassignedReferees($zoneId, $threshold)->count();
    }

    private function getUnderassignedCount(?int $zoneId, int $threshold = 2): int
    {
        return $this->findUnderassignedReferees($zoneId, $threshold)->count();
    }

    private function getTotalIssuesCount(?int $zoneId): int
    {
        return $this->getConflictsSummary($zoneId) +
            $this->getMissingRequirementsSummary($zoneId) +
            $this->getOverassignedCount($zoneId) +
            $this->getUnderassignedCount($zoneId);
    }
}
