<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentRole;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Mail\NationalNotificationMail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\NotificationDocumentService;
use App\Services\NotificationPreparationService;
use App\Services\NotificationTransactionService;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    public function index(Request $request)
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
        $tournamentsQuery = Tournament::with(['club', 'zone', 'tournamentType', 'assignments.user'])
            ->whereHas('notifications');

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentVisibility($tournamentsQuery, $user);

        // Filtro anno (sulla data del torneo)
        if ($request->filled('anno')) {
            $tournamentsQuery->whereYear('start_date', (int) $request->anno);
        }

        // Filtro ricerca nome torneo
        if ($request->filled('cerca')) {
            $tournamentsQuery->where('name', 'like', '%'.$request->cerca.'%');
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
            $isNational = $tournament->tournamentType?->is_national ?? false;

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
                'tournament_start_date' => $tournament->start_date,
                'created_at'            => $notifications->max('created_at'),
                'sent_at'               => $notifications->max('sent_at'),
            ];
        });

        // Anni disponibili per il filtro (ricavati dai tornei con notifiche)
        $anniDisponibili = TournamentNotification::join('tournaments', 'tournament_notifications.tournament_id', '=', 'tournaments.id')
            ->selectRaw('YEAR(tournaments.start_date) as anno')
            ->whereNotNull('tournaments.start_date')
            ->groupBy('anno')
            ->orderByDesc('anno')
            ->pluck('anno');

        return view('admin.tournament-notifications.index', compact('tournamentNotifications', 'anniDisponibili'));
    }

    /**
     * Form per invio notifiche collettive
     */
    public function showAssignmentForm(Tournament $tournament)
    {
        $this->checkTournamentAccess($tournament);

        // Verifica che il torneo abbia assegnazioni
        if ($tournament->assignments->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni tramite <strong>Setup e Arbitri</strong>.');
        }

        // Prepara o recupera la notifica
        $notification = $this->preparationService->prepareNotification($tournament);

        // Genera documenti se non esistono
        if (empty($notification->documents)) {
            try {
                $documents = $this->documentService->generateInitialDocuments($tournament, $notification);
                $notification->update(['documents' => $documents]);
            } catch (\Exception $e) {
                Log::error('Error generating documents in assignment form', [
                    'tournament_id' => $tournament->id,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
                session()->flash('warning', 'Si è verificato un errore nella generazione dei documenti. È possibile rigenerarli manualmente.');
            }
        }

        // Controlla stato documenti
        $documentStatus = $this->documentService->checkDocumentsExist($notification);
        $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

        // Carica dati per il form, passando la notifica esistente per pre-popolare i destinatari salvati
        $formData = $this->preparationService->loadFormData($tournament, $notification);

        return view('admin.notifications.prepare_notification', array_merge([
            'tournament' => $tournament,
            'notification' => $notification,
            'documentStatus' => $documentStatus,
            'hasExistingConvocation' => $hasExistingConvocation,
        ], $formData));
    }

    /**
     * Stato documenti per il modal
     */
    public function documentsStatus(TournamentNotification $notification)
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
     */
    public function generateDocument(TournamentNotification $notification, $type)
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo documento non valido.'], 422);
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
     */
    public function deleteDocument(TournamentNotification $notification, $type)
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
     */
    public function downloadDocument(TournamentNotification $notification, $type)
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

            $filename = $type === 'convocation' ? 'Convocazione.docx' : 'Lettera_Circolo.docx';

            return response()->file($fullPath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
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
     * Invia notifica (con metadati salvati)
     */
    public function send(TournamentNotification $notification)
    {
        $this->checkNotificationAccess($notification);

        // FIX D2: serve l'intento esplicito del form (metadata.recipients).
        // I record con metadata "estraneo" (es. import FIG: {source, command})
        // prima passavano il vecchio check empty(metadata) e "inviavano" a
        // NESSUNO flashando successo. Ora si reindirizza sempre al form.
        $metadata = $notification->metadata ?? [];
        if (empty($metadata['recipients']) || ! is_array($metadata['recipients'])) {
            return redirect()->route('admin.tournaments.show-assignment-form', $notification->tournament)
                ->with('info', 'Configura i destinatari e il messaggio per l\'invio');
        }

        try {
            $this->transactionService->sendWithTransaction($notification);

            return $this->redirectAfterSend($notification);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Errore nell\'invio delle notifiche: '.$e->getMessage());
        }
    }

    /**
     * FIX D3: il redirect post-invio riflette lo stato reale — un invio
     * parziale (es. circolo senza email) non deve apparire come pieno successo.
     */
    private function redirectAfterSend(TournamentNotification $notification)
    {
        $final = $notification->fresh();

        if ($final->status === 'partial') {
            $lastError = $final->metadata['last_error'] ?? 'destinatario non raggiungibile';

            return redirect()->route('admin.tournament-notifications.index')
                ->with('warning', "Notifica inviata PARZIALMENTE — {$lastError}. Verificare i destinatari.");
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
    public function resend(TournamentNotification $notification)
    {
        $this->checkNotificationAccess($notification);

        return redirect()
            ->route('admin.tournaments.show-assignment-form', $notification->tournament)
            ->with('info', 'Rivedi destinatari, assegnazioni e messaggio prima del reinvio.');
    }

    /**
     * Mostra una singola notifica
     */
    public function show(TournamentNotification $notification)
    {
        $this->checkNotificationAccess($notification);

        $tournamentNotification = $notification->load(['tournament.club', 'tournament.zone', 'tournament.assignments.user']);

        return view('admin.tournament-notifications.show', ['tournamentNotification' => $tournamentNotification]);
    }

    /**
     * Modifica una singola notifica
     */
    public function edit(TournamentNotification $notification)
    {
        $this->checkNotificationAccess($notification);

        $tournamentNotification = $notification;

        return view('admin.tournament-notifications.edit', compact('tournamentNotification'));
    }

    /**
     * Elimina una notifica e i relativi documenti
     */
    public function destroy(TournamentNotification $notification)
    {
        $this->checkNotificationAccess($notification);

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
    public function destroyTournament(Tournament $tournament)
    {
        $this->checkTournamentAccess($tournament);

        try {
            $notifications = TournamentNotification::where('tournament_id', $tournament->id)->get();

            foreach ($notifications as $notification) {
                $this->transactionService->deleteWithCleanup($notification);
            }

            return redirect()->route('admin.tournament-notifications.index', request()->only(['anno', 'cerca']))
                ->with('success', "Notifiche del torneo «{$tournament->name}» eliminate ({$notifications->count()}).");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Errore durante l'eliminazione: ".$e->getMessage());
        }
    }

    /**
     * Salva clausole via AJAX (per rigenerazione documenti)
     */
    public function saveClauses(Request $request, TournamentNotification $notification)
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
    public function sendAssignmentWithConvocation(Request $request, Tournament $tournament)
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

        try {
            // Recupera la notifica
            $notification = TournamentNotification::where('tournament_id', $tournament->id)
                ->orderBy('created_at', 'desc')
                ->firstOrFail();

            // Email aggiuntive libere dal form (FIX: prima il backend le ignorava)
            $additional = [];
            $additionalEmails = $request->input('additional_emails', []);
            $additionalNames = $request->input('additional_names', []);
            foreach ($additionalEmails as $i => $email) {
                if (! empty($email)) {
                    $additional[] = [
                        'email' => $email,
                        'name' => $additionalNames[$i] ?? null,
                    ];
                }
            }

            // Prepara i dati per il salvataggio
            $metadata = [
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'attach_convocation' => $request->boolean('attach_convocation', true),
                'recipients' => [
                    'referees' => $request->input('recipients', []),
                    'club' => $request->boolean('send_to_club', true),
                    'institutional' => $request->input('fixed_addresses', []),
                    // FIX: "Invia copia alla sezione" — prima il backend lo ignorava
                    'zone' => $request->boolean('send_to_section', false),
                    'additional' => $additional,
                ],
            ];

            // Salva come bozza con tutti i dati
            $this->transactionService->saveAsDraft(
                $notification,
                $metadata,
                $request->input('clauses', [])
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
    public function findByTournament(Tournament $tournament)
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
     */
    public function uploadDocument(Request $request, TournamentNotification $notification, $type)
    {
        $this->checkNotificationAccess($notification);

        // Validazione whitelist per evitare path traversal e input arbitrari
        if (! in_array($type, ['convocation', 'club_letter'], true)) {
            return response()->json(['success' => false, 'message' => 'Tipo documento non valido.'], 422);
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
    public function sendNationalNotification(Request $request, Tournament $tournament)
    {
        $this->checkTournamentAccess($tournament);

        $validated = $request->validate([
            'notification_type' => 'required|string|in:crc_referees,zone_observers',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $notificationType = $validated['notification_type'];
        $isCrcNotification = $notificationType === 'crc_referees';

        // GUARD: solo tornei nazionali possono avere notifiche CRC/SZR
        $isNational = $tournament->tournamentType?->is_national ?? false;
        if (! $isNational) {
            return redirect()->back()->with('error',
                'Questo torneo è zonale (tipo: ' . ($tournament->tournamentType?->name ?? '?') . '). ' .
                'Le notifiche CRC/SZR sono riservate ai tornei nazionali.'
            );
        }

        try {
            // Prepara destinatari tramite NotificationRecipientBuilder
            $builder = new \App\Services\NotificationRecipientBuilder();

            if ($request->has('send_to_campionati')) {
                $builder->addCampionati();
            }

            if ($isCrcNotification) {
                if ($request->has('send_to_zone')) {
                    $builder->addZone($tournament); // null-safe internamente
                }
                $builder->addZoneAdminsByIds($request->input('cc_zone_admins', []))
                        ->addRefereesByIds($request->input('cc_referees', []));
            } else {
                if ($request->has('send_to_crc')) {
                    $builder->addCrc();
                }
                // cc_national_admins sono passati come IDs: addZoneAdminsByIds ha la stessa implementazione
                $builder->addZoneAdminsByIds($request->input('cc_national_admins', []))
                        ->addObserversByIds($request->input('cc_observers', []));
            }

            $recipients   = $builder->build();
            $toRecipients = $recipients['to'];
            $ccArray      = $recipients['cc'];

            // Verifica che ci siano destinatari
            if ($recipients['isEmpty']) {
                return redirect()->back()->with('error', 'Nessun destinatario selezionato.');
            }

            // Invia email
            $successCount = 0;
            $errorCount = 0;

            // Invia email con CC
            foreach ($toRecipients as $recipient) {
                try {
                    $mailer = Mail::to($recipient['email']);
                    if (! empty($ccArray)) {
                        $mailer->cc($ccArray);
                    }
                    $mailer->send(new NationalNotificationMail($validated['subject'], $validated['message']));
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Errore invio email', [
                        'recipient' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            // Se non ci sono TO ma solo CC, usa il primo CC come TO
            // Formato $ccArray: array<{email, name}>
            if (empty($toRecipients) && ! empty($ccArray)) {
                $first       = $ccArray[0];
                $remainingCc = array_slice($ccArray, 1);
                try {
                    $mailer = Mail::to($first['email']);
                    if (! empty($remainingCc)) {
                        $mailer->cc($remainingCc);
                    }
                    $mailer->send(new NationalNotificationMail($validated['subject'], $validated['message']));
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Errore invio email (solo CC)', ['error' => $e->getMessage()]);
                    $errorCount++;
                }
            }

            // Lista nomi e totale destinatari calcolati dal builder
            $refereeList     = implode(', ', $recipients['allNames']);
            $totalRecipients = $recipients['total'];

            // Transazione: elimina bozza zonale + crea/aggiorna record nazionale
            DB::transaction(function () use ($tournament, $notificationType, $errorCount, $successCount, $refereeList, $totalRecipients, $validated) {
                // Elimina la notifica "bozza" (notification_type = null) per evitare duplicati
                // Questo record viene creato automaticamente da prepareNotification() ma non serve per gare nazionali
                TournamentNotification::where('tournament_id', $tournament->id)
                    ->whereNull('notification_type')
                    ->whereNull('sent_at')
                    ->delete();

                // Salva il record della notifica nazionale inviata
                // NOTA: il campo 'metadata' deve contenere 'is_national' => true e 'type' => $notificationType
                // per permettere a resend() di riconoscere questa come notifica nazionale e usare il percorso corretto.
                TournamentNotification::updateOrCreate(
                    [
                        'tournament_id' => $tournament->id,
                        'notification_type' => $notificationType,
                    ],
                    [
                        'status' => $errorCount === 0 ? 'sent' : 'partial',
                        'sent_at' => now(),
                        'sent_by' => auth()->id(),
                        'referee_list' => $refereeList,
                        'details' => [
                            'sent' => $successCount,
                            'errors' => $errorCount,
                            'total_recipients' => $totalRecipients,
                        ],
                        'metadata' => [
                            'is_national' => true,
                            'type' => $notificationType,
                            'subject' => $validated['subject'],
                            'message' => $validated['message'],
                            'success_count' => $successCount,
                            'error_count' => $errorCount,
                        ],
                    ]
                );
            });

            $typeLabel = $isCrcNotification ? 'arbitri designati' : 'osservatori';
            $totalSent = $successCount + count($ccArray);

            if ($errorCount === 0) {
                return redirect()->route('admin.tournament-notifications.index')
                    ->with('success', "Notifica {$typeLabel} inviata con successo a {$totalSent} destinatari.");
            } else {
                return redirect()->route('admin.tournament-notifications.index')
                    ->with('warning', "Notifica {$typeLabel} inviata con {$errorCount} errori su {$totalSent} destinatari.");
            }
        } catch (\Exception $e) {
            Log::error('Errore invio notifica nazionale', [
                'tournament_id' => $tournament->id,
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Errore nell\'invio della notifica: '.$e->getMessage());
        }
    }
}
