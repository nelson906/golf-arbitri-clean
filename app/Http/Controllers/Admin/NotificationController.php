<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\NotificationDocumentService;
use App\Services\NotificationPreparationService;
use App\Services\NotificationService;
use App\Services\NotificationTransactionService;
use App\Traits\HasZoneVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Lista notifiche con gestione documenti
     * Per gare nazionali, raggruppa CRC e Zona in una singola riga
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = TournamentNotification::with([
            'tournament.club',
            'tournament.zone',
            'tournament.assignments.user',
        ]);

        // Filtro visibilità per zona/ruolo (centralizzato nel trait)
        $this->applyTournamentRelationVisibility($query, $user, 'tournament');

        $query->orderBy('sent_at', 'desc');
        $allNotifications = $query->get();

        // Aggiorna info destinatari per ogni notifica
        foreach ($allNotifications as $notification) {
            $this->preparationService->updateRecipientInfo($notification);
        }

        // Raggruppa per tournament_id: gare nazionali hanno CRC + Zona nella stessa riga
        $grouped = $allNotifications->groupBy('tournament_id')->map(function ($notifications) {
            $first = $notifications->first();

            return (object) [
                'tournament' => $first->tournament,
                'notifications' => $notifications,
                // Notifica principale (CRC per nazionali, o l'unica per zonali)
                'primary' => $notifications->firstWhere('notification_type', 'crc_referees') ?? $first,
                // Notifica CRC (se esiste)
                'crc' => $notifications->firstWhere('notification_type', 'crc_referees'),
                // Notifica Zona (se esiste)
                'zone' => $notifications->firstWhere('notification_type', 'zone_observers'),
                // Per tornei zonali (notification_type null)
                'zonal' => $notifications->whereNull('notification_type')->first(),
                // Data più recente tra tutte le notifiche
                'created_at' => $notifications->max('created_at'),
                'sent_at' => $notifications->max('sent_at'),
            ];
        })->sortByDesc('sent_at');

        // Paginazione manuale
        $page = $request->get('page', 1);
        $perPage = 20;
        $total = $grouped->count();
        $items = $grouped->forPage($page, $perPage)->values();

        $tournamentNotifications = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.tournament-notifications.index', compact('tournamentNotifications'));
    }

    /**
     * Form per invio notifiche collettive
     */
    public function showAssignmentForm(Tournament $tournament)
    {
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

        // Carica dati per il form
        $formData = $this->preparationService->loadFormData($tournament);

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
        try {
            $fileName = $this->documentService->generateDocument($notification, $type);

            // Aggiorna i documenti della notifica
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : ($notification->documents ?? []);
            $documents[$type] = $fileName;
            $notification->update(['documents' => $documents]);

            // Get updated document status for UI refresh
            $status = $this->documentsStatus($notification)->getData();

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
        try {
            $this->documentService->deleteDocument($notification, $type);

            // Aggiorna i documenti della notifica
            $documents = is_string($notification->documents)
                ? json_decode($notification->documents, true)
                : ($notification->documents ?? []);
            unset($documents[$type]);
            $notification->update(['documents' => $documents]);

            // Ritorna lo stato aggiornato per aggiornare il modal
            $status = $this->documentsStatus($notification)->getData();

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
        // Se non ci sono metadati salvati, reindirizza al form
        if (empty($notification->metadata)) {
            return redirect()->route('admin.tournaments.show-assignment-form', $notification->tournament)
                ->with('info', 'Configura i destinatari e il messaggio per l\'invio');
        }

        try {
            $this->transactionService->sendWithTransaction($notification);

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche inviate con successo');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Errore nell\'invio delle notifiche: '.$e->getMessage());
        }
    }

    /**
     * Reinvia notifica
     */
    public function resend(TournamentNotification $notification)
    {
        try {
            // Verifica se è una notifica nazionale (salvata nei metadata)
            $metadata = is_string($notification->metadata)
                ? json_decode($notification->metadata, true)
                : ($notification->metadata ?? []);

            $isNational = $metadata['is_national'] ?? false;

            if ($isNational) {
                // Reinvio per gare nazionali: usa i destinatari salvati nei metadata
                return $this->resendNationalNotification($notification, $metadata);
            }

            // Reinvio standard per gare zonali
            $this->transactionService->sendWithTransaction($notification, true);

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche reinviate con successo');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Errore nel reinvio delle notifiche: '.$e->getMessage());
        }
    }

    /**
     * Reinvia notifica per gare nazionali
     */
    private function resendNationalNotification(TournamentNotification $notification, array $metadata)
    {
        $tournament = $notification->tournament;
        $notificationType = $metadata['type'] ?? 'crc_referees';
        $isCrcNotification = $notificationType === 'crc_referees';

        // Prepara destinatari
        $toRecipients = [];
        $ccRecipients = [];

        // TO: Comitato Campionati (sempre per gare nazionali)
        $toRecipients[] = [
            'email' => config('golf.emails.ufficio_campionati', 'campionati@federgolf.it'),
            'name' => 'Comitato Campionati',
        ];

        if ($isCrcNotification) {
            // CRC: CC a zona del torneo
            if ($tournament->club->zone?->email) {
                $ccRecipients[] = [
                    'email' => $tournament->club->zone->email,
                    'name' => $tournament->club->zone->name,
                ];
            }

            // CC Admin zonali della zona del torneo
            $zoneAdmins = \App\Models\User::where('user_type', 'admin')
                ->where('zone_id', $tournament->club->zone_id)
                ->where('is_active', true)
                ->get();
            foreach ($zoneAdmins as $admin) {
                $ccRecipients[] = ['email' => $admin->email, 'name' => $admin->name];
            }

            // CC Arbitri designati (tutti gli assegnati attuali)
            $referees = $tournament->assignments()->with('user')->get();
            foreach ($referees as $assignment) {
                if ($assignment->user) {
                    $ccRecipients[] = ['email' => $assignment->user->email, 'name' => $assignment->user->name];
                }
            }
        } else {
            // ZONA: CC a CRC
            $ccRecipients[] = [
                'email' => config('golf.emails.crc', 'crc@federgolf.it'),
                'name' => 'CRC',
            ];

            // CC Admin nazionali
            $nationalAdmins = \App\Models\User::where('user_type', 'national_admin')
                ->where('is_active', true)
                ->get();
            foreach ($nationalAdmins as $admin) {
                $ccRecipients[] = ['email' => $admin->email, 'name' => $admin->name];
            }

            // CC Osservatori (solo ruolo Osservatore)
            $observers = $tournament->assignments()
                ->where('role', 'Osservatore')
                ->with('user')
                ->get();
            foreach ($observers as $assignment) {
                if ($assignment->user) {
                    $ccRecipients[] = ['email' => $assignment->user->email, 'name' => $assignment->user->name];
                }
            }
        }

        // Prepara array CC
        $ccArray = [];
        foreach ($ccRecipients as $cc) {
            $ccArray[$cc['email']] = $cc['name'];
        }

        $subject = $metadata['subject'] ?? 'Designazione Arbitri - '.$tournament->name;
        $message = $metadata['message'] ?? '';

        $successCount = 0;
        $errorCount = 0;

        // Invia email
        foreach ($toRecipients as $recipient) {
            try {
                \Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($recipient, $subject, $ccArray) {
                    $mail->to($recipient['email'], $recipient['name'])
                        ->subject($subject);

                    if (! empty($ccArray)) {
                        $mail->cc($ccArray);
                    }
                });
                $successCount++;
            } catch (\Exception $e) {
                Log::error('Errore reinvio email nazionale', [
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
            }
        }

        // Prepara lista nomi destinatari per visualizzazione
        $allRecipientNames = [];
        foreach ($toRecipients as $r) {
            $allRecipientNames[] = $r['name'];
        }
        foreach ($ccRecipients as $r) {
            $allRecipientNames[] = $r['name'];
        }
        $refereeList = implode(', ', $allRecipientNames);
        $totalRecipients = count($toRecipients) + count($ccRecipients);

        // Aggiorna notifica
        $notification->update([
            'status' => $errorCount === 0 ? 'sent' : 'partial',
            'sent_at' => now(),
            'sent_by' => auth()->id(),
            'referee_list' => $refereeList,
            'total_recipients' => $totalRecipients,
            'metadata' => array_merge($metadata, [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'resent_at' => now()->toIso8601String(),
                'resent_by' => auth()->user()->name,
            ]),
        ]);

        $typeLabel = $isCrcNotification ? 'arbitri designati' : 'osservatori';
        $totalSent = $successCount + count($ccArray);

        if ($errorCount === 0) {
            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', "Notifica {$typeLabel} reinviata con successo a {$totalSent} destinatari.");
        } else {
            return redirect()->route('admin.tournament-notifications.index')
                ->with('warning', "Notifica {$typeLabel} reinviata con {$errorCount} errori su {$totalSent} destinatari.");
        }
    }

    /**
     * Mostra una singola notifica
     */
    public function show(TournamentNotification $notification)
    {
        $tournamentNotification = $notification->load(['tournament.club', 'tournament.zone', 'tournament.assignments.user']);

        return view('admin.tournament-notifications.show', ['tournamentNotification' => $tournamentNotification]);
    }

    /**
     * Modifica una singola notifica
     */
    public function edit(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;

        return view('admin.tournament-notifications.edit', compact('tournamentNotification'));
    }

    /**
     * Elimina una notifica e i relativi documenti
     */
    public function destroy(TournamentNotification $notification)
    {
        try {
            $this->transactionService->deleteWithCleanup($notification);

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifica eliminata con successo');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Errore durante l'eliminazione della notifica: ".$e->getMessage());
        }
    }

    /**
     * Salva clausole via AJAX (per rigenerazione documenti)
     */
    public function saveClauses(Request $request, TournamentNotification $notification)
    {
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
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'fixed_addresses' => 'nullable|array',
            'fixed_addresses.*' => 'exists:institutional_emails,id',
            'send_to_club' => 'boolean',
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

            // Prepara i dati per il salvataggio
            $metadata = [
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'attach_convocation' => $request->boolean('attach_convocation', true),
                'recipients' => [
                    'referees' => $request->input('recipients', []),
                    'club' => $request->boolean('send_to_club', true),
                    'institutional' => $request->input('fixed_addresses', []),
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

                    return redirect()->route('admin.tournament-notifications.index')
                        ->with('success', 'Notifica inviata con successo a tutti i destinatari!');
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
        try {
            // Valida il file
            $request->validate([
                'document' => 'required|file|mimes:doc,docx|max:10240', // max 10MB
            ]);

            $file = $request->file('document');
            $filename = $this->documentService->uploadDocument($notification, $type, $file);

            // Aggiorna i documenti della notifica
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : ($notification->documents ?? []);
            $documents[$type] = $filename;
            $notification->update(['documents' => $documents]);

            // Ritorna lo stato aggiornato per aggiornare il modal
            $status = $this->documentsStatus($notification)->getData();

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
        $validated = $request->validate([
            'notification_type' => 'required|string|in:crc_referees,zone_observers',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $notificationType = $validated['notification_type'];
        $isCrcNotification = $notificationType === 'crc_referees';

        try {
            // Prepara destinatari
            $toRecipients = [];
            $ccRecipients = [];

            // TO: Comitato Campionati (checkbox checked = presente nella request)
            if ($request->has('send_to_campionati')) {
                $toRecipients[] = [
                    'email' => config('golf.emails.ufficio_campionati', 'campionati@federgolf.it'),
                    'name' => 'Comitato Campionati',
                ];
            }

            if ($isCrcNotification) {
                // CRC: CC a zona del torneo e arbitri designati
                if ($request->has('send_to_zone') && $tournament->club->zone?->email) {
                    $ccRecipients[] = [
                        'email' => $tournament->club->zone->email,
                        'name' => $tournament->club->zone->name,
                    ];
                }

                // CC Admin zonali
                $ccZoneAdmins = $request->input('cc_zone_admins', []);
                if (! empty($ccZoneAdmins)) {
                    $zoneAdmins = \App\Models\User::whereIn('id', $ccZoneAdmins)->get();
                    foreach ($zoneAdmins as $admin) {
                        $ccRecipients[] = ['email' => $admin->email, 'name' => $admin->name];
                    }
                }

                // CC Arbitri designati
                $ccReferees = $request->input('cc_referees', []);
                if (! empty($ccReferees)) {
                    $referees = \App\Models\User::whereIn('id', $ccReferees)->get();
                    foreach ($referees as $referee) {
                        $ccRecipients[] = ['email' => $referee->email, 'name' => $referee->name];
                    }
                }
            } else {
                // ZONA: CC a CRC e osservatori
                if ($request->has('send_to_crc')) {
                    $ccRecipients[] = [
                        'email' => config('golf.emails.crc', 'crc@federgolf.it'),
                        'name' => 'CRC',
                    ];
                }

                // CC Admin nazionali
                $ccNationalAdmins = $request->input('cc_national_admins', []);
                if (! empty($ccNationalAdmins)) {
                    $nationalAdmins = \App\Models\User::whereIn('id', $ccNationalAdmins)->get();
                    foreach ($nationalAdmins as $admin) {
                        $ccRecipients[] = ['email' => $admin->email, 'name' => $admin->name];
                    }
                }

                // CC Osservatori
                $ccObservers = $request->input('cc_observers', []);
                if (! empty($ccObservers)) {
                    $observers = \App\Models\User::whereIn('id', $ccObservers)->get();
                    foreach ($observers as $observer) {
                        $ccRecipients[] = ['email' => $observer->email, 'name' => $observer->name];
                    }
                }
            }

            // Verifica che ci siano destinatari
            if (empty($toRecipients) && empty($ccRecipients)) {
                return redirect()->back()->with('error', 'Nessun destinatario selezionato.');
            }

            // Invia email
            $successCount = 0;
            $errorCount = 0;

            // Prepara array CC nel formato corretto per Laravel Mail
            $ccArray = [];
            foreach ($ccRecipients as $cc) {
                $ccArray[$cc['email']] = $cc['name'];
            }

            // Invia email con CC
            foreach ($toRecipients as $recipient) {
                try {
                    \Illuminate\Support\Facades\Mail::raw($validated['message'], function ($mail) use ($recipient, $validated, $ccArray) {
                        $mail->to($recipient['email'], $recipient['name'])
                            ->subject($validated['subject']);

                        if (! empty($ccArray)) {
                            $mail->cc($ccArray);
                        }
                    });
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Errore invio email', [
                        'recipient' => $recipient['email'],
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            // Salva la lista completa di CC PRIMA di eventuali modifiche
            $originalCcRecipients = $ccRecipients;

            // Se non ci sono TO ma solo CC, usa il primo CC come TO
            if (empty($toRecipients) && ! empty($ccRecipients)) {
                $firstCc = array_shift($ccRecipients);
                $remainingCc = [];
                foreach ($ccRecipients as $cc) {
                    $remainingCc[$cc['email']] = $cc['name'];
                }
                try {
                    \Illuminate\Support\Facades\Mail::raw($validated['message'], function ($mail) use ($firstCc, $validated, $remainingCc) {
                        $mail->to($firstCc['email'], $firstCc['name'])
                            ->subject($validated['subject']);
                        if (! empty($remainingCc)) {
                            $mail->cc($remainingCc);
                        }
                    });
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Errore invio email (solo CC)', ['error' => $e->getMessage()]);
                    $errorCount++;
                }
            }

            // Prepara lista nomi destinatari per visualizzazione (usa la lista CC originale)
            $allRecipientNames = [];
            foreach ($toRecipients as $r) {
                $allRecipientNames[] = $r['name'];
            }
            foreach ($originalCcRecipients as $r) {
                $allRecipientNames[] = $r['name'];
            }
            $refereeList = implode(', ', $allRecipientNames);
            $totalRecipients = count($toRecipients) + count($originalCcRecipients);

            // Elimina la notifica "bozza" (notification_type = null) per evitare duplicati
            // Questo record viene creato automaticamente da prepareNotification() ma non serve per gare nazionali
            TournamentNotification::where('tournament_id', $tournament->id)
                ->whereNull('notification_type')
                ->whereNull('sent_at')
                ->delete();

            $typeLabel = $isCrcNotification ? 'arbitri designati' : 'osservatori';
            $totalSent = $successCount + count($ccRecipients);

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
