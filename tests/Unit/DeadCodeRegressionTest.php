<?php

namespace Tests\Unit;

use App\Helpers\RefereeLevelsHelper;
use App\Helpers\SystemOperations;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Admin\TournamentTypeController;
use App\Mail\TournamentNotificationMail;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regressione dead code — Audit v4.
 *
 * Un test per ogni metodo rimosso. Se uno di questi rompe significa
 * che qualcuno ha reintrodotto codice morto verificato come tale.
 *
 * DEAD-01  TournamentController::updateStatus()    — mai registrato come route
 * DEAD-02  TournamentController::getclubsByZone()  — mai registrato come route
 * DEAD-03  Cinque stub protected TournamentController — pattern astratto mai collegato
 * DEAD-04  TournamentNotificationMail::fromMetadata() — mai chiamato
 * DEAD-05  TournamentTypeController::updateOrder()    — mai registrato come route
 * DEAD-06  RefereeLevelsHelper::getDbEnumValues()     — superseded da RefereeLevel enum
 * DEAD-07  SystemOperations: composerInstall/Update, getLatestCommit, gitPull, cleanOldFiles
 */
class DeadCodeRegressionTest extends TestCase
{
    // ====================================================================
    // DEAD-01 — TournamentController::updateStatus()
    // ====================================================================

    /**
     * updateStatus() NON deve esistere in Admin\TournamentController.
     * La route usa changeStatus() — updateStatus era un doppione irraggiungibile.
     */
    public function test_dead01_update_status_not_in_tournament_controller(): void
    {
        $rc = new ReflectionClass(TournamentController::class);

        $this->assertFalse(
            $rc->hasMethod('updateStatus'),
            'DEAD-01: updateStatus() è dead code — la route usa changeStatus(). Non deve esistere.'
        );
    }

    /**
     * Verifica che changeStatus() (il metodo reale, registrato nella route) esista ancora.
     */
    public function test_dead01_change_status_still_exists(): void
    {
        $rc = new ReflectionClass(TournamentController::class);

        $this->assertTrue(
            $rc->hasMethod('changeStatus'),
            'DEAD-01: changeStatus() è il metodo di route reale e non deve essere rimosso.'
        );
    }

    // ====================================================================
    // DEAD-02 — TournamentController::getclubsByZone()
    // ====================================================================

    /**
     * getclubsByZone() NON deve esistere — mai registrato in nessuna route.
     */
    public function test_dead02_get_clubs_by_zone_not_in_tournament_controller(): void
    {
        $rc = new ReflectionClass(TournamentController::class);

        $this->assertFalse(
            $rc->hasMethod('getclubsByZone'),
            'DEAD-02: getclubsByZone() è dead code — mai registrato come route AJAX. Non deve esistere.'
        );
    }

    // ====================================================================
    // DEAD-03 — Cinque stub protected in TournamentController
    // ====================================================================

    /**
     * I cinque metodi protected stub NON devono esistere.
     * Erano implementazioni di un pattern astratto (TournamentControllerTrait)
     * che il trait non ha mai chiamato.
     */
    public function test_dead03_protected_stubs_do_not_exist(): void
    {
        $rc = new ReflectionClass(TournamentController::class);

        $stubs = ['getEntityName', 'getIndexRoute', 'getDeleteErrorMessage', 'canBeDeleted', 'checkAccess'];

        foreach ($stubs as $stub) {
            $this->assertFalse(
                $rc->hasMethod($stub),
                "DEAD-03: {$stub}() è uno stub mai collegato al trait. Non deve esistere."
            );
        }
    }

    // ====================================================================
    // DEAD-04 — TournamentNotificationMail::fromMetadata()
    // ====================================================================

    /**
     * TournamentNotificationMail::fromMetadata() NON deve esistere.
     * Nessun controller o service la chiama — il flusso resend usa un percorso diverso.
     */
    public function test_dead04_from_metadata_not_in_tournament_notification_mail(): void
    {
        $rc = new ReflectionClass(TournamentNotificationMail::class);

        $this->assertFalse(
            $rc->hasMethod('fromMetadata'),
            'DEAD-04: fromMetadata() è dead code — nessun call site nel codice produzione.'
        );
    }

    // ====================================================================
    // DEAD-05 — TournamentTypeController::updateOrder()
    // ====================================================================

    /**
     * updateOrder() NON deve esistere in TournamentTypeController.
     * Nessuna route è registrata per questo metodo.
     */
    public function test_dead05_update_order_not_in_tournament_type_controller(): void
    {
        $rc = new ReflectionClass(TournamentTypeController::class);

        $this->assertFalse(
            $rc->hasMethod('updateOrder'),
            'DEAD-05: updateOrder() è dead code — nessuna route lo raggiunge. Non deve esistere.'
        );
    }

    // ====================================================================
    // DEAD-06 — RefereeLevelsHelper::getDbEnumValues()
    // ====================================================================

    /**
     * getDbEnumValues() NON deve esistere in RefereeLevelsHelper.
     * Superseduto da RefereeLevel::selectOptions(true) — nessun call site rimasto.
     */
    public function test_dead06_get_db_enum_values_not_in_helper(): void
    {
        $rc = new ReflectionClass(RefereeLevelsHelper::class);

        $this->assertFalse(
            $rc->hasMethod('getDbEnumValues'),
            'DEAD-06: getDbEnumValues() è dead code — usare RefereeLevel::selectOptions(true).'
        );
    }

    // ====================================================================
    // DEAD-07 — SystemOperations: cinque metodi inutilizzati
    // ====================================================================

    /**
     * I cinque metodi inutilizzati NON devono esistere in SystemOperations.
     */
    public function test_dead07_unused_system_operations_do_not_exist(): void
    {
        $rc = new ReflectionClass(SystemOperations::class);

        $deadMethods = [
            'composerInstall',
            'composerUpdate',
            'getLatestCommit',
            'gitPull',
            'cleanOldFiles',
        ];

        foreach ($deadMethods as $method) {
            $this->assertFalse(
                $rc->hasMethod($method),
                "DEAD-07: SystemOperations::{$method}() è dead code — nessun call site nel progetto."
            );
        }
    }
}
