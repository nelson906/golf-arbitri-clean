<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentRole;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use App\Services\FedergolfCommitteeService;
use App\Services\FedergolfCompetitionsClient;
use App\Support\TournamentVisibility;
use App\Support\Untrusted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Strumento di importazione guidata del Comitato di Gara da federgolf.it.
 *
 * Flusso:
 *  1. Admin seleziona una gara FIG dalla lista caricata via AJAX
 *  2. Il sistema recupera il Comitato di Gara dalla pagina FIG
 *  3. I nomi vengono messi in corrispondenza (fuzzy) con gli arbitri locali
 *  4. L'admin rivede, corregge, associa il torneo locale
 *  5. Solo dopo conferma esplicita il comitato FIG SOSTITUISCE tutte le
 *     assegnazioni del torneo locale (decisione 2026-10-04)
 *
 * Nessuna scrittura automatica sul DB. Nessuna migration richiesta.
 */
class FedergolfImportController extends Controller
{
    public function __construct(
        private readonly FedergolfCommitteeService $committeeService,
        private readonly FedergolfCompetitionsClient $competitions
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PAGINA PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mostra il wizard di importazione.
     */
    public function index(): View
    {
        $this->ensureCrcOrSuperAdmin();

        // Tornei locali visibili all'admin, tutti gli anni, raggruppati per anno.
        // Niente filtro sullo stato del torneo: e' stato eliminato (P3,
        // 2026-10-03) e ora escluderebbe tornei gia' giocati da ricaricare.
        $torneiLocali = Tournament::visible()
            ->with(['club', 'tournamentType'])
            ->orderBy('start_date')   // crescente: i più imminenti prima
            ->get()
            ->map(fn (Tournament $t) => [
                'id'              => $t->id,
                'anno'            => $t->start_date->format('Y') ?? '?',
                'label'           => $t->name . ' — ' . ($t->club->name ?? 'Circolo N/D')
                                    . ' (' . ($t->start_date->format('d/m/Y') ?? '?') . ')',
                'start_date'      => $t->start_date->format('Y-m-d'),
                'club'            => $t->club->name ?? null,
                'n_assegnazioni'  => $t->assignments()->count(),
            ]);

        // Tutti gli arbitri attivi per i menu di correzione manuale
        $arbitriLocali = User::whereIn('user_type', ['referee', 'admin', 'crc', 'zona'])
            ->where('is_active', true)
            ->orderBy('name')
            ->select(['id', 'name', 'email'])
            ->get();

        $ruoli = AssignmentRole::cases();

        return view('admin.federgolf-import.index', compact('torneiLocali', 'arbitriLocali', 'ruoli'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CARICA GARE FIG (riusa logica esistente in FedergolfController)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Restituisce la lista gare FIG dell'anno corrente.
     */
    public function loadFigCompetitions(Request $request): JsonResponse
    {
        $this->ensureCrcOrSuperAdmin();

        $anno = $request->integer('anno', (int) date('Y'));
        // Accetta solo anni ragionevoli (corrente e precedente)
        $anno = in_array($anno, [(int) date('Y'), (int) date('Y') - 1]) ? $anno : (int) date('Y');

        try {
            $result = $this->competitions->fetchYear($anno);

            if (! $result['ok']) {
                return response()->json(['success' => false, 'message' => 'Errore connessione a federgolf.it']);
            }

            $gare = [];

            // Il CONFINE e' in FedergolfCompetitionsClient: qui le righe sono
            // gia' array, ma i loro campi restano non tipizzati.
            foreach ($result['rows'] as $gara) {
                if ($gara['annullata'] ?? false) {
                    continue;
                }

                $nome = Untrusted::string($gara['nome'] ?? $gara['title'] ?? null);
                if (preg_match('/ANNULLAT|RINVIAT/i', $nome)) {
                    continue;
                }

                $gare[] = [
                    // id: token opaco di federgolf, si passa com'e' (STORICO 2026-08-28)
                    'id'    => Untrusted::scalarOrNull($gara['competition_id'] ?? $gara['id'] ?? null),
                    'nome'  => $nome,
                    'data'  => Untrusted::string($gara['data'] ?? null),
                    'club'  => Untrusted::stringOrNull($gara['club'] ?? null),
                    'tipo'  => $this->detectTipo($nome),
                ];
            }

            // Ordina per data
            usort($gare, function (array $a, array $b): int {
                $da = \DateTime::createFromFormat('d/m/Y', $a['data']);
                $db = \DateTime::createFromFormat('d/m/Y', $b['data']);
                if (! $da || ! $db) {
                    return 0;
                }

                return $da <=> $db;
            });

            return response()->json(['success' => true, 'gare' => $gare]);

        } catch (\Throwable $e) {
            Log::error('FedergolfImportController::loadFigCompetitions', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Errore: ' . $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RECUPERO E MATCHING COMITATO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Recupera il Comitato di Gara da federgolf.it e lo mette in
     * corrispondenza con gli arbitri locali.
     *
     * Nessuna scrittura sul DB.
     */
    public function fetchCommittee(Request $request): JsonResponse
    {
        $this->ensureCrcOrSuperAdmin();

        $request->validate([
            'competition_id' => 'required|string|max:100',
        ]);

        $competitionId = $request->string('competition_id')->toString();

        try {
            // 1. Recupera il comitato da FIG
            $committee = $this->committeeService->fetchCommittee($competitionId);

            if (empty($committee)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comitato di Gara non trovato per questa competizione. '
                               . 'Potrebbe non essere ancora stato pubblicato su federgolf.it.',
                ]);
            }

            // 2. Metti in corrispondenza con gli arbitri locali
            $matched = $this->committeeService->matchWithUsers($committee);

            return response()->json([
                'success'  => true,
                'comitato' => $matched,
                'totale'   => count($matched),
            ]);

        } catch (\Throwable $e) {
            Log::error('FedergolfImportController::fetchCommittee', [
                'competition_id' => $competitionId,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante il recupero: ' . $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // IMPORTAZIONE (solo dopo conferma esplicita admin)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Sostituisce le assegnazioni del torneo locale con il Comitato FIG.
     *
     * Viene chiamato SOLO quando l'admin ha revisionato tutti i match
     * e ha cliccato "Conferma e importa".
     *
     * Decisione 2026-10-04: il caricamento da FIG sovrascrive i precedenti.
     * Tutte le assegnazioni del torneo (importate o fatte a mano) vengono
     * tolte e sostituite dalle righe confermate, in un'unica transazione:
     * se una riga fallisce non cambia niente.
     */
    public function executeImport(Request $request): JsonResponse
    {
        $this->ensureCrcOrSuperAdmin();

        $request->validate([
            'tournament_id'  => 'required|integer|exists:tournaments,id',
            'assegnazioni'   => 'required|array|min:1',
            'assegnazioni.*.user_id' => 'required|integer|exists:users,id',
            'assegnazioni.*.ruolo'   => ['required', 'string'],
        ]);

        $tournament = Tournament::with(['club', 'tournamentType'])->findOrFail($request->integer('tournament_id'));

        // Operazione distruttiva: solo su tornei che l'admin vede
        if (! TournamentVisibility::canAccess($tournament)) {
            abort(403, 'Non autorizzato a modificare le assegnazioni di questo torneo');
        }

        // La validazione garantisce array di array con user_id e ruolo, ma
        // l'input resta non tipizzato: si tengono solo le righe che sono array.
        $assegnazioni = Untrusted::rows($request->array('assegnazioni'));

        // Righe da creare, una per arbitro (un doppione nella lista si salta)
        $nuove = [];
        $saltati = 0;
        foreach ($assegnazioni as $item) {
            $userId = Untrusted::int($item['user_id'] ?? null);
            if (isset($nuove[$userId])) {
                $saltati++;

                continue;
            }
            $nuove[$userId] = AssignmentRole::normalize(Untrusted::string($item['ruolo'] ?? null))->value;
        }

        $precedenti = Assignment::where('tournament_id', $tournament->id)
            ->get(['id', 'user_id', 'role', 'assigned_at', 'notes']);

        $sostituite = $precedenti->map(fn (Assignment $a) => [
            'user_id'       => $a->user_id,
            'assignment_id' => $a->id,
            'ruolo_attuale' => $a->role,
            'assegnato_il'  => $a->assigned_at?->format('d/m/Y H:i'),
            'note'          => $a->notes,
        ])->values()->all();

        try {
            DB::transaction(function () use ($tournament, $nuove) {
                Assignment::where('tournament_id', $tournament->id)->delete();

                foreach ($nuove as $userId => $ruolo) {
                    Assignment::create([
                        'tournament_id' => $tournament->id,
                        'user_id'       => $userId,
                        'role'          => $ruolo,
                        'assigned_by'   => auth()->id(),
                        'assigned_at'   => now(),
                        'notes'         => 'Importato da federgolf.it',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('FedergolfImportController::executeImport', [
                'tournament_id' => $tournament->id,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'success'   => false,
                'creati'    => 0,
                'rimossi'   => 0,
                'saltati'   => 0,
                'errori'    => [$e->getMessage()],
                'messaggio' => 'Importazione non riuscita: nessuna assegnazione è stata modificata.',
                'debug'     => [
                    'database'        => DB::connection()->getDatabaseName(),
                    'tournament_id'   => $tournament->id,
                    'tournament_nome' => $tournament->name,
                    'assegnazioni_sostituite' => [],
                ],
            ]);
        }

        $creati = count($nuove);
        $rimossi = count($sostituite);

        return response()->json([
            'success'   => true,
            'creati'    => $creati,
            'rimossi'   => $rimossi,
            'saltati'   => $saltati,
            'errori'    => [],
            'messaggio' => $this->buildResultMessage($creati, $rimossi, $saltati),
            // ── diagnostica (utile per debug ambiente) ────────────────────
            'debug' => [
                'database'                => DB::connection()->getDatabaseName(),
                'tournament_id'           => $tournament->id,
                'tournament_nome'         => $tournament->name,
                'assegnazioni_sostituite' => $sostituite,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function detectTipo(string $nome): string
    {
        if (stripos($nome, 'MASCHILE') !== false) {
            return 'M';
        }
        if (stripos($nome, 'FEMMINILE') !== false) {
            return 'F';
        }

        return 'MF';
    }

    private function buildResultMessage(int $creati, int $rimossi, int $saltati): string
    {
        $msg = $rimossi > 0
            ? "Comitato sostituito: {$rimossi} assegnazioni precedenti tolte, {$creati} caricate da FIG"
            : "{$creati} assegnazioni caricate da FIG";

        if ($saltati > 0) {
            $msg .= " ({$saltati} righe doppie ignorate)";
        }

        return $msg . '.';
    }

    /**
     * Decisione 2026-10-06 (D3): l'import guidato e' riservato a CRC e super
     * admin. L'admin di zona sui nazionali designa solo osservatori (P9) e il
     * wizard sostituisce l'intero comitato: non deve poterlo aprire.
     */
    private function ensureCrcOrSuperAdmin(): void
    {
        abort_unless($this->authUser()->isNationalAdmin(), 403, 'Import riservato al CRC e al super admin');
    }
}
