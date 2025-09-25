<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Assignment;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\InstitutionalEmail;
use App\Models\Zone;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\DocumentGenerationService;
use App\Mail\RefereeAssignmentMail;
use App\Mail\ClubNotificationMail;
use App\Mail\InstitutionalNotificationMail;
use App\Mail\AssignmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;



class NotificationController extends Controller
{

    protected $fileStorage;
    protected $documentService;
    protected $notificationService;

    public function __construct(
        FileStorageService $fileStorage,
        DocumentGenerationService $documentService,
        NotificationService $notificationService
    ) {
        $this->fileStorage = $fileStorage;
        $this->documentService = $documentService;
        $this->notificationService = $notificationService;
    }

    /**
     * 📋 Vista principale - Notifiche raggruppate per torneo
     */
    public function index(Request $request)
    {
        // ✅ AGGIUNGERE QUESTO BLOCCO ALL'INIZIO
        $user = auth()->user();
        $isNationalAdmin = $user->user_type === 'national_admin' || $user->user_type === 'super_admin';

        $query = TournamentNotification::with(['tournament.club', 'tournament.zone'])
            ->orderBy('sent_at', 'desc');

        // ✅ FILTRO ZONA AUTOMATICO PER ADMIN
        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }
        // ✅ FINE BLOCCO DA AGGIUNGERE

