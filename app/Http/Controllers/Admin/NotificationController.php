<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NationalNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\NotificationDocumentService;
use App\Services\NotificationPreparationService;
use App\Services\NotificationService;
use App\Services\NotificationTransactionService;
use App\Support\Untrusted;
use App\Traits\HasZoneVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gestione convocazioni collettive e lettere circoli (solo DOCX)
 */
class NotificationController extends Controller
{
    use HasZoneVisibility;

    public function __construct(
        private NotificationPreparationService $preparationService,
        private NotificationDocumentService $documentService,
        private NotificationTransactionService $transactionService
    ) {}

    /**
     * Verifica accesso zona al torneo (IDOR fix: il middleware controlla solo il ruolo).
     */
    private function checkTournamentAccess(Tournament $tournament): void
    {
        if (! $this->canAccessTournament($tournament)) {
            abort(403, 'Non autorizzato a gestire le notifiche di questo torneo');
        }
    }

    /**
     * Verifica accesso zona alla notifica tramite il torneo associato.
     */
    private function checkNotificationAccess(TournamentNotification $notification): void
    {
        if ($notification->tournament) {
            $this->checkTournamentAccess($notification->tournament);
        }
    }

    /**
     * Aggiorna in modo atomico la chiave $type del JSON `documents`.
     *
     * FIX A6: il read-modify-write senza lock permetteva a due richieste AJAX
     * concorrenti (es. genera convocazione + upload lettera) di sovrascriversi
     * a vicenda. Qui si rilegge il record con lockForUpdate dentro transazione.
     *
     * @param  string|null  $fileName  null = rimuove la chiave
     */
    private function updateNotificationDocument(TournamentNotification $notification, string $type, ?string $fileName): void
    {
        DB::transaction(function () use ($notification, $type, $fileName) {
            /** @var TournamentNotification $fresh */
            $fresh = TournamentNotification::lockForUpdate()->findOrFail($notification->id);

            $documents = is_string($fresh->documents)
                ? (json_decode($fresh->documents, true) ?? [])
                : ($fresh->documents ?? []);

            if ($fileName === null) {
                unset($documents[$type]);
            } else {
                $documents[$type] = $fileName;
            }

            $fresh->update(['documents' => $documents]);

            // Allinea l'istanza già in memoria
            $notification->setAttribute('documents', $documents);
        });
    }

    /**
     * Lista notifiche con gestione documenti
     * Per gare nazionali, raggruppa CRC e Zona in una singola riga
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // ═══════════════════════════════════════════════════════════════════
        // FIX M2 (audit 2026-06): paginazione DB-side.
        // Prima: get() di TUTTE le notifiche + groupBy + forPage in memoria —
        // degradava linearmente con lo storico. Ora si paginano i TORNEI con
        // notifiche (20 per pagina) e si caricano solo le loro notifiche.
        // NB: eventuali notifiche orfane (tournament_id inesistente) non
        // compaiono più in lista — prima comparivano come righe senza torneo.
        // ═══════════════════════════════════════════════════════════════════
        // Decisione 2026-10-08: l'elenco mostra tutti i tornei con arbitri
        // designati, anche quelli mai notificati («Da inviare»), oltre a quelli
        // che hanno gia' una notifica. Prima comparivano solo i secondi: i
        // tornei caricati da FIG senza notifica restavano invisibili.
        $tournamentsQuery = Tournament::with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->where(fn ($q) => $q->whereHas('notifications')->orWhereHas('assignments'));

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($tournamentsQuery, $user);

        // Mesi presenti nell'elenco, per il filtro (i tipi sono pochi e il
        // filtro per tipo non serviva: 2026-10-08)
        /** @var list<string> $mesi */
        $mesi = (clone $tournamentsQuery)->setEagerLoads([])->orderBy('start_date')->get(['id', 'start_date'])
            ->map(fn (Tournament $t): string => $t->start_date->format('Y-m'))
            ->unique()->values()->all();

        $mese = $request->string('mese')->toString();
        if (preg_match('/^(\d{4})-(\d{2})$/', $mese, $m) === 1) {
            $tournamentsQuery->whereYear('start_date', (int) $m[1])->whereMonth('start_date', (int) $m[2]);
        }

