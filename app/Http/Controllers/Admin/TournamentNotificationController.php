<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Services\FileStorageService;
use App\Services\NotificationService;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\RefereeAssignmentMail;
use App\Mail\ClubNotificationMail;
use App\Mail\InstitutionalNotificationMail;
use App\Models\InstitutionalEmail;
use Carbon\Carbon;


class TournamentNotificationController extends Controller
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
        $query = TournamentNotification::with(['tournament.club', 'tournament.zone'])
            ->orderBy('sent_at', 'desc');

        // Filtri
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

        // Statistiche per dashboard
        $stats = [
            'total_sent' => TournamentNotification::where('status', 'sent')->sum('total_recipients'),
            'total_failed' => TournamentNotification::where('status', 'failed')->sum('total_recipients'),
            'this_month' => TournamentNotification::whereMonth('sent_at', now()->month)->sum('total_recipients'),
            'pending_tournaments' => Tournament::whereIn('status', ['closed', 'assigned'])->doesntHave('notifications')->count()
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
                $pdfPath = $this->documentService->generateConvocationPDF($tournament);
                $docxData = $this->documentService->generateClubDocument($tournament);
                $docxPath = $this->fileStorage->storeInZone($docxData, $tournament, 'docx');

                $notification->update([
                    'attachments' => [
                        'convocation' => basename($pdfPath),
                        'club_letter' => basename($docxPath)
                    ]
                ]);
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
    // In app/Http/Controllers/Admin/TournamentNotificationController.php

    public function edit(TournamentNotification $notification)
    {
        $tournamentNotification = $notification;
        return view('admin.tournament-notifications.edit', compact('tournamentNotification'));
    }

    /**
     * Send notifications with correct recipients
     */
    public function send(TournamentNotification $notification)
    {
        $tournament = Tournament::with(['club.zone', 'assignments.user', 'tournamentType'])->find($notification->tournament_id);
        $sent = 0;

        // RECUPERA GLI ATTACHMENTS ESISTENTI
        $existingAttachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;
        $existingAttachments = $existingAttachments ?? [];

        $zone = $this->getZoneFolder($tournament);

        // 1. PDF - USA ESISTENTE O GENERA
        if (
            isset($existingAttachments['convocation']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['convocation']}")
        ) {
            $pdfPath = "convocazioni/{$zone}/generated/{$existingAttachments['convocation']}";
            Log::info('Using existing PDF: ' . $pdfPath);
        } else {
            $pdfPath = $this->documentService->generateConvocationPDF($tournament);
            Log::info('Generated new PDF: ' . $pdfPath);
        }

        // 2. DOCX - USA ESISTENTE O GENERA
        if (
            isset($existingAttachments['club_letter']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['club_letter']}")
        ) {
            $docxPath = "convocazioni/{$zone}/generated/{$existingAttachments['club_letter']}";
            Log::info('Using existing DOCX: ' . $docxPath);
        } elseif (
            isset($existingAttachments['club']) &&
            Storage::disk('public')->exists("convocazioni/{$zone}/generated/{$existingAttachments['club']}")
        ) {
            $docxPath = "convocazioni/{$zone}/generated/{$existingAttachments['club']}";
            Log::info('Using existing club DOCX: ' . $docxPath);
        } else {
            $docxData = $this->documentService->generateClubDocument($tournament);
            $docxPath = $this->fileStorage->storeInZone($docxData, $tournament, 'docx');
            Log::info('Generated new DOCX: ' . $docxPath);
        }

        // Prepara array attachments per le email
        $attachmentsPaths = [
            storage_path('app/public/' . $pdfPath),
            storage_path('app/public/' . $docxPath)
        ];

        Log::info('Final PDF Path: ' . $pdfPath);
        Log::info('Final DOCX Path: ' . $docxPath);
        Log::info('Attachments array:', $attachmentsPaths);

        // 2. INVIA AGLI ARBITRI (con PDF)
        foreach ($tournament->assignments as $assignment) {
            Log::info('Sending to referee: ' . $assignment->user->email);
            Log::info('With attachment: ' . $attachmentsPaths[0]);

            Mail::to($assignment->user->email)
                ->send(new RefereeAssignmentMail($assignment, $tournament, [$attachmentsPaths[0]]));

            Notification::create([
                'assignment_id' => $assignment->id,
                'recipient_type' => 'referee',
                'recipient_email' => $assignment->user->email,
                'recipient_name' => $assignment->user->name,
                'subject' => "Convocazione {$assignment->role} - {$tournament->name}",
                'body' => 'Convocazione ufficiale in allegato',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 3. INVIA AL CIRCOLO (con PDF + DOCX)
        if ($tournament->club->email) {
            Mail::to($tournament->club->email)
                ->send(new ClubNotificationMail($tournament, $attachmentsPaths));

            Notification::create([
                'recipient_type' => 'club',
                'recipient_email' => $tournament->club->email,
                'recipient_name' => $tournament->club->name,
                'subject' => "Arbitri Assegnati - {$tournament->name}",
                'body' => 'Comunicazione arbitri assegnati',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 4. DESTINATARI ISTITUZIONALI
        $institutionalRecipients = $this->getInstitutionalRecipients($tournament);

        foreach ($institutionalRecipients as $recipient) {
            Mail::to($recipient['email'])
                ->send(new InstitutionalNotificationMail($tournament, $recipient['type']));

            Notification::create([
                'recipient_type' => 'institutional',
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'],
                'subject' => "[{$recipient['type']}] {$tournament->name}",
                'body' => 'Notifica istituzionale',
                'status' => 'sent',
                'sent_at' => now(),
                'tournament_id' => $tournament->id
            ]);

            $sent++;
        }

        // 5. AGGIORNA STATO
        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
            'total_recipients' => $sent,
            'details' => [
                'sent' => $sent,
                'arbitri' => $tournament->assignments->count(),
                'club' => 1,
                'institutional' => count($institutionalRecipients)
            ],
            'templates_used' => [
                'club' => 'tournament_assignment_generic',
                'referee' => 'tournament_assignment_generic',
                'institutional' => 'institutional_notification'
            ],
            'attachments' => [
                'convocation' => basename($pdfPath),
                'club_letter' => basename($docxPath)
            ]
        ]);

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', "Inviate {$sent} notifiche con allegati");
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

    public function update(Request $request, TournamentNotification $notification)
    {
        $notification->update([
            'referee_list' => $request->referee_list
        ]);

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', 'Notifica aggiornata');
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
     * 🗑️ Eliminazione notifica torneo
     */
    public function destroy(TournamentNotification $notification)
    {
        $notification->delete();

        return redirect()->route('admin.tournament-notifications.index')
            ->with('success', 'Notifica eliminata');
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
     * PREPARE - Crea record e genera documenti
     */
    public function prepare(Tournament $tournament)
    {
        $tournament->load(['assignments.user', 'club.zone']);

        // Recupera indirizzi email fissi
        $fixedEmails = InstitutionalEmail::active()
            ->forZone($tournament->zone_id)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        // Se esiste già una notifica, aggiorna
        $existingNotification = TournamentNotification::where('tournament_id', $tournament->id)
            ->whereIn('status', ['pending', 'sent'])
            ->first();

        if ($existingNotification) {
            return view('admin.tournament-notifications.prepare', compact(
                'tournament',
                'existingNotification',
                'fixedEmails'
            ));
        }

        // Altrimenti crea nuova
        return view('admin.tournament-notifications.prepare', compact(
            'tournament',
            'fixedEmails'
        ));
    }

    public function documentsStatus(TournamentNotification $notification)
    {
        $tournament = $notification->tournament;
        $attachments = is_string($notification->attachments) ?
            json_decode($notification->attachments, true) : $notification->attachments;

        $convocation = null;
        $clubLetter = null;

        $zone = $this->getZoneFolder($tournament);

        // Controlla convocazione
        if (isset($attachments['convocation'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['convocation']}";

            if (Storage::disk('public')->exists($path)) {
                $convocation = [
                    'filename' => $attachments['convocation'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        // Controlla lettera circolo - PRIMA club_letter POI club
        if (isset($attachments['club_letter'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['club_letter']}";

            if (Storage::disk('public')->exists($path)) {
                $clubLetter = [
                    'filename' => $attachments['club_letter'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        } elseif (isset($attachments['club'])) {
            $path = "convocazioni/{$zone}/generated/{$attachments['club']}";

            if (Storage::disk('public')->exists($path)) {
                $clubLetter = [
                    'filename' => $attachments['club'],
                    'generated_at' => Storage::disk('public')->lastModified($path) ?
                        Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->format('d/m/Y H:i') : 'N/A',
                    'size' => $this->formatBytes(Storage::disk('public')->size($path))
                ];
            }
        }

        return response()->json([
            'notification_id' => $notification->id,
            'tournament_id' => $tournament->id,
            'convocation' => $convocation,
            'club_letter' => $clubLetter
        ]);
    }

    // Aggiungi questo metodo helper
    private function getZoneFolder($tournament): string
    {
        // Usa il metodo del FileStorageService per consistenza
        return $this->fileStorage->getZoneFolder($tournament);
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
    /**
     * Genera un documento
     */
    public function generateDocument(Request $request, TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;

        try {
            if ($type === 'convocation') {
                // Genera convocazione usando il DocumentGenerationService
                $path = $this->documentService->generateConvocationForTournament($tournament);

                // Aggiorna attachments
                // Aggiorna attachments
                $attachments = $notification->attachments;

                // DECODIFICA SE È STRINGA
                if (is_string($attachments)) {
                    $attachments = json_decode($attachments, true) ?? [];
                }

                // Se ancora non è array, inizializza
                if (!is_array($attachments)) {
                    $attachments = [];
                }

                $attachments['convocation'] = basename($path);
                $notification->update(['attachments' => json_encode($attachments)]);

                return redirect()->back()->with('success', 'Convocazione generata con successo');
            }

            if ($type === 'club_letter') {
                $docData = $this->documentService->generateClubDocument($tournament);
                $path = $this->fileStorage->storeInZone($docData, $tournament, 'docx');

                $attachments = is_string($notification->attachments) ?
                    json_decode($notification->attachments, true) : $notification->attachments;
                $attachments = $attachments ?? [];

                $attachments['club_letter'] = basename($path);
                $notification->update(['attachments' => json_encode($attachments)]);

                return redirect()->back()->with('success', 'Facsimile generato con successo');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore nella generazione: ' . $e->getMessage());
        }
    }

    /**
     * Rigenera un documento
     */
    public function regenerateDocument(Request $request, TournamentNotification $notification, $type)
    {
        // Usa lo stesso metodo di generate
        return $this->generateDocument($request, $notification, $type);
    }

    /**
     * Scarica un documento
     */
    // Per scaricare
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

    // Per caricare file modificato
    public function uploadDocument(Request $request, TournamentNotification $notification, $type)
    {
        $request->validate([
            'document' => 'required|file|mimes:doc,docx|max:10240'
        ]);

        $file = $request->file('document');
        $tournament = $notification->tournament;

        // Gestisci attachments come stringa o array (NON attachmentPaths!)
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

        return redirect()->back()->with('success', 'Documento caricato con successo');
    }


    /**
     * Elimina un documento
     */
    public function deleteDocument(TournamentNotification $notification, $type)
    {
        $tournament = $notification->tournament;

        try {
            if ($type === 'convocation') {
                $this->fileStorage->deleteConvocation($tournament);

                $attachments = $notification->attachments ?? [];
                unset($attachments['convocation']);
                $notification->update(['attachments' => $attachments]);
            }

            if ($type === 'club_letter') {
                $this->fileStorage->deleteClubLetter($tournament);

                $attachments = $notification->attachments ?? [];
                unset($attachments['club_letter']);
                $notification->update(['attachments' => $attachments]);
            }

            return redirect()->back()->with('success', 'Documento eliminato');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore nell\'eliminazione: ' . $e->getMessage());
        }
    }

    public function findByTournament(Tournament $tournament)
    {
        $notification = TournamentNotification::where('tournament_id', $tournament->id)
            ->latest()
            ->first();

        return response()->json([
            'notification_id' => $notification ? $notification->id : null
        ]);
    }
}
