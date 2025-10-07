<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gestione convocazioni collettive e lettere circoli (solo DOCX)
 */
class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService) {
        $this->notificationService = $notificationService;
    }

    /**
     * Lista notifiche con gestione documenti
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isNationalAdmin = in_array($user->user_type, ['national_admin', 'super_admin']);

        $query = TournamentNotification::with([
            'tournament.club', 
            'tournament.zone',
            'tournament.assignments.user'
        ]);

        // Filtra per zona se non è admin nazionale
        if (!$isNationalAdmin) {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        $query->orderBy('sent_at', 'desc');
        $tournamentNotifications = $query->paginate(20);

        // Calcola il numero di destinatari e lista arbitri per ogni notifica
        foreach ($tournamentNotifications as $notification) {
            // Lista nomi arbitri
            $refereeNames = $notification->tournament->assignments
                ->map(function ($assignment) {
                    return $assignment->user->name;
                })->implode(', ');

            // Calcola numero destinatari
            $total = $notification->tournament->assignments->count() + 1; // arbitri + circolo

            // Aggiorna i campi nel database se necessario
            if (empty($notification->referee_list) || $notification->total_recipients != $total) {
                $notification->update([
                    'referee_list' => $refereeNames,
                    'total_recipients' => $total
                ]);

                // Aggiorna anche l'oggetto in memoria
                $notification->referee_list = $refereeNames;
                $notification->total_recipients = $total;
            }
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
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni.');
        }

        // Prepara o recupera la notifica
        $notification = TournamentNotification::firstOrCreate(
            ['tournament_id' => $tournament->id],
            [
                'status' => 'pending',
                'referee_list' => $tournament->assignments->pluck('user.name')->implode(', '),
                'total_recipients' => $tournament->assignments->count() + 1,
                'sent_by' => auth()->id()
            ]
        );

        // Genera documenti se non esistono
        if (empty($notification->documents)) {
            try {
                $documents = [];
                // Genera DOCX convocazione
                $convocationData = $this->documentService->generateConvocationForTournament($tournament);
                $docxPath = $this->fileStorage->storeInZone($convocationData, $tournament, 'docx');
                $documents['convocation'] = basename($docxPath);

                // Genera DOCX lettera circolo
                $clubDocData = $this->documentService->generateClubDocument($tournament);
                $clubDocxPath = $this->fileStorage->storeInZone($clubDocData, $tournament, 'docx');
                $documents['club_letter'] = basename($clubDocxPath);

                $notification->update(['documents' => $documents]);
            } catch (\Exception $e) {
                Log::error('Error generating documents in assignment form', [
                    'tournament_id' => $tournament->id,
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
                session()->flash('warning', 'Si è verificato un errore nella generazione dei documenti. È possibile rigenerarli manualmente.');
            }
        }

        // Carica gli arbitri assegnati
        $assignedReferees = $tournament->referees()->get();

        // Controlla stato documenti esistenti
        $documents = is_string($notification->documents) ?
            json_decode($notification->documents, true) : $notification->documents;
        $documents = $documents ?? [];

        $documentStatus = [
            'hasConvocation' => isset($documents['convocation']) &&
                Storage::disk('public')->exists("convocazioni/" . $this->getZoneFolder($tournament) . "/generated/{$documents['convocation']}"),
            'hasClubLetter' => isset($documents['club_letter']) &&
                Storage::disk('public')->exists("convocazioni/" . $this->getZoneFolder($tournament) . "/generated/{$documents['club_letter']}")
        ];

        // Controlla se esistono documenti
        $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

        // Carica email istituzionali
        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $groupedEmails = $institutionalEmails->groupBy('category');

        // Carica clausole disponibili
        $availableClauses = NotificationClause::active()
            ->ordered()
            ->get()
            ->groupBy('applies_to')
            ->toArray();

        return view('admin.notifications.assignment_form', [
            'tournament' => $tournament,
            'notification' => $notification,
            'assignedReferees' => $assignedReferees,
            'documentStatus' => $documentStatus,
            'hasExistingConvocation' => $hasExistingConvocation,
            'groupedEmails' => $groupedEmails,
            'institutionalEmails' => $institutionalEmails,
            'availableClauses' => $availableClauses
        ]);
    }

    /**
     * Stato documenti per il modal
     */
    public function documentsStatus(TournamentNotification $notification)
    {
        try {
            $tournament = $notification->tournament;
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : $notification->documents;
            $documents = $documents ?? [];

            $zone = $this->getZoneFolder($tournament);
            $response = [
                'notification_id' => $notification->id,
                'tournament_id' => $tournament->id,
                'convocation' => null,
                'club_letter' => null
            ];

            // Check convocazione DOCX
            if (!empty($documents['convocation'])) {
                $path = "convocazioni/{$zone}/generated/{$documents['convocation']}";
                if (Storage::disk('public')->exists($path)) {
                    $response['convocation'] = [
                        'filename' => $documents['convocation'],
                        'generated_at' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i'),
                        'size' => $this->formatBytes(Storage::disk('public')->size($path))
                    ];
                }
            }

            // Check lettera circolo
            if (!empty($documents['club_letter'])) {
                $path = "convocazioni/{$zone}/generated/{$documents['club_letter']}";
                if (Storage::disk('public')->exists($path)) {
                    $response['club_letter'] = [
                        'filename' => $documents['club_letter'],
                        'generated_at' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i'),
                        'size' => $this->formatBytes(Storage::disk('public')->size($path))
                    ];
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('Error checking documents status', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Errore nel caricamento dei documenti: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Genera/rigenera documento
     */
    public function generateDocument(Request $request, TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;
        
        try {
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : $notification->documents;
            $documents = $documents ?? [];

            Log::info('Generating document', [
                'type' => $type,
                'notification_id' => $notification->id,
                'tournament_id' => $tournament->id,
                'current_documents' => $documents
            ]);

            if ($type === 'convocation') {
                $convocationData = $this->documentService->generateConvocationForTournament($tournament, $notification);
                $docxPath = $this->fileStorage->storeInZone($convocationData, $tournament, 'docx');
                $documents['convocation'] = basename($docxPath);
            }

            if ($type === 'club_letter') {
                $docData = $this->documentService->generateClubDocument($tournament, $notification);
                $path = $this->fileStorage->storeInZone($docData, $tournament, 'docx');
                $documents['club_letter'] = basename($path);
            }

            Log::info('Generated document', [
                'type' => $type,
                'documents' => $documents,
                'zone' => $this->getZoneFolder($tournament)
            ]);

            $notification->update(['documents' => $documents]);

            // Get updated document status for UI refresh
            $status = $this->documentsStatus($notification)->getData();
            
            return response()->json([
                'success' => true,
                'message' => 'Documento generato con successo',
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Errore generazione documento', [
                'type' => $type,
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Errore nella generazione: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un documento della notifica (AJAX dal modal)
     */
    public function deleteDocument(Request $request, TournamentNotification $notification, $type)
    {
        try {
            $tournament = $notification->tournament;
            $documents = is_string($notification->documents)
                ? json_decode($notification->documents, true)
                : ($notification->documents ?? []);

            if (empty($documents[$type])) {
                return response()->json(['success' => false, 'message' => 'Documento non trovato'], 404);
            }

            $zone = $this->getZoneFolder($tournament);
            $path = "convocazioni/{$zone}/generated/{$documents[$type]}";

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

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
                'error' => $e->getMessage()
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
            $tournament = $notification->tournament;
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : $notification->documents;
            $documents = $documents ?? [];

            if (empty($documents[$type])) {
                throw new \Exception('Documento non trovato');
            }

            $zone = $this->getZoneFolder($tournament);
            $path = "convocazioni/{$zone}/generated/{$documents[$type]}";
            $fullPath = storage_path('app/public/' . $path);

            if (!file_exists($fullPath)) {
                throw new \Exception('File non trovato sul server');
            }

            // Log del download
            Log::info('Downloading document', [
                'notification_id' => $notification->id,
                'type' => $type,
                'path' => $path
            ]);

            return response()->download($fullPath, $type === 'convocation' ? 'Convocazione.docx' : 'Lettera_Circolo.docx');
        } catch (\Exception $e) {
            Log::error('Error downloading document', [
                'notification_id' => $notification->id,
                'type' => $type,
                'error' => $e->getMessage()
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
            DB::beginTransaction();
            
            Log::info('Sending notification', [
                'notification_id' => $notification->id,
                'metadata' => $notification->metadata
            ]);

            // Invia la notifica tramite il servizio
            $this->notificationService->send($notification);
            
            DB::commit();
            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche inviate con successo');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * Reinvia notifica
     */
    public function resend(TournamentNotification $notification)
    {
        try {
            DB::beginTransaction();
            
            // Forza il reinvio
            $this->notificationService->send($notification, true);
            
            DB::commit();
            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche reinviate con successo');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore reinvio notifiche: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Errore nel reinvio delle notifiche: ' . $e->getMessage());
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
            Log::info('Starting notification deletion', [
                'notification_id' => $notification->id,
                'tournament_id' => $notification->tournament_id,
                'documents' => $notification->documents
            ]);

            DB::beginTransaction();

            // Rimuovi i file allegati se esistono
            $documents = is_string($notification->documents)
                ? json_decode($notification->documents, true)
                : ($notification->documents ?? []);

            if (!empty($documents)) {
                $zone = $this->getZoneFolder($notification->tournament);
                $basePath = "convocazioni/{$zone}/generated/";

                // Aggiungi logging per debug
                Log::info('Attempting to delete documents', [
                    'zone' => $zone,
                    'basePath' => $basePath,
                    'documents' => $documents
                ]);

                foreach (['convocation', 'club_letter'] as $type) {
                    if (!empty($documents[$type])) {
                        $path = $basePath . $documents[$type];
                        if (Storage::disk('public')->exists($path)) {
                            Storage::disk('public')->delete($path);
                            Log::info("Deleted document: {$type}", ['path' => $path]);
                        } else {
                            Log::warning("Document not found: {$type}", ['path' => $path]);
                        }
                    }
                }
            }

            // Elimina la notifica
            $notification->delete();

            DB::commit();
            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifica eliminata con successo');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
            return redirect()->back()->with('error', "Errore durante l'eliminazione della notifica: " . $e->getMessage());
        }
    }

    /**
     * Invia notifica con allegati dal form
     */
    public function sendAssignmentWithConvocation(Request $request, Tournament $tournament)
    {
        Log::info('Starting sendAssignmentWithConvocation', [
            'tournament_id' => $tournament->id,
            'request_data' => $request->all()
        ]);

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
            'clauses.*' => 'nullable|exists:notification_clauses,id'
        ]);

        try {
            DB::beginTransaction();

            // Recupera o crea la notifica
            $notification = TournamentNotification::where('tournament_id', $tournament->id)
                ->orderBy('created_at', 'desc')
                ->firstOrFail();

            // Salva i metadati per l'invio
            $metadata = [
                'recipients' => []
            ];

            Log::info('Current tournament assignments', [
                'tournament_id' => $tournament->id,
                'assignments' => $tournament->assignments()->with('user')->get()->map(function($a) {
                    return ['id' => $a->user_id, 'name' => $a->user->name, 'role' => $a->role];
                })->toArray()
            ]);

            // Gestisci arbitri
            Log::info('Processing referee recipients', [
                'has_recipients' => $request->has('recipients'),
                'recipients_input' => $request->input('recipients'),
                'all_input' => $request->all()
            ]);

            if ($request->has('recipients')) {
                $metadata['recipients']['referees'] = $request->input('recipients');
                Log::info('Set referee recipients', ['referees' => $metadata['recipients']['referees']]);
            } else {
                Log::warning('No referee recipients in request');
            }

            // Gestisci club
            $metadata['recipients']['club'] = $request->boolean('send_to_club', true);

            // Gestisci email istituzionali
            if ($request->has('fixed_addresses')) {
                $metadata['recipients']['institutional'] = $request->input('fixed_addresses');
            }

            // Aggiungi altri metadati
            $metadata['subject'] = $validated['subject'];
            $metadata['message'] = $validated['message'];
            $metadata['attach_convocation'] = $request->boolean('attach_convocation', true);

            $notification->update([
                'metadata' => $metadata
            ]);

            // Salva le clausole selezionate
            if ($request->has('clauses')) {
                Log::info('Saving clauses', [
                    'notification_id' => $notification->id,
                    'clauses' => $request->input('clauses')
                ]);
                
                // Rimuovi le selezioni precedenti
                NotificationClauseSelection::where('tournament_notification_id', $notification->id)->delete();
                
                foreach ($request->input('clauses') as $placeholder => $clauseId) {
                    if (!empty($clauseId)) {
                        try {
                            $selection = NotificationClauseSelection::create([
                                'tournament_notification_id' => $notification->id,
                                'clause_id' => $clauseId,
                                'placeholder_code' => $placeholder
                            ]);
                            
                            Log::info('Clause selection created', [
                                'notification_id' => $notification->id,
                                'placeholder' => $placeholder,
                                'clause_id' => $clauseId,
                                'selection_id' => $selection->id
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error saving clause selection', [
                                'notification_id' => $notification->id,
                                'placeholder' => $placeholder,
                                'clause_id' => $clauseId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            Log::info('Sending notification from form', [
                'notification_id' => $notification->id,
                'metadata' => $metadata,
                'request_data' => $request->all()
            ]);

            // Rigenera i documenti con le clausole selezionate prima dell'invio
            try {
                $documents = is_string($notification->documents) ? json_decode($notification->documents, true) : ($notification->documents ?? []);
                $convocationData = $this->documentService->generateConvocationForTournament($tournament, $notification);
                $convPath = $this->fileStorage->storeInZone($convocationData, $tournament, 'docx');
                $documents['convocation'] = basename($convPath);

                $clubDocData = $this->documentService->generateClubDocument($tournament, $notification);
                $clubPath = $this->fileStorage->storeInZone($clubDocData, $tournament, 'docx');
                $documents['club_letter'] = basename($clubPath);
                $notification->update(['documents' => $documents]);
            } catch (\Throwable $e) {
                Log::warning('Could not regenerate documents with clauses before send', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Invia la notifica
            $this->notificationService->send($notification);

            DB::commit();

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifiche inviate con successo');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche con allegati: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * Recupera cartella zona per i file
     */
    private function getZoneFolder($tournament): string
    {
        return $this->fileStorage->getZoneFolder($tournament);
    }

    /**
     * Rigenera un documento esistente
     */
    public function regenerateDocument(Request $request, TournamentNotification $notification, $type)
    {
        return $this->generateDocument($request, $notification, $type);
    }

    /**
     * Formatta dimensione file
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
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
            'notification_id' => $notification?->id
        ]);
    }

    /**
     * Carica un documento manualmente
     */
    public function uploadDocument(Request $request, TournamentNotification $notification, $type)
    {
        try {
            $tournament = $notification->tournament;

            // Valida il file
            $request->validate([
                'document' => 'required|file|mimes:doc,docx|max:10240' // max 10MB
            ]);

            $file = $request->file('document');
            $documents = is_string($notification->documents) ?
                json_decode($notification->documents, true) : $notification->documents;
            $documents = $documents ?? [];

            // Salva il nuovo file
            $zone = $this->getZoneFolder($tournament);
            $filename = str_replace(' ', '_', $file->getClientOriginalName());

            // Muovi il file
            $file->storeAs("convocazioni/{$zone}/generated", $filename, 'public');

            // Aggiorna i documenti della notifica
            $documents[$type] = $filename;
            $notification->update(['documents' => $documents]);

            // Ritorna lo stato aggiornato per aggiornare il modal
            $status = $this->documentsStatus($notification)->getData();

            return response()->json([
                'success' => true,
                'message' => 'Documento caricato con successo',
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Errore caricamento documento', [
                'notification_id' => $notification->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
