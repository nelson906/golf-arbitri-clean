<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UpdateControllers extends Command
{
    protected $signature = 'update:controllers';
    protected $description = 'Aggiorna controllers per schema users unificato';

    public function handle()
    {
        $this->info('Aggiornamento Controllers per Schema Unificato');
        $this->info('============================================');

        // 1. Crea UserController unificato (sostituisce RefereeController)
        $this->createUnifiedUserController();

        // 2. Aggiorna TournamentController
        $this->updateTournamentController();

        // 3. Aggiorna AssignmentController
        $this->updateAssignmentController();

        // 4. Crea basic dashboard
        $this->createDashboardController();

        $this->info('Controllers aggiornati per schema unificato');
        $this->warn('NOTA: Backup dei controller esistenti fatto in /backup');

        return 0;
    }

    private function createUnifiedUserController()
    {
        $this->info('1. Creazione UserController unificato...');

        $controllerPath = app_path('Http/Controllers/Admin/UserController.php');

        // Backup se esiste
        if (File::exists($controllerPath)) {
            $backupPath = app_path('Http/Controllers/Admin/UserController.backup.php');
            File::copy($controllerPath, $backupPath);
            $this->warn("Backup salvato: {$backupPath}");
        }

        $content = $this->getUserControllerContent();
        File::put($controllerPath, $content);

        $this->line("✅ UserController unificato creato");
    }

    private function updateTournamentController()
    {
        $this->info('2. Aggiornamento TournamentController...');

        $controllerPath = app_path('Http/Controllers/Admin/TournamentController.php');

        if (File::exists($controllerPath)) {
            $backupPath = app_path('Http/Controllers/Admin/TournamentController.backup.php');
            File::copy($controllerPath, $backupPath);
        }

        $content = $this->getTournamentControllerContent();
        File::put($controllerPath, $content);

        $this->line("✅ TournamentController aggiornato");
    }

    private function updateAssignmentController()
    {
        $this->info('3. Aggiornamento AssignmentController...');

        $controllerPath = app_path('Http/Controllers/Admin/AssignmentController.php');

        if (File::exists($controllerPath)) {
            $backupPath = app_path('Http/Controllers/Admin/AssignmentController.backup.php');
            File::copy($controllerPath, $backupPath);
        }

        $content = $this->getAssignmentControllerContent();
        File::put($controllerPath, $content);

        $this->line("✅ AssignmentController aggiornato");
    }

    private function createDashboardController()
    {
        $this->info('4. Creazione DashboardController...');

        $controllerPath = app_path('Http/Controllers/DashboardController.php');

        $content = $this->getDashboardControllerContent();
        File::put($controllerPath, $content);

        $this->line("✅ DashboardController creato");
    }

    private function getUserControllerContent()
    {
        return '<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display users (referees + admins)
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = User::with([\'zone\'])
            ->when($request->filled(\'user_type\'), function ($q) use ($request) {
                $q->where(\'user_type\', $request->user_type);
            })
            ->when($request->filled(\'level\'), function ($q) use ($request) {
                $q->where(\'level\', $request->level);
            })
            ->when($request->filled(\'zone_id\'), function ($q) use ($request) {
                $q->where(\'zone_id\', $request->zone_id);
            })
            ->when($request->filled(\'search\'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function($q) use ($search) {
                    $q->where(\'name\', \'like\', "%{$search}%")
                      ->orWhere(\'email\', \'like\', "%{$search}%")
                      ->orWhere(\'referee_code\', \'like\', "%{$search}%");
                });
            });

        // Zone filtering for non-super admins
        if (!in_array($user->user_type, [\'super_admin\', \'national_admin\'])) {
            $query->where(\'zone_id\', $user->zone_id);
        }

        $users = $query->orderBy(\'name\')->paginate(20);

        $zones = Zone::orderBy(\'name\')->get();

        return view(\'admin.users.index\', compact(\'users\', \'zones\'));
    }

    /**
     * Show user details
     */
    public function show(User $user)
    {
        $user->load([\'zone\', \'assignments.tournament\', \'availabilities.tournament\', \'careerHistory\']);

        // Stats summary
        $stats = [
            \'total_assignments\' => $user->assignments->count(),
            \'current_year_assignments\' => $user->assignments()
                ->whereHas(\'tournament\', function($q) {
                    $q->whereYear(\'start_date\', date(\'Y\'));
                })->count(),
            \'total_availabilities\' => $user->availabilities->count(),
            \'roles_summary\' => $user->assignments->groupBy(\'role\')->map->count(),
        ];

        return view(\'admin.users.show\', compact(\'user\', \'stats\'));
    }

    /**
     * Show create form
     */
    public function create()
    {
        $zones = Zone::orderBy(\'name\')->get();
        return view(\'admin.users.create\', compact(\'zones\'));
    }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $request->validate([
            \'name\' => \'required|string|max:255\',
            \'email\' => \'required|email|unique:users\',
            \'user_type\' => \'required|in:\' . implode(\',\', array_keys(User::USER_TYPES)),
            \'zone_id\' => \'nullable|exists:zones,id\',
            \'level\' => \'nullable|in:\' . implode(\',\', array_keys(User::LEVELS)),
        ]);

        $userData = $request->only([
            \'name\', \'first_name\', \'last_name\', \'email\', \'user_type\',
            \'referee_code\', \'level\', \'gender\', \'zone_id\', \'phone\', \'city\'
        ]);

        $userData[\'password\'] = Hash::make($request->password ?? \'temp123\');

        User::create($userData);

        return redirect()->route(\'admin.users.index\')
            ->with(\'success\', \'Utente creato con successo\');
    }

    /**
     * Show edit form
     */
    public function edit(User $user)
    {
        $zones = Zone::orderBy(\'name\')->get();
        return view(\'admin.users.edit\', compact(\'user\', \'zones\'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            \'name\' => \'required|string|max:255\',
            \'email\' => \'required|email|unique:users,email,\' . $user->id,
            \'user_type\' => \'required|in:\' . implode(\',\', array_keys(User::USER_TYPES)),
        ]);

        $userData = $request->only([
            \'name\', \'first_name\', \'last_name\', \'email\', \'user_type\',
            \'referee_code\', \'level\', \'gender\', \'zone_id\', \'phone\', \'city\',
            \'is_active\'
        ]);

        $user->update($userData);

        return redirect()->route(\'admin.users.index\')
            ->with(\'success\', \'Utente aggiornato con successo\');
    }

    /**
     * Toggle user active status
     */
    public function toggleActive(User $user)
    {
        $user->update([\'is_active\' => !$user->is_active]);

        $status = $user->is_active ? \'attivato\' : \'disattivato\';

        return back()->with(\'success\', "Utente {$status} con successo");
    }
}';
    }

    private function getTournamentControllerContent()
    {
        return '<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\Club;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Models\User;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Tournament::with([\'club\', \'tournamentType\', \'zone\', \'assignments.user\'])
            ->when($request->filled(\'status\'), function ($q) use ($request) {
                $q->where(\'status\', $request->status);
            })
            ->when($request->filled(\'zone_id\'), function ($q) use ($request) {
                $q->where(\'zone_id\', $request->zone_id);
            })
            ->when($request->filled(\'tournament_type_id\'), function ($q) use ($request) {
                $q->where(\'tournament_type_id\', $request->tournament_type_id);
            });

        // Zone filtering
        if (!in_array($user->user_type, [\'super_admin\', \'national_admin\'])) {
            $query->where(\'zone_id\', $user->zone_id);
        }

        $tournaments = $query->orderByDesc(\'start_date\')->paginate(15);

        $zones = Zone::orderBy(\'name\')->get();
        $tournamentTypes = TournamentType::active()->ordered()->get();

        return view(\'admin.tournaments.index\', compact(\'tournaments\', \'zones\', \'tournamentTypes\'));
    }

    public function show(Tournament $tournament)
    {
        $tournament->load([
            \'club\', \'tournamentType\', \'zone\', \'creator\',
            \'assignments.user.zone\', \'availabilities.user\'
        ]);

        return view(\'admin.tournaments.show\', compact(\'tournament\'));
    }

    public function create()
    {
        $user = auth()->user();

        $clubs = Club::active()->inZone($user->zone_id)->orderBy(\'name\')->get();
        $tournamentTypes = TournamentType::active()->ordered()->get();
        $zones = Zone::orderBy(\'name\')->get();

        return view(\'admin.tournaments.create\', compact(\'clubs\', \'tournamentTypes\', \'zones\'));
    }

    public function store(Request $request)
    {
        $request->validate([
            \'name\' => \'required|string|max:255\',
            \'start_date\' => \'required|date\',
            \'end_date\' => \'required|date|after_or_equal:start_date\',
            \'club_id\' => \'required|exists:clubs,id\',
            \'tournament_type_id\' => \'required|exists:tournament_types,id\',
        ]);

        $tournamentData = $request->only([
            \'name\', \'start_date\', \'end_date\', \'availability_deadline\',
            \'club_id\', \'tournament_type_id\', \'zone_id\', \'description\', \'notes\'
        ]);

        $tournamentData[\'created_by\'] = auth()->id();
        $tournamentData[\'status\'] = \'draft\';

        $tournament = Tournament::create($tournamentData);

        return redirect()->route(\'admin.tournaments.show\', $tournament)
            ->with(\'success\', \'Torneo creato con successo\');
    }

    public function assignmentsForm(Tournament $tournament)
    {
        $tournament->load([\'assignments.user\', \'availabilities.user\']);

        // Available referees (con disponibilità)
        $availableReferees = User::referees()
            ->active()
            ->whereHas(\'availabilities\', function($q) use ($tournament) {
                $q->where(\'tournament_id\', $tournament->id);
            })
            ->whereDoesntHave(\'assignments\', function($q) use ($tournament) {
                $q->where(\'tournament_id\', $tournament->id);
            })
            ->with(\'zone\')
            ->orderBy(\'name\')
            ->get();

        // Other referees (senza disponibilità ma nella zona)
        $otherReferees = User::referees()
            ->active()
            ->where(\'zone_id\', $tournament->zone_id)
            ->whereDoesntHave(\'availabilities\', function($q) use ($tournament) {
                $q->where(\'tournament_id\', $tournament->id);
            })
            ->whereDoesntHave(\'assignments\', function($q) use ($tournament) {
                $q->where(\'tournament_id\', $tournament->id);
            })
            ->orderBy(\'name\')
            ->get();

        return view(\'admin.tournaments.assignments\', compact(\'tournament\', \'availableReferees\', \'otherReferees\'));
    }
}';
    }

    private function getAssignmentControllerContent()
    {
        return '<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Assignment::with([\'user\', \'tournament.club\', \'tournament.zone\', \'assignedBy\'])
            ->when($request->filled(\'tournament_id\'), function ($q) use ($request) {
                $q->where(\'tournament_id\', $request->tournament_id);
            })
            ->when($request->filled(\'user_id\'), function ($q) use ($request) {
                $q->where(\'user_id\', $request->user_id);
            })
            ->when($request->filled(\'role\'), function ($q) use ($request) {
                $q->where(\'role\', $request->role);
            });

        // Zone filtering
        if (!in_array($user->user_type, [\'super_admin\', \'national_admin\'])) {
            $query->whereHas(\'tournament\', function($q) use ($user) {
                $q->where(\'zone_id\', $user->zone_id);
            });
        }

        $assignments = $query->orderByDesc(\'assigned_at\')->paginate(20);

        return view(\'admin.assignments.index\', compact(\'assignments\'));
    }

    public function store(Request $request)
    {
        $request->validate([
            \'tournament_id\' => \'required|exists:tournaments,id\',
            \'user_id\' => \'required|exists:users,id\',
            \'role\' => \'required|in:\' . implode(\',\', array_keys(Assignment::ROLES)),
            \'notes\' => \'nullable|string\',
        ]);

        $tournament = Tournament::findOrFail($request->tournament_id);
        $referee = User::findOrFail($request->user_id);

        // Check if already assigned
        if (Assignment::where(\'tournament_id\', $tournament->id)
                      ->where(\'user_id\', $referee->id)
                      ->exists()) {
            return back()->with(\'error\', \'Arbitro già assegnato a questo torneo\');
        }

        Assignment::create([
            \'tournament_id\' => $tournament->id,
            \'user_id\' => $referee->id,
            \'role\' => $request->role,
            \'notes\' => $request->notes,
            \'assigned_by\' => auth()->id(),
            \'assigned_at\' => now(),
        ]);

        return back()->with(\'success\', "Arbitro {$referee->name} assegnato come {$request->role}");
    }

    public function destroy(Assignment $assignment)
    {
        $assignment->delete();

        return back()->with(\'success\', \'Assegnazione rimossa con successo\');
    }
}';
    }

    private function getDashboardControllerContent()
    {
        return '<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Tournament;
use App\Models\Assignment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Redirect based on user type
        switch ($user->user_type) {
            case \'super_admin\':
            case \'national_admin\':
                return $this->adminDashboard($user);
            case \'admin\':
                return $this->zoneDashboard($user);
            case \'referee\':
                return $this->refereeDashboard($user);
            default:
                abort(403);
        }
    }

    private function adminDashboard($user)
    {
        $stats = [
            \'total_users\' => User::count(),
            \'active_referees\' => User::referees()->active()->count(),
            \'total_tournaments\' => Tournament::count(),
            \'active_tournaments\' => Tournament::whereIn(\'status\', [\'open\', \'assigned\'])->count(),
            \'pending_assignments\' => Assignment::whereHas(\'tournament\', function($q) {
                $q->where(\'status\', \'open\');
            })->count(),
        ];

        $recentTournaments = Tournament::with([\'club\', \'zone\', \'tournamentType\'])
            ->latest()
            ->limit(5)
            ->get();

        return view(\'admin.dashboard\', compact(\'stats\', \'recentTournaments\'));
    }

    private function zoneDashboard($user)
    {
        $stats = [
            \'zone_referees\' => User::referees()->inZone($user->zone_id)->active()->count(),
            \'zone_tournaments\' => Tournament::inZone($user->zone_id)->count(),
            \'pending_tournaments\' => Tournament::inZone($user->zone_id)
                ->where(\'status\', \'open\')
                ->count(),
        ];

        $zoneTournaments = Tournament::with([\'club\', \'tournamentType\'])
            ->inZone($user->zone_id)
            ->latest()
            ->limit(5)
            ->get();

        return view(\'admin.zone-dashboard\', compact(\'stats\', \'zoneTournaments\'));
    }

    private function refereeDashboard($user)
    {
        $user->load([\'assignments.tournament\', \'availabilities.tournament\']);

        $stats = [
            \'total_assignments\' => $user->assignments->count(),
            \'current_year_assignments\' => $user->assignments()
                ->whereHas(\'tournament\', function($q) {
                    $q->whereYear(\'start_date\', date(\'Y\'));
                })->count(),
            \'pending_tournaments\' => Tournament::where(\'status\', \'open\')
                ->where(\'availability_deadline\', \'>\', now())
                ->count(),
        ];

        $upcomingAssignments = $user->assignments()
            ->with(\'tournament.club\')
            ->whereHas(\'tournament\', function($q) {
                $q->where(\'start_date\', \'>=\', now())
                  ->where(\'status\', \'!=\', \'cancelled\');
            })
            ->orderBy(function($query) {
                $query->select(\'start_date\')
                      ->from(\'tournaments\')
                      ->whereColumn(\'tournaments.id\', \'assignments.tournament_id\');
            })
            ->limit(5)
            ->get();

        return view(\'referee.dashboard\', compact(\'stats\', \'upcomingAssignments\'));
    }
}';
    }
}
