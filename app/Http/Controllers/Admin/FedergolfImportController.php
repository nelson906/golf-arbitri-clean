<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentRole;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\User;
use App\Services\FedergolfCommitteeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
 *  5. Solo dopo conferma esplicita vengono create le assegnazioni
 *
 * Nessuna scrittura automatica sul DB. Nessuna migration richiesta.
 */
class FedergolfImportController extends Controller
{
    public function __construct(
        private readonly FedergolfCommitteeService $committeeService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // PAGINA PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mostra il wizard di importazione.
     */
    public function index(): View
    {
        // Tornei locali: tutti gli anni per flessibilità, raggruppati per anno
        // Includono anche tornei già assegnati (l'admin potrebbe voler integrare)
        $torneiLocali = Tournament::with(['club', 'tournamentType'])
            ->whereIn('status', ['draft', 'open', 'assigned'])
            ->orderBy('start_date')   // crescente: i più imminenti prima
            ->get()
            ->map(fn (Tournament $t) => [
                'id'              => $t->id,
                'anno'            => $t->start_date?->format('Y') ?? '?',
                'label'           => $t->name . ' — ' . ($t->club->name ?? 'Circolo N/D')
                                    . ' (' . ($t->start_date?->format('d/m/Y') ?? '?') . ')',
                'start_date'      => $t->start_date?->format('Y-m-d'),
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
        $anno = $request->integer('anno', (int) date('Y'));
        // Accetta solo anni ragionevoli (corrente e precedente)
        $anno = in_array($anno, [(int) date('Y'), (int) date('Y') - 1]) ? $anno : (int) date('Y');

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'User-Agent'       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                    'Accept'           => 'application/json',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->post('https://www.federgolf.it/wp-admin/admin-ajax.php', [
                    'action'  => 'competitions-search',
                    'tipo'    => '',
                    'keyword' => '',
                    'anno'    => $anno,
                    'mese'    => '',
                ]);

            if (! $response->successful()) {
                return response()->json(['success' => false, 'message' => 'Errore connessione a federgolf.it']);
            }

            $data = $response->json();
            $oggi = new \DateTime;
            $gare = [];

            foreach ($data['data'] ?? [] as $gara) {
                if ($gara['annullata'] ?? false) {
                    continue;
                }

                $nome = $gara['nome'] ?? $gara['title'] ?? '';
                if (preg_match('/ANNULLAT|RINVIAT/i', $nome)) {
                    continue;
                }

                $gare[] = [
                    'id'    => $gara['competition_id'] ?? $gara['id'],
                    'nome'  => $nome,
                    'data'  => $gara['data'] ?? null,
                    'club'  => $gara['club'] ?? null,
                    'tipo'  => $this->detectTipo($nome),
                ];
            }

            // Ordina per data
            usort($gare, function ($a, $b) {
                $da = \DateTime::createFromFormat('d/m/Y', $a['data'] ?? '');
                $db = \DateTime::createFromFormat('d/m/Y', $b['data'] ?? '');
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
        $request->validate([
            'competition_id' => 'required|string|max:100',
        ]);

        $competitionId = $request->input('competition_id');

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
     * Crea le assegnazioni sul torneo locale selezionato dall'admin.
     *
     * Viene chiamato SOLO quando l'admin ha revisionato tutti i match
     * e ha cliccato "Conferma importazione".
     *
     * Gestisce gracefully i duplicati (li salta, non sovrascrive).
     */
    public function executeImport(Request $request): JsonResponse
    {
        $request->validate([
            'tournament_id'  => 'required|integer|exists:tournaments,id',
            'assegnazioni'   => 'required|array|min:1',
            'assegnazioni.*.user_id' => 'required|integer|exists:users,id',
            'assegnazioni.*.ruolo'   => ['required', 'string'],
        ]);

        $tournamentId  = $request->integer('tournament_id');
        $assegnazioni  = $request->input('assegnazioni');

        $creati       = 0;
        $saltati      = 0;
        $errori       = [];
        $dettagliSaltati = []; // diagnostica: mostra COSA era già presente

        // ── Diagnostica DB: verifica connessione e contesto ──────────────────
        $dbName      = \DB::connection()->getDatabaseName();
        $tournamentOk = Tournament::find($tournamentId);

        foreach ($assegnazioni as $idx => $item) {
            $userId = (int) $item['user_id'];
            $ruolo  = AssignmentRole::normalize($item['ruolo'])->value;

            // Controlla se l'assegnazione esiste già
            $existing = Assignment::where('tournament_id', $tournamentId)
                ->where('user_id', $userId)
                ->first(['id', 'role', 'assigned_at', 'notes']);

            if ($existing) {
                $saltati++;
                $dettagliSaltati[] = [
                    'user_id'       => $userId,
                    'assignment_id' => $existing->id,
                    'ruolo_attuale' => $existing->role,
                    'assegnato_il'  => $existing->assigned_at?->format('d/m/Y H:i'),
                    'note'          => $existing->notes,
                ];
                continue;
            }

            try {
                Assignment::create([
                    'tournament_id' => $tournamentId,
                    'user_id'       => $userId,
                    'role'          => $ruolo,
                    'assigned_by'   => auth()->id(),
                    'assigned_at'   => now(),
                    'status'        => 'assigned',
                    'is_confirmed'  => false,
                    'notes'         => 'Importato da federgolf.it',
                ]);
                $creati++;
            } catch (\Throwable $e) {
                Log::warning('FedergolfImportController::executeImport singola assegnazione', [
                    'tournament_id' => $tournamentId,
                    'user_id'       => $userId,
                    'error'         => $e->getMessage(),
                ]);
                $errori[] = "Riga {$idx}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success'          => true,
            'creati'           => $creati,
            'saltati'          => $saltati,
            'errori'           => $errori,
            'messaggio'        => $this->buildResultMessage($creati, $saltati, $errori),
            // ── diagnostica (utile per debug ambiente) ────────────────────
            'debug' => [
                'database'          => $dbName,
                'tournament_id'     => $tournamentId,
                'tournament_nome'   => $tournamentOk?->name ?? '⚠️ NON TROVATO',
                'assegnazioni_gia_presenti' => $dettagliSaltati,
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

    private function buildResultMessage(int $creati, int $saltati, array $errori): string
    {
        $msg = "{$creati} assegnazioni create";

        if ($saltati > 0) {
            $msg .= ", {$saltati} già presenti (saltate)";
        }

        if (! empty($errori)) {
            $msg .= ', ' . count($errori) . ' errori';
        }

        return $msg . '.';
    }
}
