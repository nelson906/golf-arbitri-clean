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
        private NotificationService $notificationService,
        private NotificationPreparationService $preparationService,
        private NotificationDocumentService $documentService,
        private NotificationTransactionService $transactionService
    ) {}

    /**
     * Lista notifiche con gestione documenti
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
        $tournamentNotifications = $query->paginate(20);

        // Aggiorna info destinatari per ogni notifica
        foreach ($tournamentNotifications as $notification) {
            $this->preparationService->updateRecipientInfo($notification);
        }

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
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni tramite <strong>Gestione Torneo</strong>.');
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
    public function generateDocument(Request $request, TournamentNotification $notification, $type)
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
    public function deleteDocument(Request $request, TournamentNotification $notification, $type)
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
            $this->transactionService->sendWithTransaction($notification, true);

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche reinviate con successo');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Errore nel reinvio delle notifiche: '.$e->getMessage());
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
}
