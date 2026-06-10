<?php

namespace Tests\Unit\Mail;

use App\Enums\AssignmentRole;
use App\Mail\AssignmentNotification;
use App\Mail\ClubNotificationMail;
use App\Models\TournamentNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regressione layer Mail (Audit v4, aggiornato al MODELLO A MAIL SINGOLA 2026-06:
 * TO = circolo con allegati, CC = arbitri/istituzionali/zona/aggiuntivi).
 *
 * Verifiche:
 *  DEAD-MAIL-02  AssignmentNotification non ha call site nel codice app/
 *  DISPATCH-01   L'arbitro selezionato è raggiunto (TO promosso o CC)
 *  DISPATCH-02   La mail parte verso il circolo quando recipients['club'] = true
 *
 * NB: i destinatari vivono in metadata['recipients'] (fonte di verità del
 * form, fix D1) — la colonna `recipients` non è più letta come input.
 */
class MailDispatchRegressionTest extends TestCase
{
    private function makeNotification(int $tournamentId, bool $club, array $refereeIds): TournamentNotification
    {
        return TournamentNotification::create([
            'tournament_id' => $tournamentId,
            'status'        => 'pending',
            'metadata'      => [
                'type'       => 'zone_referees',
                'recipients' => [
                    'club'          => $club,
                    'referees'      => $refereeIds,
                    'institutional' => [],
                ],
            ],
        ]);
    }

    // ====================================================================
    // DEAD-MAIL-02 — AssignmentNotification non è usata nel codice app/
    // ====================================================================

    /**
     * AssignmentNotification non deve essere istanziata in nessun file
     * del codice applicativo (app/).
     */
    public function test_assignment_notification_has_no_call_sites_in_app_code(): void
    {
        $appDir = app_path();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_ends_with($file->getPathname(), 'Mail/AssignmentNotification.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relPath = str_replace($appDir.DIRECTORY_SEPARATOR, '', $file->getPathname());

            $this->assertStringNotContainsString(
                'new AssignmentNotification(',
                $content,
                "DEAD-MAIL-02: AssignmentNotification è ancora istanziata in app/{$relPath}."
            );

            $this->assertStringNotContainsString(
                'new \\App\\Mail\\AssignmentNotification(',
                $content,
                "DEAD-MAIL-02: AssignmentNotification è ancora istanziata (FQCN) in app/{$relPath}."
            );
        }
    }

    // ====================================================================
    // DISPATCH-01 — La copia conoscenza raggiunge l'arbitro
    // ====================================================================

    public function test_send_to_referee_dispatches_cc_copy(): void
    {
        $tournament = $this->createTournament();
        $referee    = $this->createReferee(['email' => 'arbitro@test.com']);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $notification = $this->makeNotification($tournament->id, false, [$referee->id]);

        app(NotificationService::class)->send($notification);

        $ccCopy = Mail::queued(ClubNotificationMail::class, function ($mail) use ($referee) {
            return $mail->hasTo($referee->email) || $mail->hasCc($referee->email);
        });
        $this->assertTrue(
            $ccCopy->isNotEmpty(),
            'DISPATCH-01: la copia conoscenza deve raggiungere l\'arbitro selezionato.'
        );

        $this->assertTrue(
            Mail::queued(AssignmentNotification::class)->isEmpty(),
            'DISPATCH-01: AssignmentNotification non deve mai essere inviata (è dead code).'
        );
    }

    /**
     * Mail unica senza documenti generati: nessun allegato fantasma.
     */
    public function test_mail_without_documents_has_no_attachments(): void
    {
        $tournament = $this->createTournament();
        $referee    = $this->createReferee(['email' => 'arbitro@test.com']);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $notification = $this->makeNotification($tournament->id, false, [$referee->id]);

        app(NotificationService::class)->send($notification);

        Mail::assertQueued(ClubNotificationMail::class, function ($mail) use ($referee) {
            return ($mail->hasTo($referee->email) || $mail->hasCc($referee->email))
                && empty($mail->attachmentPaths);
        });
    }

    // ====================================================================
    // DISPATCH-02 — La mail circolo parte quando recipients.club = true
    // ====================================================================

    public function test_send_to_club_dispatches_club_notification_mail(): void
    {
        $tournament = $this->createTournament();

        $notification = $this->makeNotification($tournament->id, true, []);

        app(NotificationService::class)->send($notification);

        $this->assertTrue(
            Mail::queued(ClubNotificationMail::class)->isNotEmpty(),
            'DISPATCH-02: ClubNotificationMail deve essere accodata quando recipients.club = true.'
        );
    }
}