        // Filtro ricerca nome torneo
        if ($request->filled('cerca')) {
            $tournamentsQuery->where('name', 'like', '%'.$request->string('cerca')->toString().'%');
        }

        // Ordina per data torneo ascendente (cronologia crescente)
        $tournamentsPage = $tournamentsQuery->orderBy('start_date', 'asc')->paginate(20)->withQueryString();

        // Notifiche solo dei tornei in pagina
        $notificationsByTournament = TournamentNotification::whereIn(
            'tournament_id',
            $tournamentsPage->getCollection()->pluck('id')
        )->get()->groupBy('tournament_id');

        // RIMOSSO: updateRecipientInfo() in loop causava N+1 UPDATE queries ad ogni visualizzazione.
        // referee_list e details.total_recipients vengono ora aggiornati dall'AssignmentObserver
        // al momento della creazione/eliminazione delle assegnazioni (single source of truth).

        // Raggruppa per torneo: gare nazionali hanno CRC + Zona nella stessa riga.
        //
        // FONTE DI VERITÀ: tournament.tournamentType.is_national determina se il torneo
        // è nazionale o zonale. NON si usa notification_type per questa decisione,
        // perché i record di notifica possono avere il tipo errato (es. import batch FIG
        // che assegna crc_referees a tutti i tornei indiscriminatamente).
        $tournamentNotifications = $tournamentsPage->through(function ($tournament) use ($notificationsByTournament) {
            $notifications = $notificationsByTournament->get($tournament->id, collect());

            // Collega il torneo (già eager-loaded) alle notifiche per evitare lazy-load nella view
            $notifications->each->setRelation('tournament', $tournament);

            $first = $notifications->first();

            // Fonte di verità: is_national dal tipo torneo, non dalla notifica
            $isNational = $tournament->tournamentType->is_national ?? false;

            return (object) [
                'tournament'            => $tournament,
                'notifications'         => $notifications,
                // is_national da tournamentType — NON da notification_type
                'is_national'           => $isNational,
                // Notifica CRC (rilevante solo per tornei nazionali)
                'crc'                   => $notifications->firstWhere('notification_type', 'crc_referees'),
                // Notifica Zona (rilevante solo per tornei nazionali)
                'zone'                  => $notifications->firstWhere('notification_type', 'zone_observers'),
                // Notifica zonale (notification_type null)
                'zonal'                 => $notifications->whereNull('notification_type')->first(),
                // Notifica principale per azioni (resend, dettaglio, modifica):
                //   nazionali → CRC; zonali → record null; fallback → primo disponibile
                'primary'               => $isNational
                    ? ($notifications->firstWhere('notification_type', 'crc_referees') ?? $first)
                    : ($notifications->whereNull('notification_type')->first() ?? $first),
                // Arbitri designati oggi (anche per i tornei mai notificati)
                'referees'              => \App\Enums\AssignmentRole::sortCollection($tournament->assignments)
                    ->map(fn ($a) => $a->getRelationValue('user') instanceof \App\Models\User ? $a->user->name : null)
                    ->filter()->implode(', '),
                'tournament_start_date' => $tournament->start_date,
                'created_at'            => $notifications->max('created_at'),
                'sent_at'               => $notifications->max('sent_at'),
            ];
        });

        // Notifiche NON inviate (ultimo tentativo fallito), ben in vista in
        // cima alla pagina (decisione 2026-10-07)
        $notSent = $this->applyTournamentRelationVisibility(
            TournamentNotification::with('tournament')->where('status', 'failed'),
            $user
        )->orderByDesc('updated_at')->get();

