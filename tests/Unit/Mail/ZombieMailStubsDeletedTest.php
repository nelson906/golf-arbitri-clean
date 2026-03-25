<?php

namespace Tests\Unit\Mail;

use Tests\TestCase;

/**
 * Regression test per FIX C-1 (originale) e Audit v4 (aggiornamento).
 *
 * Verifica che le classi Mail zombie siano state eliminate.
 * Se una di queste classi venisse ricreata con view: 'view.name',
 * Laravel lancerebbe InvalidArgumentException in produzione.
 *
 * Zombie aggiunte in Audit v4:
 *   AssignmentNotification — mai istanziata in produzione; sostituita da
 *   RefereeAssignmentMail in tutti i call site del NotificationService.
 *
 * NOTA: il test usa file_exists() invece di class_exists() per evitare
 * che l'autoloader Composer (classmap non ancora rigenerata) tenti di
 * includere il file eliminato e lanci ErrorException. Dopo aver eliminato
 * i file è necessario eseguire `composer dump-autoload` per aggiornare
 * la classmap — finché non viene fatto, class_exists() causa un errore.
 * file_exists() è la verifica corretta per questo tipo di regressione.
 *
 * Eseguire con: php artisan test --filter=ZombieMailStubsDeletedTest
 */
class ZombieMailStubsDeletedTest extends TestCase
{
    /**
     * NationalAvailabilityNotification deve essere eliminata (file assente).
     */
    public function test_national_availability_notification_file_does_not_exist(): void
    {
        $this->assertFalse(
            file_exists(app_path('Mail/NationalAvailabilityNotification.php')),
            'Il file NationalAvailabilityNotification.php deve essere eliminato: ' .
            'aveva view: "view.name" (inesistente) e crashava in produzione.'
        );
    }

    /**
     * RefereeAvailabilityConfirmation deve essere eliminata (file assente).
     */
    public function test_referee_availability_confirmation_file_does_not_exist(): void
    {
        $this->assertFalse(
            file_exists(app_path('Mail/RefereeAvailabilityConfirmation.php')),
            'Il file RefereeAvailabilityConfirmation.php deve essere eliminato: ' .
            'aveva view: "view.name" (inesistente) e crashava in produzione.'
        );
    }

    /**
     * ZonalAvailabilityNotification deve essere eliminata (file assente).
     */
    public function test_zonal_availability_notification_file_does_not_exist(): void
    {
        $this->assertFalse(
            file_exists(app_path('Mail/ZonalAvailabilityNotification.php')),
            'Il file ZonalAvailabilityNotification.php deve essere eliminato: ' .
            'aveva view: "view.name" (inesistente) e crashava in produzione.'
        );
    }

    /**
     * Le classi Mail ancora in uso devono esistere.
     * Questo test previene rimozioni accidentali di classi attive.
     *
     * NB: AssignmentNotification rimossa in Audit v4 (era dead code —
     * mai istanziata). Il suo sostituto è RefereeAssignmentMail.
     */
    public function test_active_mail_classes_still_exist(): void
    {
        $activeClasses = [
            \App\Mail\BatchAvailabilityAdminNotification::class,
            \App\Mail\BatchAvailabilityNotification::class,
            \App\Mail\TournamentNotificationMail::class,
            \App\Mail\ClubNotificationMail::class,
            \App\Mail\RefereeAssignmentMail::class,
            \App\Mail\InstitutionalNotificationMail::class,
        ];

        foreach ($activeClasses as $class) {
            $this->assertTrue(
                class_exists($class),
                "La classe Mail attiva {$class} non deve essere eliminata."
            );
        }
    }

    /**
     * AssignmentNotification deve essere eliminata (Audit v4).
     *
     * Era dead code: mai istanziata in produzione.
     * Il NotificationService usa RefereeAssignmentMail come unico canale
     * per le notifiche agli arbitri.
     */
    public function test_assignment_notification_file_does_not_exist(): void
    {
        $this->assertFalse(
            file_exists(app_path('Mail/AssignmentNotification.php')),
            'Il file AssignmentNotification.php deve essere eliminato: era dead code, '
            . 'mai istanziato — il NotificationService usa RefereeAssignmentMail.'
        );
    }

    /**
     * Nessuna classe Mail deve puntare alla view 'view.name' (placeholder di artisan).
     *
     * Questo test scansiona tutte le classi Mail presenti nella directory
     * e fallisce se una di esse usa ancora il placeholder di default.
     */
    public function test_no_mail_class_uses_view_name_placeholder(): void
    {
        $mailDir = app_path('Mail');
        $files = glob("{$mailDir}/*.php");

        $offenders = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, "view: 'view.name'")) {
                $offenders[] = basename($file);
            }
        }

        $this->assertEmpty(
            $offenders,
            'Le seguenti classi Mail usano ancora il placeholder "view.name": ' .
            implode(', ', $offenders)
        );
    }
}
