<?php

namespace Tests\Unit\Mail;

use App\Enums\AssignmentRole;
use App\Mail\AssignmentNotification;
use App\Mail\ClubNotificationMail;
use App\Mail\RefereeAssignmentMail;
use App\Models\TournamentNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Regressione per la pulizia del layer Mail (Audit v4).
 *
 * Verifiche:
 *  DEAD-MAIL-02  AssignmentNotification non ha call site nel codice app/
 *  DISPATCH-01   NotificationService::send() usa RefereeAssignmentMail per gli arbitri
 *  DISPATCH-02   NotificationService::send() usa ClubNotificationMail per il circolo
 *
 * I test DISPATCH-* coprono anche il comportamento atteso dopo la rimozione
 * di AssignmentNotification: il sistema continua a funzionare correttamente.
 */
class MailDispatchRegressionTest extends TestCase
{
    // ====================================================================
    // DEAD-MAIL-02 — AssignmentNotification non è usata nel codice app/
    // ====================================================================

    /**
     * AssignmentNotification non deve essere istanziata in nessun file
     * del codice applicativo (app/). L'unico file che può citarla è la
     * definizione della classe stessa.
     *
     * Se questo test rompe significa che qualcuno ha reintrodotto un
     * call site per la classe obsoleta.
     */
    public function test_assignment_notification_has_no_call_sites_in_app_code(): void
    {
        $appDir = app_path();

        // Raccoglie tutti i file PHP in app/ tranne la definizione della classe
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // Salta la definizione della classe stessa
            if (str_ends_with($file->getPathname(), 'Mail/AssignmentNotification.php')) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relPath = str_replace($appDir . DIRECTORY_SEPARATOR, '', $file->getPathname());

            $this->assertStringNotContainsString(
                'new AssignmentNotification(',
                $content,
                "DEAD-MAIL-02: AssignmentNotification è ancora istanziata in app/{$relPath}. "
                . 'Usare RefereeAssignmentMail al suo posto.'
            );

            $this->assertStringNotContainsString(
                'new \\App\\Mail\\AssignmentNotification(',
                $content,
                "DEAD-MAIL-02: AssignmentNotification è ancora istanziata (FQCN) in app/{$relPath}."
            );
        }
    }

    // ====================================================================
    // DISPATCH-01 — NotificationService usa RefereeAssignmentMail
    // ====================================================================

    /**
     * NotificationService::send() deve usare RefereeAssignmentMail quando
     * i destinatari includono un arbitro. Non deve mai usare AssignmentNotification.
     */
    public function test_send_to_referee_dispatches_referee_assignment_mail(): void
    {
        Mail::fake();

        $tournament = $this->createTournament();
        $referee    = $this->createReferee();
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'pending',
            'metadata'      => ['type' => 'zone_referees'],
            'recipients'    => [
                'club'          => false,
                'referees'      => [$referee->id],
                'institutional' => [],
            ],
        ]);

        app(NotificationService::class)->send($notification);

        // Mail::assertSent() accetta come secondo arg solo callable|int|null — non una stringa.
        // Usiamo assertTrue con Mail::sent() per poter allegare messaggi di errore descrittivi.
        $this->assertTrue(
            Mail::sent(RefereeAssignmentMail::class)->isNotEmpty(),
            'DISPATCH-01: RefereeAssignmentMail deve essere inviata per le notifiche arbitri.'
        );
        $this->assertTrue(
            Mail::sent(AssignmentNotification::class)->isEmpty(),
            'DISPATCH-01: AssignmentNotification non deve mai essere inviata (è dead code).'
        );
    }

    /**
     * RefereeAssignmentMail deve essere inviata all'indirizzo email dell'arbitro.
     */
    public function test_referee_assignment_mail_sent_to_referee_email(): void
    {
        Mail::fake();

        $tournament = $this->createTournament();
        $referee    = $this->createReferee(['email' => 'arbitro@test.com']);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'pending',
            'metadata'      => ['type' => 'zone_referees'],
            'recipients'    => [
                'club'          => false,
                'referees'      => [$referee->id],
                'institutional' => [],
            ],
        ]);

        app(NotificationService::class)->send($notification);

        // assertSent() con callable non accetta un terzo parametro messaggio.
        $sentToReferee = Mail::sent(RefereeAssignmentMail::class, function ($mail) use ($referee) {
            return $mail->hasTo($referee->email);
        });
        $this->assertTrue(
            $sentToReferee->isNotEmpty(),
            "DISPATCH-01: La mail deve essere inviata all'indirizzo email dell'arbitro ({$referee->email})."
        );
    }

    // ====================================================================
    // DISPATCH-02 — NotificationService usa ClubNotificationMail per il circolo
    // ====================================================================

    /**
     * Quando recipients['club'] = true, deve essere inviata ClubNotificationMail.
     */
    public function test_send_to_club_dispatches_club_notification_mail(): void
    {
        Mail::fake();

        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'pending',
            'metadata'      => ['type' => 'zone_referees'],
            'recipients'    => [
                'club'          => true,
                'referees'      => [],
                'institutional' => [],
            ],
        ]);

        app(NotificationService::class)->send($notification);

        $this->assertTrue(
            Mail::sent(ClubNotificationMail::class)->isNotEmpty(),
            'DISPATCH-02: ClubNotificationMail deve essere inviata quando recipients.club = true.'
        );
    }
}