        // Filtri manuali (il resto rimane uguale)
        if ($request->filled('zone_id')) {
            $query->whereHas('tournament', function ($q) use ($request) {
                $q->where('zone_id', $request->zone_id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        $tournamentNotifications = $query->paginate(20);

        // ✅ ANCHE LE STATISTICHE DEVONO ESSERE FILTRATE
        $stats = [
            'total_sent' => $this->getFilteredStats('sent', $user, $isNationalAdmin),
            'total_failed' => $this->getFilteredStats('failed', $user, $isNationalAdmin),
            'this_month' => $this->getFilteredStatsThisMonth($user, $isNationalAdmin),
            'pending_tournaments' => $this->getPendingTournaments($user, $isNationalAdmin)
        ];

        return view('admin.tournament-notifications.index', compact('tournamentNotifications', 'stats'));
    }


    /**
     * 🎯 Form invio notifiche per torneo specifico
     */
    public function create(Tournament $tournament)
    {
        // Verifica che il torneo abbia assegnazioni
        if ($tournament->assignments->isEmpty()) {
            return redirect()->back()
                ->with('error', 'Il torneo non ha arbitri assegnati. Completare prima le assegnazioni.');
        }

        // Verifica che non sia già stato notificato
        if ($tournament->notifications()->exists()) {
            return redirect()->back()
                ->with('warning', 'Il torneo è già stato notificato. Usare la funzione "Reinvia" se necessario.');
        }

        // Template email disponibili
        $emailTemplates = [
            'tournament_assignment_generic' => 'Template Generico Standard',
            'tournament_assignment_formal' => 'Template Formale Ufficiale',
            'tournament_assignment_urgent' => 'Template Urgente',
            'tournament_assignment_casual' => 'Template Informale'
        ];

        return view('admin.tournament-notifications.create', compact('tournament', 'emailTemplates'));
    }

    /**
     * 📧 Invio unificato di tutte le notifiche del torneo
     */
    public function store(Request $request, Tournament $tournament)
    {
        $validated = $request->validate([
            'email_template' => 'required|string',
            'send_to_club' => 'boolean',
            'send_to_referees' => 'boolean',
            'generate_documents' => 'boolean',
            'institutional_emails' => 'array',
            'additional_emails' => 'array'
        ]);

        DB::beginTransaction();
        try {
            // Crea la notifica
            $notification = TournamentNotification::create([
                'tournament_id' => $tournament->id,
                'status' => 'pending',
                'referee_list' => $tournament->assignments->pluck('user.name')->implode(', '),
                'total_recipients' => $tournament->assignments->count() + 1,
                'sent_by' => auth()->id(),
                'templates_used' => ['email' => $request->email_template]
            ]);

            // Genera documenti se richiesto
            if ($request->boolean('generate_documents', true)) {
                $this->generateAllDocuments($tournament, $notification);
            }

            DB::commit();

            return redirect()->route('admin.tournament-notifications.index')
                ->with('success', 'Notifica preparata. Ora puoi inviarla.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * 👁️ Dettagli notifiche torneo con espansione
     */
    public function show($notification)
    {
        // Gestisce sia model binding che ID
        if (!$notification instanceof TournamentNotification) {
            $notification = TournamentNotification::findOrFail($notification);
        }

        $tournamentNotification = $notification;

        // Carica tutte le relazioni necessarie
        $tournamentNotification->load([
            'tournament.club',
            'tournament.assignments.referee',
            'tournament.assignments.user',
            'tournament.zone',
            'sentBy'
        ]);

        // Recupera le singole notifiche per dettagli
        $individualNotifications = $tournamentNotification->individualNotifications()
            ->with(['assignment.referee', 'assignment.user'])
            ->orderBy('recipient_type')
            ->orderBy('created_at')
            ->get();

        return view('admin.tournament-notifications.show', compact('tournamentNotification', 'individualNotifications'));
    }

    /**
     * 📝 Form di modifica notifica (redirect a show)
     */
    public function edit(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;  // ✅ Estrai il tournament dalla notification

        $this->checkAssignmentFormAuthorization($tournament);
        // CREA O TROVA LA NOTIFICATION

        // Genera documenti se non esistono
        if (empty($notification->attachments)) {
            $this->generateAllDocuments($tournament, $notification);
        }

        $assignments = $this->getTournamentAssignments($tournament);

        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $groupedEmails = $institutionalEmails->groupBy('category');

        // Get assigned referees
        $assignedReferees = $tournament->referees()->get();

        // Check existing documents
        $documentStatus = $this->checkDocumentStatus($tournament);
        $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

        return view('admin.notifications.assignment_form', compact(
            'tournament',
            'notification',  // ✅ Aggiungi anche notification
            'assignedReferees',
            'assignments',
            'groupedEmails',
            'documentStatus',
            'hasExistingConvocation',
            'institutionalEmails'
        ));
    }

    /**
     * ✅ ASSIGNMENT FORM: Mostra form per inviare notifiche da torneo specifico
     */
    public function showAssignmentForm(Tournament $tournament)
    {
        $this->checkAssignmentFormAuthorization($tournament);
        // CREA O TROVA LA NOTIFICATION
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
        if (empty($notification->attachments)) {
            $this->generateAllDocuments($tournament, $notification);
        }

        $assignments = $this->getTournamentAssignments($tournament);

        $institutionalEmails = InstitutionalEmail::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();
        $groupedEmails = $institutionalEmails->groupBy('category');

        // Get assigned referees
        $assignedReferees = $tournament->referees()->get();

        // Check existing documents
        $documentStatus = $this->checkDocumentStatus($tournament);
        $hasExistingConvocation = $documentStatus['hasConvocation'] || $documentStatus['hasClubLetter'];

        return view('admin.notifications.assignment_form', compact(
            'tournament',
            'assignedReferees',
            'assignments',
            'groupedEmails',
            'documentStatus',
            'hasExistingConvocation',
            'institutionalEmails'
        ));
    }

    /**
     * ✅ SEND ASSIGNMENT: Invia notifiche senza allegati
     */
    public function sendTournamentAssignment(Request $request, Tournament $tournament)
    {

        $validated = $this->validateAssignmentRequest($request);

        try {
            DB::beginTransaction();

            $assignments = $this->getTournamentAssignments($tournament);
            $emailData = [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'subject' => $validated['subject'],
                'message' => $validated['message']
            ];

            // Salva i metadati nella TournamentNotification per il metodo send()
            $notification = TournamentNotification::where('tournament_id', $tournament->id)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($notification) {
                $notification->update([
                    'metadata' => [
                        'recipients' => $request->all(),
                        'subject' => $validated['subject'],
                        'message' => $validated['message'],
                        'send_to_section' => $request->boolean('send_to_section'),
                        'send_to_club' => $request->boolean('send_to_club'),
                        'attach_convocation' => false  // Questo metodo non ha allegati
                    ]
                ]);
            }

            $this->processEmailSending($request, $emailData, $tournament);

            DB::commit();
            return redirect()->back()->with('success', 'Notifiche inviate con successo');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * ✅ SEND WITH ATTACHMENTS: Invia notifiche con allegati
     */
    public function sendAssignmentWithConvocation(Request $request, Tournament $tournament)
    {

        $this->checkAssignmentFormAuthorization($tournament);
        $validated = $this->validateAssignmentWithConvocationRequest($request);

        try {
            DB::beginTransaction();

            // Get convocation data
            $convocationData = $this->getConvocationData($tournament, $request);
            $assignments = $this->getTournamentAssignments($tournament);

            $emailData = [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'convocation' => $convocationData
            ];

            // Salva i metadati nella TournamentNotification per il metodo send()
            $notification = TournamentNotification::where('tournament_id', $tournament->id)
                ->orderBy('created_at', 'desc')
                ->first();
                
            if ($notification) {
                $notification->update([
                    'metadata' => [
                        'recipients' => $request->all(),
                        'subject' => $validated['subject'],
                        'message' => $validated['message'],
                        'send_to_section' => $request->boolean('send_to_section'),
                        'send_to_club' => $request->boolean('send_to_club'),
                        'attach_convocation' => $request->boolean('attach_convocation', true)
                    ]
                ]);
            }

            $this->processEmailSending($request, $emailData, $tournament);

            DB::commit();

            $message = 'Notifiche inviate con successo';


            if (!empty($convocationData)) {
                $attachmentNames = [];
                foreach ($convocationData as $attachment) {
                    if (isset($attachment['path']) && $attachment['path']) {
                        $attachmentNames[] = $attachment['type'] === 'convocation' ? 'convocazione' : 'lettera circolo';
                    }
                }
                if (!empty($attachmentNames)) {
                    $message .= ' con ' . implode(' e ', $attachmentNames) . ' allegata/e.';
                }
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore invio notifiche con allegati: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore nell\'invio delle notifiche: ' . $e->getMessage());
        }
    }

    /**
     * Send notifications with correct recipients
     */
    public function send(TournamentNotification $notification)
    {
        // Se non ci sono metadati salvati con le scelte dell'utente, 
        // reindirizza al form per configurare l'invio
        $metadata = is_string($notification->metadata) ? 
            json_decode($notification->metadata, true) : $notification->metadata;
            
        if (empty($metadata) || !isset($metadata['recipients'])) {
            return redirect()->route('admin.tournaments.show-assignment-form', $notification->tournament)
                ->with('info', 'Configura i destinatari e il messaggio per l\'invio');
        }

        $tournament = Tournament::with(['club.zone', 'assignments.user', 'tournamentType'])
            ->find($notification->tournament_id);
        $sent = 0;

        $existingAttachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $existingAttachments = $existingAttachments ?? [];

        $zone = $this->getZoneFolder($tournament);

        // Usa i metadati salvati per ricreare il request
        $mockRequest = new Request($metadata['recipients']);
        
        try {
            DB::beginTransaction();
            
            $assignments = $this->getTournamentAssignments($tournament);
            
            // Prepara i dati email con i metadati salvati
            $emailData = [
                'tournament' => $tournament,
                'assignments' => $assignments,
                'subject' => $metadata['subject'] ?? 'Assegnazione Arbitri - ' . $tournament->name,
                'message' => $metadata['message'] ?? 'Si comunica l\'assegnazione degli arbitri per il torneo.'
            ];
            
            // Se ci sono allegati, recuperali
            if ($metadata['attach_convocation'] ?? false) {
                $convocationData = $this->getConvocationData($tournament, $mockRequest);
                $emailData['convocation'] = $convocationData;
            }
            
            // Usa processEmailSending che rispetta tutte le impostazioni del form
            $this->processEmailSending($mockRequest, $emailData, $tournament);
            
            // Aggiorna lo stato della notifica
            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);
            
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
     * Find notification by tournament
     */
    public function findByTournament(Tournament $tournament)
    {
        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($notification) {
            return response()->json([
                'notification_id' => $notification->id,
                'tournament_id' => $tournament->id,
                'status' => $notification->status
            ]);
        }

        return response()->json([
            'notification_id' => null,
            'tournament_id' => $tournament->id,
            'status' => 'not_found'
        ]);
    }

    /**
     * Get institutional recipients based on tournament type
     */
    private function getInstitutionalRecipients(Tournament $tournament): array
    {
        $recipients = [];

        // Ufficio Campionati sempre
        $recipients[] = [
            'email' => config('golf.emails.ufficio_campionati', 'campionati@federgolf.it'),
            'name' => 'Ufficio Campionati FIG',
            'type' => 'COORDINAMENTO'
        ];

        if ($tournament->is_national || $tournament->tournamentType->is_national) {
            // Per tornei nazionali

            // SZR competente per conoscenza
            $recipients[] = [
                'email' => "szr{$tournament->zone_id}@federgolf.it",
                'name' => "SZR {$tournament->zone->name}",
                'type' => 'CONOSCENZA'
            ];
        } else {
            // Per tornei zonali

            // CRC Regionale per conoscenza
            $recipients[] = [
                'email' => 'crc@federgolf.it',
                'name' => 'CRC - Comitato Regionale',
                'type' => 'CONOSCENZA'
            ];
        }

        return $recipients;
    }
    /**
     * 🔄 Reinvio notifiche torneo
     */
    public function resend(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;
        return $this->send($notification);
    }

    /**
     * 📊 API per statistiche dashboard
     */
    public function stats(Request $request)
    {
        $stats = [
            'today' => TournamentNotification::whereDate('sent_at', today())->sum('total_recipients'),
            'this_week' => TournamentNotification::whereBetween('sent_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_recipients'),
            'this_month' => TournamentNotification::whereMonth('sent_at', now()->month)->sum('total_recipients'),
            'success_rate' => $this->calculateSuccessRate(),
            'pending_tournaments' => Tournament::whereIn('status', ['closed', 'assigned'])->doesntHave('notifications')->count(),
            'failed_today' => TournamentNotification::whereDate('sent_at', today())->where('status', 'failed')->count(),
            'partial_today' => TournamentNotification::whereDate('sent_at', today())->where('status', 'partial')->count()
        ];

        // Statistiche per zona se richiesto
        if ($request->filled('zone_id')) {
            $stats['zone_stats'] = TournamentNotification::forZone($request->zone_id)
                ->selectRaw('status, COUNT(*) as count, SUM(total_recipients) as recipients')
                ->groupBy('status')
                ->get();
        }

        return response()->json($stats);
    }

    /**
     * 📈 Esporta dati notifiche per analisi
     */
    public function export(Request $request)
    {
        $query = TournamentNotification::with(['tournament.club', 'tournament.zone', 'sentBy'])
            ->orderBy('sent_at', 'desc');

        // Applica filtri export
        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->date_to);
        }

        if ($request->filled('zone_id')) {
            $query->forZone($request->zone_id);
        }

        $notifications = $query->limit(1000)->get();

        $filename = 'tournament_notifications_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($notifications) {
            $file = fopen('php://output', 'w');

            // Header CSV
            fputcsv($file, [
                'ID',
                'Torneo',
                'Zona',
                'Circolo',
                'Data Invio',
                'Stato',
                'Destinatari Totali',
                'Destinatari Club',
                'Destinatari Arbitri',
                'Destinatari Istituzionali',
                'Fallimenti',
                'Tasso Successo %',
                'Inviato Da',
                'Template Club',
                'Template Arbitri',
                'Template Istituzionali'
            ]);

            // Dati
            foreach ($notifications as $notification) {
                $stats = $notification->stats;
                fputcsv($file, [
                    $notification->id,
                    $notification->tournament->name,
                    $notification->tournament->zone->code ?? 'N/A',
                    $notification->tournament->club->name ?? 'N/A',
                    $notification->sent_at->format('Y-m-d H:i:s'),
                    $notification->status,
                    $notification->total_recipients,
                    $stats['club_sent'],
                    $stats['referees_sent'],
                    $stats['institutional_sent'],
                    $stats['total_failed'],
                    $stats['success_rate'],
                    $notification->sentBy->name ?? 'N/A',
                    $notification->templates_used['club'] ?? 'N/A',
                    $notification->templates_used['referee'] ?? 'N/A',
                    $notification->templates_used['institutional'] ?? 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * 🎯 Calcolo percentuale successo
     */
    private function calculateSuccessRate(): float
    {
        $total = TournamentNotification::sum('total_recipients');
        $failed = TournamentNotification::where('status', 'failed')->sum('total_recipients');

        return $total > 0 ? round((($total - $failed) / $total) * 100, 1) : 0;
    }

    /**
     * Get document status
     */
    public function documentsStatus(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;

        $convocation = null;
        $convocationPdf = null;
        $clubLetter = null;

        $zone = $this->getZoneFolder($tournament);

        // Check convocazione DOCX
        if (isset($attachments['convocation'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['convocation']}";
            if (Storage::disk('public')->exists($path)) {
                $convocation = [
                    'filename' => $attachments['convocation'],
                    'generated_at' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i'),
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        // Check convocazione PDF (se già inviata)
        if (isset($attachments['convocation_pdf'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['convocation_pdf']}";
            if (Storage::disk('public')->exists($path)) {
                $convocationPdf = [
                    'filename' => $attachments['convocation_pdf'],
                    'generated_at' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i'),
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        // Check lettera circolo
        if (isset($attachments['club_letter'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['club_letter']}";
            if (Storage::disk('public')->exists($path)) {
                $clubLetter = [
                    'filename' => $attachments['club_letter'],
                    'generated_at' => Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i'),
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        return response()->json([
            'notification_id' => $notification->id,
            'tournament_id' => $tournament->id,
            'convocation' => $convocation,           // DOCX
            'convocation_pdf' => $convocationPdf,    // PDF (solo se inviata)
            'club_letter' => $clubLetter
        ]);
    }

    /**
     * Download document
     */
    public function downloadDocument(TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;

        $filename = null;

        // Per club_letter, cerca in entrambe le chiavi
        if ($type === 'club_letter') {
            if (isset($attachments['club_letter'])) {
                $filename = $attachments['club_letter'];
            } elseif (isset($attachments['club'])) {
                $filename = $attachments['club'];
            }
        } else {
            $filename = $attachments[$type] ?? null;
        }

        if (!$filename) {
            return redirect()->back()->with('error', 'Documento non trovato negli attachments');
        }

        $zone = $this->getZoneFolder($tournament);
        $path = "convocazioni/{$zone}/generated/{$filename}";
        $fullPath = storage_path('app/public/' . $path);

        if (file_exists($fullPath)) {
            return response()->download($fullPath);
        }

        return redirect()->back()->with('error', 'File non trovato sul server: ' . $path);
    }

    /**
     * Upload document
     */
    public function uploadDocument(Request $request, TournamentNotification $notification, $type)
    {
        $request->validate([
            'document' => 'required|file|mimes:doc,docx|max:10240'
        ]);

        $file = $request->file('document');
        $tournament = $notification->tournament;

        // Gestisci attachments come stringa o array
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $attachments = $attachments ?? [];

        // Usa il DocumentGenerationService che già hai
        $filename = isset($attachments[$type]) ?
            $attachments[$type] :
            $this->documentService->generateFilename($type, $tournament);

        // Prepara dati per storage
        $fileData = [
            'path' => $file->getRealPath(),
            'filename' => $filename,
            'type' => $type
        ];

        Log::info('Upload document - BEFORE', [
            'type' => $type,
            'current_attachments' => $notification->attachments,
            'filename_to_use' => $filename
        ]);

        $path = $this->fileStorage->storeInZone($fileData, $tournament, 'docx');

        Log::info('Upload document - AFTER storage', [
            'stored_path' => $path,
            'basename' => basename($path)
        ]);

        // Aggiorna attachments nel database
        $attachments[$type] = basename($path);
        $notification->update(['attachments' => json_encode($attachments)]);

        // Ricarica per verificare
        $notification->refresh();

        Log::info('Upload document - AFTER update', [
            'updated_attachments' => $notification->attachments
        ]);

        // Restituisci JSON per AJAX
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Documento caricato con successo'
            ]);
        }

        return redirect()->back()->with('success', 'Documento caricato con successo');
    }

    /**
     * Generate document
     */
    public function generateDocument(Request $request, TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;

        try {
            $attachments = is_string($notification->attachments) ?
                json_decode($notification->attachments, true) : $notification->attachments;
            $attachments = $attachments ?? [];

            if ($type === 'convocation') {
                // Genera DOCX
                $convocationData = $this->documentService->generateConvocationForTournament($tournament);
                $docxPath = $this->fileStorage->storeInZone($convocationData, $tournament, 'docx');
                $attachments['convocation'] = basename($docxPath);

                // Genera ANCHE il PDF con lo stesso formato nome!
                $pdfPath = $this->documentService->generateConvocationPDF($tournament);
                $attachments['convocation_pdf'] = basename($pdfPath);

                Log::info('Generated convocation documents:', [
                    'docx' => basename($docxPath),
                    'pdf' => basename($pdfPath)
                ]);
            }

            if ($type === 'club_letter') {
                $docData = $this->documentService->generateClubDocument($tournament);
                $path = $this->fileStorage->storeInZone($docData, $tournament, 'docx');
                $attachments['club_letter'] = basename($path);

                Log::info('Generated club letter document:', [
                    'docx' => basename($path)
                ]);
            }

            $notification->update(['attachments' => json_encode($attachments)]);

            // Restituisci JSON per AJAX
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento generato con successo'
                ]);
            }

            return redirect()->back()->with('success', 'Documento generato con successo');
        } catch (\Exception $e) {
            Log::error('Errore generazione documento', [
                'type' => $type,
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore nella generazione: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Errore nella generazione: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate document
     */
    public function regenerateDocument(Request $request, TournamentNotification $notification, $type)
    {
        // Usa lo stesso metodo di generate
        return $this->generateDocument($request, $notification, $type);
    }

    /**
     * Delete document
     */
    public function deleteDocument(TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;

        try {
            $attachments = is_string($notification->attachments) ?
                json_decode($notification->attachments, true) : $notification->attachments;
            $attachments = $attachments ?? [];

            $zone = $this->getZoneFolder($tournament);

            if ($type === 'convocation') {
                // Elimina sia DOCX che PDF
                if (isset($attachments['convocation'])) {
                    $docxPath = "convocazioni/{$zone}/generated/{$attachments['convocation']}";
                    Storage::disk('public')->delete($docxPath);
                    unset($attachments['convocation']);
                }

                if (isset($attachments['convocation_pdf'])) {
                    $pdfPath = "convocazioni/{$zone}/generated/{$attachments['convocation_pdf']}";
                    Storage::disk('public')->delete($pdfPath);
                    unset($attachments['convocation_pdf']);
                }
            }

            if ($type === 'club_letter') {
                if (isset($attachments['club_letter'])) {
                    $path = "convocazioni/{$zone}/generated/{$attachments['club_letter']}";
                    Storage::disk('public')->delete($path);
                    unset($attachments['club_letter']);
                }
            }

            $notification->update(['attachments' => json_encode($attachments)]);

            // Restituisci JSON per AJAX
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Documento eliminato con successo'
                ]);
            }

            return redirect()->back()->with('success', 'Documento eliminato');
        } catch (\Exception $e) {
            Log::error('Error deleting document', [
                'type' => $type,
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Errore nell\'eliminazione: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', 'Errore nell\'eliminazione: ' . $e->getMessage());
        }
    }

    /**
     * Get zone folder
     */
    private function getZoneFolder($tournament): string
    {
        // Usa il metodo del FileStorageService per consistenza
        return $this->fileStorage->getZoneFolder($tournament);
    }

    /**
     * Format bytes
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Check authorization for assignment form
     */
    private function checkAssignmentFormAuthorization(Tournament $tournament)
    {
        $user = Auth::user();

        if (!$user->hasRole('admin') && !$user->hasRole('super_admin') && !$user->hasRole('national_admin')) {
            abort(403, 'Non hai i permessi per inviare notifiche.');
        }

        if ($user->hasRole('national_admin') && (!$tournament->tournamentType || !$tournament->tournamentType->is_national)) {
            abort(403, 'Non hai accesso a questo torneo non nazionale.');
        }
    }

    /**
     * Get tournament assignments
     */
    private function getTournamentAssignments(Tournament $tournament)
    {
        try {
            Log::info('Getting tournament assignments', [
                'tournament_id' => $tournament->id,
                'tournament_name' => $tournament->name
            ]);

            // Try to get assignments from database
            $assignments = Assignment::where('tournament_id', $tournament->id)->get();

            if ($assignments->isEmpty()) {
                Log::warning('No assignments found for tournament', [
                    'tournament_id' => $tournament->id
                ]);

                // Try alternative: get from pivot table
                $assignedReferees = $tournament->referees ?? collect();

                if ($assignedReferees->isNotEmpty()) {
                    Log::info('Found assigned referees via pivot', [
                        'count' => $assignedReferees->count()
                    ]);

                    // Convert pivot data to assignment-like structure
                    $mockAssignments = $assignedReferees->map(function ($referee) use ($tournament) {
                        return (object)[
                            'id' => $referee->pivot->id ?? uniqid(),
                            'tournament_id' => $tournament->id,
                            'user_id' => $referee->id,
                            'role' => $referee->pivot->role ?? 'Arbitro',
                            'user' => $referee,
                            'assigned_at' => $referee->pivot->assigned_at ?? now(),
                            'tournament' => $tournament
                        ];
                    });

                    return $mockAssignments;
                }

                return collect();
            }

            // Load relations
            $assignments->load(['user', 'assignedBy', 'tournament']);

            Log::info('Assignments loaded successfully', [
                'count' => $assignments->count(),
                'tournament_id' => $tournament->id
            ]);

            return $assignments;
        } catch (\Exception $e) {
            Log::error('Error in getTournamentAssignments', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return collect();
        }
    }

    /**
     * Check document status
     */
    private function checkDocumentStatus(Tournament $tournament)
    {
        $zone = $this->fileStorage->getZoneFolder($tournament);
        $basePath = "convocazioni/{$zone}/generated/";

        $files = Storage::disk('public')->files($basePath);

        $hasConvocation = false;
        $hasClubLetter = false;

        foreach ($files as $file) {
            $filename = basename($file);

            // Cerca convocazione DOCX
            if (
                str_contains(strtolower($filename), 'convocazione') &&
                str_ends_with($filename, '.docx')
            ) {
                $hasConvocation = true;
            }

            // Cerca facsimile/lettera circolo
            if ((str_contains(strtolower($filename), 'facsimile') ||
                    str_contains(strtolower($filename), 'lettera')) &&
                str_ends_with($filename, '.docx')
            ) {
                $hasClubLetter = true;
            }
        }

        return [
            'hasConvocation' => $hasConvocation,
            'hasClubLetter' => $hasClubLetter
        ];
    }

    /**
     * Validate assignment request
     */
    private function validateAssignmentRequest(Request $request)
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'send_to_club' => 'boolean',
            'fixed_addresses' => 'nullable|array',
            'fixed_addresses.*' => 'exists:institutional_emails,id',
            'additional_emails' => 'nullable|array',
            'additional_emails.*' => 'nullable|email',
            'additional_names' => 'nullable|array',
            'additional_names.*' => 'nullable|string'
        ]);
    }

    /**
     * Validate assignment with convocation request
     */
    private function validateAssignmentWithConvocationRequest(Request $request)
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'recipients' => 'nullable|array',
            'recipients.*' => 'exists:users,id',
            'fixed_addresses' => 'nullable|array',
            'fixed_addresses.*' => 'exists:institutional_emails,id',
            'additional_emails' => 'nullable|array',
            'additional_emails.*' => 'nullable|email',
            'additional_names' => 'nullable|array',
            'additional_names.*' => 'nullable|string',
            'attach_convocation' => 'boolean'
        ]);
    }

    /**
     * Get convocation data
     */
    private function getConvocationData(Tournament $tournament, Request $request)
    {
        $attachments = [];

        if (!$request->has('attach_convocation') || !$request->attach_convocation) {
            return $attachments;
        }

        $zone = $this->fileStorage->getZoneFolder($tournament);
        $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
        $tournamentName = substr($tournamentName, 0, 50);

        // 1. Aggiungi SOLO PDF convocazione per gli arbitri
        $pdfFilename = "convocazione_{$tournament->id}_{$tournamentName}.pdf";
        $pdfPath = "convocazioni/{$zone}/generated/{$pdfFilename}";

        if (Storage::disk('public')->exists($pdfPath)) {
            $attachments[] = [
                'path' => Storage::disk('public')->path($pdfPath),
                'filename' => $pdfFilename,
                'type' => 'convocation_pdf'  // Cambiato tipo per distinguere
            ];
            Log::info('Found PDF convocation: ' . $pdfFilename);
        }

        // 2. Aggiungi lettera circolo DOCX (SOLO per il circolo)
        $clubLetterData = $this->getClubLetterData($tournament);
        if ($clubLetterData['path']) {
            $attachments[] = $clubLetterData;
            Log::info('Found club letter: ' . $clubLetterData['filename']);
        }

        return $attachments;
    }

    /**
     * Get main convocation data - CERCA IL PDF PER GLI ARBITRI!
     */
    private function getMainConvocationData(Tournament $tournament)
    {
        $zone = $this->fileStorage->getZoneFolder($tournament);
        $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
        $tournamentName = substr($tournamentName, 0, 50);

        // CERCA PRIMA IL PDF (per gli arbitri)
        $pdfFilename = "convocazione_{$tournament->id}_{$tournamentName}.pdf";
        $pdfPath = "convocazioni/{$zone}/generated/{$pdfFilename}";

        if (Storage::disk('public')->exists($pdfPath)) {
            return [
                'path' => Storage::disk('public')->path($pdfPath),
                'filename' => $pdfFilename,
                'type' => 'convocation'
            ];
        }

        // Se non trova il PDF, cerca il DOCX
        $docxFilename = "convocazione_{$tournament->id}_{$tournamentName}.docx";
        $docxPath = "convocazioni/{$zone}/generated/{$docxFilename}";

        if (Storage::disk('public')->exists($docxPath)) {
            return [
                'path' => Storage::disk('public')->path($docxPath),
                'filename' => $docxFilename,
                'type' => 'convocation'
            ];
        }

        return ['path' => null, 'filename' => null, 'type' => 'convocation'];
    }

    /**
     * Get club letter data
     */
    private function getClubLetterData(Tournament $tournament)
    {
        $clubLetterData = ['path' => null, 'filename' => 'lettera_circolo.docx', 'type' => 'club_letter'];

        // Pattern standard come in gestione_arbitri
        $zone = $this->fileStorage->getZoneFolder($tournament);
        $tournamentName = preg_replace('/[^A-Za-z0-9\-]/', '_', $tournament->name);
        $tournamentName = substr($tournamentName, 0, 50);
        $expectedFilename = "lettera_circolo_{$tournament->id}_{$tournamentName}.docx";
        $expectedPath = "convocazioni/{$zone}/generated/{$expectedFilename}";

        if (Storage::disk('public')->exists($expectedPath)) {
            $clubLetterData['path'] = Storage::disk('public')->path($expectedPath);
            $clubLetterData['filename'] = $expectedFilename;
        }

        return $clubLetterData;
    }

    /**
     * Process email sending
     */
    private function processEmailSending(Request $request, array $emailData, Tournament $tournament)
    {
        $recipients = $this->collectAllRecipients($request, $emailData, $tournament);

        foreach ($recipients as $recipient) {
            try {
                $this->sendNotificationEmail(
                    $recipient['email'],
                    $recipient['name'],
                    $emailData['subject'],
                    $emailData['message'],
                    $tournament,
                    $recipient['type'] === 'referee' ? $recipient['assignment'] : null,
                    $recipient['type'] === 'club',
                    $emailData['convocation'] ?? [],
                    $recipient['type']
                );
            } catch (\Exception $e) {
                Log::error('Error sending email to ' . $recipient['email'], [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Collect all recipients
     */
    private function collectAllRecipients(Request $request, array $emailData, Tournament $tournament)
    {
        $recipients = [];

        // Add referee recipients
        if ($request->has('recipients')) {
            foreach ($request->recipients as $userId) {
                $assignment = $emailData['assignments']->firstWhere('user_id', $userId);
                if ($assignment) {
                    $recipients[] = [
                        'email' => $assignment->user->email,
                        'name' => $assignment->user->name,
                        'type' => 'referee',
                        'assignment' => $assignment
                    ];
                }
            }
        }

        // Add club if requested
        if ($request->boolean('send_to_club') && $tournament->club) {
            $clubEmail = $this->getClubEmail($tournament->club);
            if ($clubEmail) {
                $recipients[] = [
                    'email' => $clubEmail,
                    'name' => $tournament->club->name,
                    'type' => 'club',
                    'assignment' => null
                ];
            }
        }
        // Add section zone email if requested
        if ($request->boolean('send_to_section') && $tournament->club->zone) {
            $zoneEmail = $this->getSectionEmail($tournament->club->zone);
            if ($zoneEmail) {
                $recipients[] = [
                    'email' => $zoneEmail,
                    'name' => 'Sezione ' . $tournament->club->zone->name,
                    'type' => 'institutional',  // Usa 'institutional' invece di 'section'
                    'assignment' => null
                ];
            }
        }
        // Add institutional emails
        if ($request->has('fixed_addresses')) {
            $institutionalEmails = InstitutionalEmail::whereIn('id', $request->fixed_addresses)->get();
            foreach ($institutionalEmails as $instEmail) {
                $recipients[] = [
                    'email' => $instEmail->email,
                    'name' => $instEmail->name,
                    'type' => 'institutional',
                    'assignment' => null
                ];
            }
        }

        // Add additional emails
        if ($request->has('additional_emails')) {
            foreach ($request->additional_emails as $index => $email) {
                if (!empty($email)) {
                    $recipients[] = [
                        'email' => $email,
                        'name' => $request->additional_names[$index] ?? 'Destinatario',
                        'type' => 'additional',
                        'assignment' => null
                    ];
                }
            }
        }

        return $recipients;
    }
    /**
     * Get section email
     */
    private function getSectionEmail(Zone $zone)
    {
        // Prima controlla se la zona ha un'email diretta
        if ($zone->email) {
            return $zone->email;
        }

        // Altrimenti cerca nelle email istituzionali per questa zona
        $sectionEmail = InstitutionalEmail::where('is_active', true)
            ->where('zone_id', $zone->id)
            ->where(function ($query) {
                $query->where('category', 'zone')
                    ->orWhere('category', 'sezione');
            })
            ->first();

        if ($sectionEmail) {
            return $sectionEmail->email;
        }

        // Fallback: costruisci email standard per la sezione
        return "sezione.zona{$zone->id}@federgolf.it";
    }
    /**
     * Send notification email
     */
    private function sendNotificationEmail(
        string $recipientEmail,
        string $recipientName,
        string $subject,
        string $message,
        Tournament $tournament,
        $assignment = null,
        bool $isClub = false,
        array $attachments = [],
        string $recipientType = null
    ) {
        // Prepare variables for replacement
        // Costruisci il formato date corretto
        $dateRange = $tournament->start_date->format('d/m/Y');
        if (!$tournament->start_date->isSameDay($tournament->end_date)) {
            $dateRange .= ' - ' . $tournament->end_date->format('d/m/Y');
        }
        
        $variables = [
            'referee_name' => $recipientName,
            'tournament_name' => $tournament->name,
            'tournament_date' => $tournament->start_date->format('d/m/Y'),
            'tournament_dates' => $dateRange,
            'club_name' => $tournament->club->name,
            'club_address' => $tournament->club->full_address ?? '',
            'role' => $assignment ? $assignment->role : '',
            'zone_name' => $tournament->zone->name ?? $tournament->club->zone->name,
            'assigned_date' => now()->format('d/m/Y'),
            'tournament_category' => $tournament->tournamentType->name ?? '',
            'contact_person' => $tournament->club->contact_person ?? 'N/A',
            'zone_email' => $this->getSectionEmail($tournament->club->zone),
            'club_email' => $tournament->club->email ?? null
        ];

        // Replace variables in subject and message
        $finalSubject = $this->replaceVariables($subject, $variables);
        $finalMessage = $this->replaceVariables($message, $variables);

        // Create notification record
        $notification = Notification::create([
            'assignment_id' => $assignment ? $assignment->id : null,
            'tournament_id' => $tournament->id,
            'recipient_type' => $this->getRecipientType($isClub, $recipientName, $recipient['type'] ?? null),
            'recipient_email' => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject' => $finalSubject,
            'body' => $finalMessage,
            'status' => 'pending',
            'template_used' => 'custom'
        ]);

        try {
            // IMPORTANTE: Filtra gli allegati in base al destinatario!
            $attachmentPaths = [];

            if ($assignment && !$isClub) {
                // ARBITRO: Invia SOLO il PDF della convocazione
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && !empty($attachment['path'])) {
                        // Controlla se è un PDF di convocazione
                        if ($attachment['type'] === 'convocation_pdf') {
                            $attachmentPaths[] = $attachment['path'];
                            Log::info('Attaching PDF to referee: ' . $attachment['filename']);
                        }
                    }
                }

                // Send to referee
                Mail::to($recipientEmail)
                    ->send(new RefereeAssignmentMail($assignment, $tournament, $attachmentPaths));
            } elseif ($isClub) {
                // CIRCOLO: Invia TUTTI gli allegati (PDF convocazione + DOCX lettera circolo)
                foreach ($attachments as $attachment) {
                    if (isset($attachment['path']) && !empty($attachment['path'])) {
                        $attachmentPaths[] = $attachment['path'];
                        Log::info('Attaching to club: ' . $attachment['filename']);
                    }
                }

                // Send to club
                Mail::to($recipientEmail)
                    ->send(new ClubNotificationMail($tournament, $attachmentPaths));
            } else {
                // ISTITUZIONALI (inclusa la sezione): Usa lo stesso template del club ma senza allegati
                // Prepara i dati degli arbitri per il template
                $referees = [];
                foreach ($tournament->assignments as $assignment) {
                    $referees[] = [
                        'name' => $assignment->user->name,
                        'role' => $assignment->role,
                        'email' => $assignment->user->email,
                        'phone' => $assignment->user->phone ?? 'N/A'
                    ];
                }
                
                // Aggiungi i dati degli arbitri alle variabili
                $variables['referees'] = $referees;
                $variables['tournament_id'] = $tournament->id;
                
                // Crea una notifica con tutti i dati necessari
                $enrichedNotification = clone $notification;
                $enrichedNotification->body = $finalMessage;
                $enrichedNotification->subject = $finalSubject;
                
                // Usa AssignmentNotification con le variabili complete
                Mail::to($recipientEmail)
                    ->send(new AssignmentNotification($enrichedNotification, $variables));
            }

            // Mark as sent
            $notification->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            Log::info('Email sent successfully', [
                'notification_id' => $notification->id,
                'recipient' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
                'assignment_id' => $assignment?->id
            ]);
        } catch (\Exception $e) {
            if (isset($notification) && $notification->exists) {
                $notification->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'retry_count' => ($notification->retry_count ?? 0) + 1
                ]);
            }

            Log::error('Error sending email', [
                'recipient' => $recipientEmail,
                'recipient_name' => $recipientName,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Replace variables in text
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }

    /**
     * Get recipient type
     */
    private function getRecipientType($isClub, $recipientName, $type = null)
    {
        // Se il tipo è già specificato, usalo direttamente
        if ($type) {
            return $type;
        }

        // Altrimenti determina il tipo basandosi su altri parametri
        if ($isClub) {
            return 'club';
        }

        if (str_contains($recipientName, 'Sezione')) {
            return 'institutional';  // Le sezioni sono considerate institutional
        }

        return 'referee';
    }
    /**
     * Get club email
     */
    private function getClubEmail($club)
    {
        if (isset($club->email) && filter_var($club->email, FILTER_VALIDATE_EMAIL)) {
            return $club->email;
        }

        $contactInfo = $club->contact_info;
        if (is_string($contactInfo)) {
            try {
                $contactInfo = json_decode($contactInfo, true);
            } catch (\Exception $e) {
                Log::warning("Failed to decode contact_info for club {$club->id}");
            }
        }

        if (is_array($contactInfo) && isset($contactInfo['email']) && !empty($contactInfo['email'])) {
            return $contactInfo['email'];
        }

        return null;
    }

    /**
     * Get filtered stats by status
     */
    private function getFilteredStats($status, $user, $isNationalAdmin): int
    {
        $query = TournamentNotification::where('status', $status);

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->count();
    }

    /**
     * Get filtered stats for this month
     */
    private function getFilteredStatsThisMonth($user, $isNationalAdmin): int
    {
        $query = TournamentNotification::whereMonth('sent_at', now()->month)
            ->whereYear('sent_at', now()->year);

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->whereHas('tournament', function ($q) use ($user) {
                $q->where('zone_id', $user->zone_id);
            });
        }

        return $query->sum('total_recipients');
    }

    /**
     * Get pending tournaments count
     */
    private function getPendingTournaments($user, $isNationalAdmin): int
    {
        $query = Tournament::whereIn('status', ['closed', 'assigned'])
            ->doesntHave('notifications');

        if (!$isNationalAdmin && $user->user_type !== 'super_admin') {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->count();
    }

    /**
     * Generate all documents for tournament notification
     * Centralizza la generazione di tutti i documenti per evitare duplicazione di codice
     */
    private function generateAllDocuments(Tournament $tournament, TournamentNotification $notification): void
    {
        try {
            // Genera DOCX convocazione
            $convocationData = $this->documentService->generateConvocationForTournament($tournament);
            $convocationDocxPath = $this->fileStorage->storeInZone($convocationData, $tournament, 'docx');

            // Genera PDF convocazione
            $pdfPath = $this->documentService->generateConvocationPDF($tournament);

            // Genera DOCX lettera circolo
            $clubDocData = $this->documentService->generateClubDocument($tournament);
            $clubDocxPath = $this->fileStorage->storeInZone($clubDocData, $tournament, 'docx');

            $notification->update([
                'attachments' => [
                    'convocation' => basename($convocationDocxPath),
                    'convocation_pdf' => basename($pdfPath),
                    'club_letter' => basename($clubDocxPath)
                ]
            ]);

            Log::info('All documents generated for tournament', [
                'tournament_id' => $tournament->id,
                'notification_id' => $notification->id,
                'docx_convocation' => basename($convocationDocxPath),
                'pdf_convocation' => basename($pdfPath),
                'docx_club_letter' => basename($clubDocxPath)
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating documents', [
                'tournament_id' => $tournament->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
