<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RefereeCareerService;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RefereeCareerController extends Controller
{
    use HasZoneVisibility;

    protected RefereeCareerService $careerService;

    public function __construct(RefereeCareerService $careerService)
    {
        $this->careerService = $careerService;
    }

    /**
     * Display a list of all referees with their career stats
     */
    public function curricula(Request $request): View
    {
        $year = $request->integer('year', now()->year);
        $search = $request->string('search')->toString() ?: null;
        $sort = $request->string('sort', 'last_name')->toString();
        $direction = $request->string('direction', 'asc')->toString();

        // Gestione zona con default per admin zonale
        $user = auth()->user();
        $zone = $request->string('zone')->toString() ?: null;

        // Se non è specificata una zona e l'utente è admin zonale, usa la sua zona
        if (! $request->has('zone') && $this->isZoneAdmin($user)) {
            $zone = $this->getUserZoneId($user);
        }

        $level = $request->string('level')->toString() ?: null;

        // Ottieni gli anni disponibili dalla tabella referee_career_history
        $historyYears = DB::table('referee_career_history')
            ->select('assignments_by_year')
            ->whereNotNull('assignments_by_year')
            ->get()
            ->flatMap(function ($record) {
                // assignments_by_year e' una colonna JSON: json_decode puo'
                // restituire qualunque cosa, comprese righe storiche malformate.
                $assignmentsByYear = json_decode((string) $record->assignments_by_year, true);

                return is_array($assignmentsByYear) ? array_keys($assignmentsByYear) : [];
            })
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Combina con anni correnti
        $tournamentYears = DB::table('tournaments')
            ->selectRaw('YEAR(start_date) as year')
            ->whereNotNull('start_date')
            ->groupBy('year')
            ->pluck('year')
            ->toArray();

        $allYears = array_unique(array_merge($historyYears, $tournamentYears));
        rsort($allYears);

        // Se non ci sono dati, usa range completo
        if (empty($allYears)) {
            $allYears = range(2025, 2015);
        }

        $query = User::where('user_type', 'referee')
            ->where('level', '!=', 'Archivio')
            ->with('zone');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('referee_code', 'like', "%{$search}%");
            });
        }

        if ($zone) {
            $query->where('zone_id', $zone);
        }

        if ($level) {
            if ($level === 'Nazionale') {
                $query->whereIn('level', ['Nazionale', 'Internazionale']);
            } elseif ($level === 'Zonale') {
                $query->whereIn('level', ['Aspirante', '1_livello', 'Regionale']);
            } else {
                $query->where('level', $level);
            }
        }

        $query->orderBy($sort, $direction);
        $referees = $query->get();

        $stats = $referees->map(function ($referee) use ($year) {
            $fullCareerData = $this->careerService->getCareerData($referee);
            $yearSpecificData = null;
            if ($year) {
                $yearSpecificData = $this->careerService->getYearData($referee, $year);
            }

            return [
                'referee' => $referee,
                'stats' => $fullCareerData['career_summary'] ?? [
                    'total_assignments' => 0,
                    'roles_summary' => [],
                    'first_year' => null,
                ],
                'year_data' => $yearSpecificData ?? [
                    'level' => $referee->level,
                    'total_tournaments' => 0,
                    'roles' => [],
                ],
            ];
        });

        return view('admin.referees.curricula', [
            'stats' => $stats,
            'year' => $year,
            'years' => $allYears,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
            'zone' => $zone,
            'level' => $level,
        ]);
    }

    /**
     * Display career details for a specific referee
     */
    public function curriculum(User $referee): View
    {
        $careerData = $this->careerService->getCareerData($referee);

        return view('admin.referees.curriculum', [
            'referee' => $referee,
            'careerData' => $careerData,
            'isAdmin' => true,
        ]);
    }
}