        return view('admin.tournament-notifications.index', compact('tournamentNotifications', 'notSent', 'mesi'));
    }

    /**
     * Form per invio notifiche collettive
     */
    public function showAssignmentForm(Tournament $tournament): RedirectResponse|View
    {
        $this->checkTournamentAccess($tournament);

        // Verifica che il torneo abbia assegnazioni
        if ($tournament->assignments->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni tramite <strong>Setup e Arbitri</strong>.');
        }

        $isNational = $tournament->tournamentType->is_national ?? false;

        // Zonale (decisione 2026-10-07): senza circolo o senza la sua email il
        // form non si apre, perche' la notifica non potrebbe mai partire
        if (! $isNational && ! NotificationService::clubHasValidEmail($tournament)) {
            return redirect()->back()->with('error', $tournament->club === null
                ? 'Il torneo non ha ancora un circolo: la notifica si prepara quando il circolo è scelto.'
                : 'Il circolo '.$tournament->club->name.' non ha un\'email valida: inseriscila nella scheda del circolo prima di preparare la notifica.');
        }

        // Prepara o recupera la notifica.
        // Nazionale (P12/P13, 2026-10-03): solo la notifica di chi apre il form,
        // non salvata, e nessun documento Word.
        $notification = $isNational
            ? $this->preparationService->prepareNationalNotification($tournament, $this->authUser(), request()->string('comunicazione')->toString() ?: null)
            : $this->preparationService->prepareNotification($tournament);

        // Gli allegati NON si creano all'apertura: si creano una volta sola con
        // "Crea allegati", dopo aver scelto le clausole (decisione 2026-10-07)

        // Controlla stato documenti (sui nazionali non esistono)
        $documentStatus = $isNational
            ? ['hasConvocation' => false, 'hasClubLetter' => false]
            : $this->documentService->checkDocumentsExist($notification);
        $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

        // Carica dati per il form, passando la notifica esistente per pre-popolare i destinatari salvati
        $formData = $this->preparationService->loadFormData($tournament, $notification);

        return view('admin.notifications.prepare_notification', array_merge([
            'nationalFormType' => $isNational
                ? NotificationPreparationService::nationalFormType($this->authUser(), request()->string('comunicazione')->toString() ?: null)
                : null,
            'tournament' => $tournament,
            'notification' => $notification,
            'documentStatus' => $documentStatus,
            'hasExistingConvocation' => $hasExistingConvocation,
        ], $formData));
    }

    /**
     * Stato documenti per il modal
     */
    public function documentsStatus(TournamentNotification $notification): JsonResponse
    {
        $this->checkNotificationAccess($notification);

        try {
            $status = $this->documentService->getDocumentsStatus($notification);

            return response()->json($status);
        } catch (\Exception $e) {
            Log::error('Error checking documents status', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Errore nel caricamento dei documenti: '.$e->getMessage()], 500);
        }
    }

    /**
     * Genera/rigenera documento
     *
     * @param  string  $type
     */
    public function generateDocument(TournamentNotification $notification, $type): JsonResponse
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo documento non valido.'], 422);
        }

        // Sui nazionali non esistono documenti Word
        if ($notification->tournament->tournamentType->is_national ?? false) {
            return response()->json(['success' => false, 'message' => 'Sui tornei nazionali non ci sono allegati.'], 422);
        }

        // Si crea una volta sola: dopo si corregge scaricando e ricaricando
        // (decisione 2026-10-07), mai rigenerando sopra la versione salvata
        $documents = is_array($notification->documents) ? $notification->documents : [];
        if (! empty($documents[$type])) {
            return response()->json([
                'success' => false,
                'message' => 'Allegato già creato: per correggerlo scaricalo, modificalo e ricaricalo.',
            ], 422);
        }

        try {
            $fileName = $this->documentService->generateDocument($notification, $type);

            // Aggiorna i documenti della notifica (atomico, FIX A6)
            $this->updateNotificationDocument($notification, $type, $fileName);

            // Get updated document status for UI refresh (M5: chiamata diretta al service)
            $status = $this->documentService->getDocumentsStatus($notification);

            return response()->json([
                'success' => true,
                'message' => 'Documento generato con successo',
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore generazione documento', [
                'type' => $type,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella generazione: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un documento della notifica (AJAX dal modal)
     *
     * @param  string  $type
     */
    public function deleteDocument(TournamentNotification $notification, $type): JsonResponse
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo documento non valido.'], 422);
        }

        try {
            $this->documentService->deleteDocument($notification, $type);

            // Aggiorna i documenti della notifica (atomico, FIX A6)
            $this->updateNotificationDocument($notification, $type, null);

            // Ritorna lo stato aggiornato per aggiornare il modal (M5: chiamata diretta al service)
            $status = $this->documentService->getDocumentsStatus($notification);

            return response()->json([
                'success' => true,
                'message' => 'Documento eliminato',
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore eliminazione documento', [
                'notification_id' => $notification->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Download documento
     *
     * @param  string  $type
     */
    public function downloadDocument(TournamentNotification $notification, $type): RedirectResponse|BinaryFileResponse
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            abort(422, 'Tipo documento non valido.');
        }

        try {
            $fullPath = $this->documentService->getDocumentPath($notification, $type);

            Log::info('Downloading document', [
                'notification_id' => $notification->id,
                'type' => $type,
            ]);

            $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === 'doc' ? 'doc' : 'docx';
            $filename = ($type === 'convocation' ? 'Convocazione.' : 'Lettera_Circolo.').$extension;

            return response()->file($fullPath, [
                'Content-Type' => $extension === 'doc' ? 'application/msword' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading document', [
                'notification_id' => $notification->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * FIX D3: il redirect post-invio riflette lo stato reale — un invio
     * parziale (es. circolo senza email) non deve apparire come pieno successo.
     */
    private function redirectAfterSend(TournamentNotification $notification): RedirectResponse
    {
        $final = $notification->refresh();

        if ($final->status === 'partial') {
            $lastError = $final->metadata['last_error'] ?? 'destinatario non raggiungibile';

            return redirect()->route('admin.tournament-notifications.index')
                ->with('warning', "Notifica inviata PARZIALMENTE — {$lastError}. Verificare i destinatari.");
        }

        // FIX M5 (audit 2026-07): nessuna mail partita (es. nessun destinatario
        // valido) → errore esplicito, non "successo"
        if ($final->status === 'failed') {
            $lastError = $final->metadata['last_error'] ?? 'nessun destinatario valido';

            return redirect()->route('admin.tournament-notifications.index')
                ->with('error', "Notifica NON inviata — {$lastError}.");
        }

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', 'Notifiche inviate con successo');
    }

    /**
     * Reinvia notifica.
     *
     * Comportamento unificato per tutte le notifiche (zonali, nazionali, importate FIG):
     * il pulsante "Reinvia" reindirizza sempre al form di preparazione
     * (admin.notifications.prepare_notification) così che l'admin possa rivedere
     * destinatari, assegnazioni e contenuti prima del nuovo invio.
     */
    public function resend(TournamentNotification $notification): RedirectResponse
    {
        $this->checkNotificationAccess($notification);

        return redirect()
            ->route('admin.tournaments.show-assignment-form', $notification->tournament)
            ->with('info', 'Rivedi destinatari, assegnazioni e messaggio prima del reinvio.');
    }

    /**
     * Mostra una singola notifica
     */
    public function show(TournamentNotification $notification): View
    {
        $this->checkNotificationAccess($notification);

        $tournamentNotification = $notification->load(['tournament.club', 'tournament.zone', 'tournament.assignments.user']);

        return view('admin.tournament-notifications.show', ['tournamentNotification' => $tournamentNotification]);
    }

    /**
     * Elimina una notifica e i relativi documenti
     */
    public function destroy(TournamentNotification $notification): RedirectResponse
    {
        $this->checkNotificationAccess($notification);

        if (! $this->canDeleteNotification($notification)) {
            return redirect()->back()->with('error',
                'Questa comunicazione appartiene all\'altra parte (CRC o zona): non puoi eliminarla.'
            );
        }

        try {
            $this->transactionService->deleteWithCleanup($notification);

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifica eliminata con successo');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Errore durante l'eliminazione della notifica: ".$e->getMessage());
        }
    }

    /**
     * Elimina TUTTE le notifiche di un torneo (CRC + Zona + bozze)
     * Usato dal pulsante "Elimina" nella lista raggruppata
     */
    public function destroyTournament(Tournament $tournament): RedirectResponse
    {
        $this->checkTournamentAccess($tournament);

        try {
            // Solo le comunicazioni che chi opera puo' eliminare: la SZR non
            // tocca quella del CRC e viceversa (2026-10-07)
            $notifications = TournamentNotification::where('tournament_id', $tournament->id)->get()
                ->filter(fn (TournamentNotification $n): bool => $this->canDeleteNotification($n));

            foreach ($notifications as $notification) {
                $this->transactionService->deleteWithCleanup($notification);
            }

            return redirect()->route('admin.tournament-notifications.index', request()->only(['cerca', 'mese']))
                ->with('success', "Notifiche del torneo «{$tournament->name}» eliminate ({$notifications->count()}).");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Errore durante l'eliminazione: ".$e->getMessage());
        }
    }

    /**
     * Salva clausole via AJAX (per rigenerazione documenti)
     */
    public function saveClauses(Request $request, TournamentNotification $notification): JsonResponse
    {
        $this->checkNotificationAccess($notification);

        $validated = $request->validate([
            'clauses' => 'nullable|array',
            'clauses.*' => 'nullable|exists:notification_clauses,id',
        ]);

        try {
            $savedCount = $this->preparationService->saveClauseSelections(
                $notification,
                $validated['clauses'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => "Salvate {$savedCount} clausole",
                'saved_count' => $savedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errore nel salvataggio delle clausole: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invia notifica con allegati dal form
     */
    public function sendAssignmentWithConvocation(Request $request, Tournament $tournament): JsonResponse|RedirectResponse
    {
        $this->checkTournamentAccess($tournament);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'fixed_addresses' => 'nullable|array',
            'fixed_addresses.*' => 'exists:institutional_emails,id',
            'send_to_club' => 'boolean',
            'send_to_section' => 'boolean',
            'additional_emails' => 'nullable|array',
            'additional_emails.*' => 'nullable|email',
            'additional_names' => 'nullable|array',
            'additional_names.*' => 'nullable|string|max:255',
            'attach_convocation' => 'boolean',
            'clauses' => 'nullable|array',
            'clauses.*' => 'nullable|exists:notification_clauses,id',
            'action' => 'nullable|string|in:save,send,preview',
        ]);

        $action = $request->input('action', 'save');

        // P12 (2026-10-03): questo e' il form ZONALE. Sui tornei nazionali esistono
        // solo le due comunicazioni separate CRC (arbitri) e SZR (osservatori).
        if ($tournament->tournamentType->is_national ?? false) {
            return redirect()->route('admin.tournaments.show-assignment-form', $tournament)
                ->with('error', 'Torneo nazionale: usa la comunicazione CRC (arbitri) o SZR (osservatori).');
        }

        // Senza email del circolo la notifica zonale non esiste (2026-10-07)
        if (! NotificationService::clubHasValidEmail($tournament)) {
            return redirect()->back()->with('error', NotificationService::ERR_CLUB_EMAIL);
        }

        try {
            // Recupera la notifica zonale (tipo vuoto), mai una delle nazionali
            $notification = TournamentNotification::where('tournament_id', $tournament->id)
                ->whereNull('notification_type')
                ->orderBy('created_at', 'desc')
                ->firstOrFail();

            // Email aggiuntive libere dal form (FIX: prima il backend le ignorava)
            $additional = [];
            $additionalNames = $request->array('additional_names');

            foreach ($request->array('additional_emails') as $i => $email) {
                $indirizzo = Untrusted::string($email);

                if ($indirizzo !== '') {
                    $additional[] = [
                        'email' => $indirizzo,
                        'name' => Untrusted::stringOrNull(
                            is_string($i) || is_int($i) ? ($additionalNames[$i] ?? null) : null
                        ),
                    ];
                }
            }

            // Prepara i dati per il salvataggio
            $metadata = [
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'attach_convocation' => $request->boolean('attach_convocation', true),
                'recipients' => [
                    'referees' => $request->array('recipients'),
                    // Designati tolti a mano: il form li ripropone tolti
                    'excluded_referees' => array_values(array_diff(
                        \App\Support\Untrusted::intList($tournament->assignments()->pluck('user_id')->all()),
                        \App\Support\Untrusted::intList($request->array('recipients'))
                    )),
                    // Il circolo e' sempre il destinatario principale (2026-10-07)
                    'club' => true,
                    'institutional' => $request->array('fixed_addresses'),
                    // FIX: "Invia copia alla sezione" — prima il backend lo ignorava
                    'zone' => $request->boolean('send_to_section', false),
                    'additional' => $additional,
                ],
            ];

            // Salva come bozza con tutti i dati
            $this->transactionService->saveAsDraft(
                $notification,
                $metadata,
                $request->array('clauses')
            );

            // ═══════════════════════════════════════════════════════════════════════
            // GESTIONE AZIONE: save, send, o preview
            // ═══════════════════════════════════════════════════════════════════════

            // PREVIEW: restituisce JSON con anteprima email
            if ($action === 'preview') {
                $preview = $this->preparationService->prepareEmailPreview($notification, $tournament);

                return response()->json([
                    'success' => true,
                    'preview' => $preview,
                ]);
            }

            // SEND: invia subito la notifica
            if ($action === 'send') {
                try {
                    $this->transactionService->sendWithTransaction($notification);

                    // FIX D3: distingue invio pieno da invio parziale
                    return $this->redirectAfterSend($notification);
                } catch (\Exception $sendError) {
                    return redirect()->back()
                        ->with('error', 'Errore nell\'invio: '.$sendError->getMessage())
                        ->with('warning', 'La notifica è stata salvata come bozza.');
                }
            }

            // SAVE (default): salva solo come bozza
            return redirect()->route('admin.tournaments.index')
                ->with('success', 'Notifica salvata come bozza. Puoi inviarla dalla lista tornei.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore nella preparazione: '.$e->getMessage());
        }
    }

    /**
     * Find notification by tournament
     */
    public function findByTournament(Tournament $tournament): JsonResponse
    {
        $this->checkTournamentAccess($tournament);

        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->latest()
            ->first();

        return response()->json([
            'notification_id' => $notification?->id,
        ]);
    }

    /**
     * Carica un documento manualmente
     *
     * @param  string  $type
     */
    public function uploadDocument(Request $request, TournamentNotification $notification, $type): JsonResponse
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo documento non valido.'], 422);
        }

        if ($notification->tournament->tournamentType->is_national ?? false) {
            return response()->json(['success' => false, 'message' => 'Sui tornei nazionali non ci sono allegati.'], 422);
        }

        try {
            // Valida il file
            $request->validate([
                'document' => 'required|file|mimes:doc,docx|max:10240', // max 10MB
            ]);

            $file = $request->file('document');
            $filename = $this->documentService->uploadDocument($notification, $type, $file);

            // Aggiorna i documenti della notifica (atomico, FIX A6)
            $this->updateNotificationDocument($notification, $type, $filename);

            // Ritorna lo stato aggiornato per aggiornare il modal (M5: chiamata diretta al service)
            $status = $this->documentService->getDocumentsStatus($notification);

            return response()->json([
                'success' => true,
                'message' => 'Documento caricato con successo',
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Errore caricamento documento', [
                'notification_id' => $notification->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invia notifica per gare nazionali (senza allegati)
     * Gestisce sia CRC (arbitri designati) che Admin Zona (osservatori)
     */
    public function sendNationalNotification(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->checkTournamentAccess($tournament);

        // FIX M1 (audit 2026-07): prima i cc_* non erano validati — nessun
        // check exists/array: ID arbitrari finivano in User::whereIn() nudo
        // (CC a qualunque utente del DB) e un input scalare causava TypeError
        // 500 in addRefereesByIds(array). Il filtro is_active è nel builder.
        $validated = $request->validate([
            'notification_type' => 'required|string|in:crc_referees,zone_observers',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'cc_zone_admins' => 'nullable|array',
            'cc_zone_admins.*' => 'integer|exists:users,id',
            'cc_referees' => 'nullable|array',
            'cc_referees.*' => 'integer|exists:users,id',
            'cc_national_admins' => 'nullable|array',
            'cc_national_admins.*' => 'integer|exists:users,id',
            'cc_observers' => 'nullable|array',
            'cc_observers.*' => 'integer|exists:users,id',
        ]);

        $notificationType = $validated['notification_type'];
        $isCrcNotification = $notificationType === 'crc_referees';

        // P9/P12 (2026-10-03): la SZR comunica solo gli osservatori
        if ($isCrcNotification && $this->authUser()->isZoneAdmin()) {
            return redirect()->back()->with('error',
                'La comunicazione degli arbitri dei tornei nazionali spetta al CRC: la zona comunica solo gli osservatori.'
            );
        }

        // La comunicazione degli osservatori la manda la zona in cui si gioca
        // (il super admin solo se serve); il CRC no, anche se puo' designarli
        if (! $isCrcNotification && $this->authUser()->user_type === \App\Enums\UserType::NationalAdmin) {
            return redirect()->back()->with('error',
                'La comunicazione degli osservatori la invia la zona in cui si gioca.'
            );
        }

        // GUARD: solo tornei nazionali possono avere notifiche CRC/SZR
        $isNational = $tournament->tournamentType->is_national ?? false;
        if (! $isNational) {
            return redirect()->back()->with('error',
                'Questo torneo è zonale (tipo: ' . ($tournament->tournamentType->name ?? '?') . '). ' .
                'Le notifiche CRC/SZR sono riservate ai tornei nazionali.'
            );
        }

        try {
            // Prepara destinatari tramite NotificationRecipientBuilder
            $builder = new \App\Services\NotificationRecipientBuilder();

            // Il Comitato Campionati e' sempre il destinatario principale e non
            // si puo' togliere (decisione 2026-10-07)
            $builder->addCampionati();

            if ($isCrcNotification) {
                if ($request->has('send_to_zone')) {
                    $builder->addZone($tournament); // null-safe internamente
                }
                $builder->addZoneAdminsByIds($validated['cc_zone_admins'] ?? [])
                        ->addRefereesByIds($validated['cc_referees'] ?? []);
            } else {
                if ($request->has('send_to_crc')) {
                    $builder->addCrc();
                }
                // cc_national_admins sono passati come IDs: addZoneAdminsByIds ha la stessa implementazione
                $builder->addZoneAdminsByIds($validated['cc_national_admins'] ?? [])
                        ->addObserversByIds($validated['cc_observers'] ?? []);
            }

            $recipients   = $builder->build();
            $toRecipients = $recipients['to'];
            $ccArray      = $recipients['cc'];

            // Il Comitato Campionati e' il destinatario principale: senza il suo
            // indirizzo non si invia (mai promuovere un indirizzo in copia)
            if ($toRecipients === []) {
                return redirect()->back()->with('error',
                    'Manca un indirizzo valido del Comitato Campionati (GOLF_EMAIL_CAMPIONATI nel .env): la comunicazione non parte.'
                );
            }

            // Invia email
            // Mittente: CRC per gli arbitri, la zona per gli osservatori
            $sender = $isCrcNotification ? [null, null] : $this->zoneSender($tournament);

            $successCount = 0;
            $errorCount = 0;
            $lastError = null;
            $reached = 0; // indirizzi accettati dal server di posta

            // Invia email con CC
            foreach ($toRecipients as $recipient) {
                try {
                    $mailer = Mail::to($recipient['email']);
                    if (! empty($ccArray)) {
                        $mailer->cc($ccArray);
                    }
                    $mailer->send(new NationalNotificationMail($validated['subject'], $validated['message'], ...$sender));
                    $successCount++;
                    $reached += 1 + count($ccArray);
                } catch (\Exception $e) {
                    Log::error('Errore invio email', [
                        'recipient' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                    $lastError = 'il server di posta ha rifiutato l\'invio: '.$e->getMessage();
                }
            }

            // Designati di questa comunicazione (CRC: arbitri e Direttore; SZR:
            // osservatori) e totale destinatari calcolato dal builder
            $refereeList     = TournamentNotification::refereeListFor(
                $tournament->assignments()->with('user')->get(),
                $notificationType
            );
            $totalRecipients = $recipients['total'];

            // Transazione: elimina bozza zonale + crea/aggiorna record nazionale
            // Esito reale (decisione 2026-10-07): nessuna mail partita = "non
            // inviata", senza data d'invio; la data resta quella dell'ultimo
            // invio riuscito, se c'era
            $status = $errorCount === 0 ? 'sent' : ($successCount > 0 ? 'partial' : 'failed');

            DB::transaction(function () use ($tournament, $notificationType, $errorCount, $successCount, $refereeList, $totalRecipients, $validated, $status, $lastError, $reached) {
                // Elimina la notifica "bozza" (notification_type = null) per evitare duplicati
                // Questo record viene creato automaticamente da prepareNotification() ma non serve per gare nazionali
                TournamentNotification::where('tournament_id', $tournament->id)
                    ->whereNull('notification_type')
                    ->whereNull('sent_at')
                    ->delete();

                // Salva il record della notifica nazionale inviata
                // NOTA: il campo 'metadata' deve contenere 'is_national' => true e 'type' => $notificationType
                // per permettere a resend() di riconoscere questa come notifica nazionale e usare il percorso corretto.
                $values = [
                    'status' => $status,
                    'sent_by' => auth()->id(),
                    'referee_list' => $refereeList,
                    'details' => [
                        'sent' => $reached,
                        'errors' => $errorCount,
                        'total_recipients' => $totalRecipients,
                    ],
                    'metadata' => [
                        'is_national' => true,
                        'type' => $notificationType,
                        'subject' => $validated['subject'],
                        'message' => $validated['message'],
                        'success_count' => $reached,
                        'error_count' => $errorCount,
                        'last_error' => $lastError,
                        'last_attempt_at' => now()->toDateTimeString(),
                    ],
                ];
                if ($successCount > 0) {
                    $values['sent_at'] = now();
                }

                // Tentativo fallito dopo un invio riuscito: restano i dati di
                // quell'invio, si registra solo l'esito del tentativo
                $existing = TournamentNotification::where('tournament_id', $tournament->id)
                    ->where('notification_type', $notificationType)
                    ->first();
                if ($status === 'failed' && $existing !== null && $existing->sent_at !== null) {
                    $previous = is_array($existing->metadata) ? $existing->metadata : [];
                    $existing->update([
                        'status' => 'failed',
                        'metadata' => array_merge($previous, [
                            'last_error' => $lastError,
                            'last_attempt_at' => now()->toDateTimeString(),
                        ]),
                    ]);

                    return;
                }

                TournamentNotification::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'notification_type' => $notificationType,
                    ],
                    $values
                );
            });

            $typeLabel = $isCrcNotification ? 'arbitri designati' : 'osservatori';
            $totalSent = $reached;

            if ($status === 'failed') {
                return redirect()->route('admin.tournament-notifications.index')
                    ->with('error', "Notifica {$typeLabel} NON inviata — {$lastError}. Nessuna mail è partita: riprova dal form.");
            }

            if ($status === 'partial') {
                return redirect()->route('admin.tournament-notifications.index')
                    ->with('warning', "Notifica {$typeLabel} inviata solo in parte ({$errorCount} invii non riusciti) — {$lastError}.");
            }

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', "Notifica {$typeLabel} inviata con successo a {$totalSent} destinatari.");
        } catch (\Exception $e) {
            Log::error('Errore invio notifica nazionale', [
                'tournament_id' => $tournament->id,
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Errore nell\'invio della notifica: '.$e->getMessage());
        }
    }

    /**
     * Sui nazionali la comunicazione degli arbitri e' del CRC e quella degli
     * osservatori della zona: ciascuno elimina solo la propria (il super
     * admin tutto).
     */
    private function canDeleteNotification(TournamentNotification $notification): bool
    {
        $type = $this->authUser()->user_type;

        return match ($notification->notification_type) {
            'crc_referees' => $type !== \App\Enums\UserType::ZoneAdmin,
            'zone_observers' => $type !== \App\Enums\UserType::NationalAdmin,
            default => true,
        };
    }

    /**
     * Nome e indirizzo di risposta della zona in cui si gioca il torneo (per
     * la comunicazione degli osservatori), con la stessa regola delle mail
     * zonali: email della zona se valida, altrimenti szrN@federgolf.it.
     *
     * @return array{0: string, 1: string|null}
     */
    private function zoneSender(Tournament $tournament): array
    {
        $zone = $tournament->club->zone ?? $tournament->zone;
        $zoneId = $tournament->club->zone_id ?? $tournament->zone_id;
        $code = \App\Helpers\ZoneHelper::getFolderCode($zoneId);
        $name = $zone && $zone->name ? "{$code} - {$zone->name}" : "{$code} - Sezione Zonale Regole";

        $email = $zone?->email;
        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = $zoneId ? \App\Helpers\ZoneHelper::getEmailPattern($zoneId) : null;
        }

        return [$name, $email];
    }
}
