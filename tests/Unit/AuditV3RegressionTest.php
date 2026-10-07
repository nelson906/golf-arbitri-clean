<?php

namespace Tests\Unit;

use App\Enums\AssignmentRole;
use App\Enums\UserType;
use App\Helpers\RefereeLevelsHelper;
use App\Models\Assignment;
use App\Models\TournamentNotification;
use App\Models\User;
use App\Observers\AssignmentObserver;
use App\Services\NotificationRecipientBuilder;
use App\Services\NotificationTransactionService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Regression test suite — Audit v3
 *
 * Un test per ogni fix applicato. Se uno di questi rompe significa che
 * qualcuno ha reintrodotto un bug già corretto.
 *
 * Struttura:
 *   BUG-01  metadata is_national salvato in sendNationalNotification()
 *   BUG-02  isAdmin() copre tutti i tipi admin (la CommunicationPolicy e' stata rimossa)
 *   BUG-03  AssignmentObserver sincronizza referee_list
 *   BUG-04  prepareAndSend() ha rimosso il parametro $data inutilizzato
 *   DUP-02  Funzioni helper globali definite in helpers.php (namespace radice)
 *   DUP-03  NotificationRecipientBuilder usato in entrambi i metodi national
 *   INC-01  Query UserType::NationalAdmin->value invece di stringa hardcoded
 */
class AuditV3RegressionTest extends TestCase
{
    // ====================================================================
    // BUG-01 — metadata['is_national'] salvato da sendNationalNotification
    // ====================================================================

    /**
     * Il campo metadata di TournamentNotification deve essere castato come array
     * e deve stare in $fillable, così resend() può leggere is_national.
     */
    public function test_bug01_metadata_is_fillable_and_cast_as_array(): void
    {
        $notification = new TournamentNotification;

        $this->assertContains('metadata', $notification->getFillable(),
            'BUG-01: metadata deve essere in $fillable per poter salvare is_national.');

        $casts = $notification->getCasts();
        $this->assertArrayHasKey('metadata', $casts,
            'BUG-01: metadata deve avere un cast definito.');
        $this->assertStringContainsString('array', $casts['metadata'],
            'BUG-01: metadata deve essere castato come array o json.');
    }

    /**
     * Un TournamentNotification con metadata['is_national'] = true
     * deve persistere e tornare quel valore dopo fresh().
     */
    public function test_bug01_is_national_flag_persists_in_metadata(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'sent',
            'sent_by'       => $this->createZoneAdmin()->id,
            'metadata'      => [
                'is_national'   => true,
                'type'          => 'crc_referees',
                'subject'       => 'Test Subject',
                'message'       => 'Test Message',
                'success_count' => 2,
                'error_count'   => 0,
            ],
        ]);

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);

        $this->assertIsArray($fresh->metadata,
            'BUG-01: metadata deve essere un array dopo fresh().');
        $this->assertTrue($fresh->metadata['is_national'],
            'BUG-01: is_national deve essere true nel metadata persistito.');
        $this->assertEquals('crc_referees', $fresh->metadata['type'],
            'BUG-01: il tipo notifica deve essere persistito nel metadata.');
    }

    /**
     * Il metodo resend() nel controller usa $metadata['is_national'] per
     * distinguere le notifiche nazionali. Verifichiamo che il modello supporti
     * questo pattern.
     */
    public function test_bug01_resend_detection_pattern_works(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'sent',
            'metadata'      => ['is_national' => true, 'type' => 'zone_observers'],
        ]);

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);

        // Pattern usato da resend() nel controller
        $isNational = $fresh->metadata['is_national'] ?? false;

        $this->assertTrue($isNational,
            'BUG-01: il pattern di rilevamento notifica nazionale deve funzionare.');
    }

    // ====================================================================
    // BUG-02 — isAdmin() copre tutti i tipi admin
    // ====================================================================

    /**
     * Il metodo isAdmin() del modello User deve restituire true per tutti i
     * tipi admin.
     */
    public function test_bug02_is_admin_returns_true_for_all_admin_types(): void
    {
        $this->assertTrue($this->createSuperAdmin()->isAdmin(),
            'BUG-02: SuperAdmin->isAdmin() deve essere true.');
        $this->assertTrue($this->createNationalAdmin()->isAdmin(),
            'BUG-02: NationalAdmin->isAdmin() deve essere true.');
        $this->assertTrue($this->createZoneAdmin()->isAdmin(),
            'BUG-02: ZoneAdmin->isAdmin() deve essere true.');
        $this->assertFalse($this->createReferee()->isAdmin(),
            'BUG-02: Referee->isAdmin() deve essere false.');
    }

    // ====================================================================
    // BUG-03 — AssignmentObserver sincronizza referee_list
    // ====================================================================

    /**
     * L'AssignmentObserver deve essere registrato nell'AppServiceProvider.
     *
     * Nota: Laravel avvolge i callback degli observer in closures anonime,
     * quindi getListeners() non restituisce il nome della classe. Verifichiamo
     * invece il sorgente di AppServiceProvider (approccio usato anche per DUP-05).
     */
    public function test_bug03_assignment_observer_is_registered(): void
    {
        $serviceProviderPath = app_path('Providers/AppServiceProvider.php');
        $this->assertFileExists($serviceProviderPath,
            'BUG-03: AppServiceProvider.php deve esistere.');

        $source = (string) file_get_contents($serviceProviderPath);

        $this->assertStringContainsString('AssignmentObserver', $source,
            'BUG-03: AppServiceProvider deve fare riferimento ad AssignmentObserver.');
        $this->assertStringContainsString('Assignment::observe(', $source,
            'BUG-03: AppServiceProvider deve registrare l\'observer con Assignment::observe().');
    }

    /**
     * Creare un'assegnazione deve aggiornare referee_list nella notifica collegata.
     */
    public function test_bug03_creating_assignment_updates_notification_referee_list(): void
    {
        $tournament   = $this->createTournament();
        $referee      = $this->createReferee();

        // Crea una notifica esistente con referee_list vuota
        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'sent',
            'referee_list'  => '',
            'details'       => ['total_recipients' => 0],
        ]);

        // Crea l'assegnazione — deve scattare l'observer
        // (usa la factory perché assigned_by è NOT NULL senza default)
        $assignment = $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);

        $this->assertStringContainsString($referee->name, (string) $fresh->referee_list,
            'BUG-03: Il nome dell\'arbitro assegnato deve comparire in referee_list dopo la creazione.');
    }

    /**
     * Eliminare un'assegnazione deve rimuovere l'arbitro da referee_list.
     */
    public function test_bug03_deleting_assignment_removes_referee_from_list(): void
    {
        $tournament = $this->createTournament();
        $referee1   = $this->createReferee(['name' => 'Arbitro Uno']);
        $referee2   = $this->createReferee(['name' => 'Arbitro Due']);

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'sent',
            'referee_list'  => '',
        ]);

        $assignment1 = $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee1->id,
            'role'          => AssignmentRole::Referee->value,
        ]);
        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee2->id,
            'role'          => AssignmentRole::TournamentDirector->value,
        ]);

        // Verifica che entrambi siano presenti
        $refereeList = (string) $notification->refresh()->referee_list;
        $this->assertStringContainsString('Arbitro Uno', $refereeList);
        $this->assertStringContainsString('Arbitro Due', $refereeList);

        // Elimina la prima assegnazione
        $assignment1->delete();

        $freshAfterDelete = $notification->fresh();
        $this->assertNotNull($freshAfterDelete);
        $this->assertStringNotContainsString('Arbitro Uno', (string) $freshAfterDelete->referee_list,
            'BUG-03: referee_list non deve contenere l\'arbitro rimosso.');
        $this->assertStringContainsString('Arbitro Due', (string) $freshAfterDelete->referee_list,
            'BUG-03: referee_list deve ancora contenere l\'arbitro rimanente.');
    }

    /**
     * L'observer deve aggiornare total_recipients in details JSON.
     */
    public function test_bug03_observer_updates_total_recipients_in_details(): void
    {
        $tournament = $this->createTournament();
        $referee    = $this->createReferee();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status'        => 'sent',
            'details'       => ['total_recipients' => 0],
        ]);

        $this->createAssignment([
            'tournament_id' => $tournament->id,
            'user_id'       => $referee->id,
            'role'          => AssignmentRole::Referee->value,
        ]);

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh);
        $this->assertIsArray($fresh->details);
        $this->assertArrayHasKey('total_recipients', $fresh->details,
            'BUG-03: details deve contenere total_recipients dopo l\'aggiornamento observer.');
        $this->assertGreaterThan(0, $fresh->details['total_recipients'],
            'BUG-03: total_recipients deve essere maggiore di 0 dopo aver aggiunto un arbitro.');
    }

    // ====================================================================
    // BUG-04 — prepareAndSend() rimosso (audit 2026-06: dead code)
    // ====================================================================

    /**
     * prepareAndSend() era dead code (mai chiamato: il flusso reale è
     * saveAsDraft() + sendWithTransaction()) ed è stato rimosso.
     * Questo test impedisce che venga reintrodotto.
     */
    public function test_bug04_prepare_and_send_removed_as_dead_code(): void
    {
        $rc = new \ReflectionClass(NotificationTransactionService::class);

        $this->assertFalse(
            $rc->hasMethod('prepareAndSend'),
            'BUG-04/DEAD: prepareAndSend() è dead code rimosso — usare saveAsDraft() + sendWithTransaction().'
        );
    }

    // ====================================================================
    // DUP-02 — Funzioni helper globali nel namespace radice (helpers.php)
    // ====================================================================

    /**
     * referee_levels() deve essere una funzione globale (non namespaced).
     */
    public function test_dup02_referee_levels_is_global_function(): void
    {
        $this->assertTrue(function_exists('referee_levels'),
            'DUP-02: referee_levels() deve essere una funzione globale.');

        $levels = referee_levels();
        $this->assertNotEmpty($levels);
        $this->assertArrayHasKey('Aspirante', $levels);
    }

    /**
     * normalize_referee_level() deve essere globale e funzionare correttamente.
     */
    public function test_dup02_normalize_referee_level_is_global_function(): void
    {
        $this->assertTrue(function_exists('normalize_referee_level'),
            'DUP-02: normalize_referee_level() deve essere una funzione globale.');

        $this->assertEquals('Nazionale', normalize_referee_level('naz'));
        $this->assertEquals('Aspirante', normalize_referee_level('asp'));
        $this->assertNull(normalize_referee_level(null));
    }

    /**
     * referee_level_label() deve essere globale e restituire label corrette.
     */
    public function test_dup02_referee_level_label_is_global_function(): void
    {
        $this->assertTrue(function_exists('referee_level_label'),
            'DUP-02: referee_level_label() deve essere una funzione globale.');

        $this->assertEquals('Primo Livello', referee_level_label('1_livello'));
        $this->assertEquals('Nazionale', referee_level_label('Nazionale'));
        $this->assertEquals('Non specificato', referee_level_label(null));
    }

    /**
     * Le funzioni globali devono delegare alla classe RefereeLevelsHelper.
     */
    public function test_dup02_global_functions_delegate_to_helper_class(): void
    {
        // Le due chiamate devono produrre lo stesso risultato
        $this->assertEquals(
            RefereeLevelsHelper::getSelectOptions(),
            referee_levels(),
            'DUP-02: referee_levels() deve delegare a RefereeLevelsHelper::getSelectOptions().'
        );

        $this->assertEquals(
            RefereeLevelsHelper::normalize('reg'),
            normalize_referee_level('reg'),
            'DUP-02: normalize_referee_level() deve delegare a RefereeLevelsHelper::normalize().'
        );
    }

    // ====================================================================
    // DUP-03 — NotificationRecipientBuilder produce output corretto
    // ====================================================================

    /**
     * Il builder deve iniziare vuoto e build() deve segnalarlo come isEmpty.
     */
    public function test_dup03_empty_builder_produces_empty_result(): void
    {
        $result = (new NotificationRecipientBuilder)->build();

        $this->assertEmpty($result['to'],   'DUP-03: to deve essere vuoto.');
        $this->assertEmpty($result['cc'],   'DUP-03: cc deve essere vuoto.');
        $this->assertTrue($result['isEmpty'], 'DUP-03: isEmpty deve essere true su builder vuoto.');
        $this->assertEquals(0, $result['total']);
    }

    /**
     * addCampionati() deve aggiungere l'ufficio campionati in TO (non in CC).
     */
    public function test_dup03_add_campionati_adds_to_to_list(): void
    {
        $result = (new NotificationRecipientBuilder)->addCampionati()->build();

        $this->assertCount(1, $result['to'],
            'DUP-03: addCampionati() deve aggiungere 1 elemento in TO.');
        $this->assertEmpty($result['cc'],
            'DUP-03: addCampionati() non deve aggiungere nulla in CC.');
        $this->assertFalse($result['isEmpty']);
    }

    /**
     * addCrc() deve aggiungere il CRC in CC.
     */
    public function test_dup03_add_crc_adds_to_cc_list(): void
    {
        $result = (new NotificationRecipientBuilder)->addCrc()->build();

        $this->assertEmpty($result['to'],
            'DUP-03: addCrc() non deve aggiungere nulla in TO.');
        $this->assertCount(1, $result['cc'],
            'DUP-03: addCrc() deve aggiungere 1 elemento in CC.');
    }

    /**
     * Il builder non deve aggiungere duplicati nella stessa lista.
     */
    public function test_dup03_builder_deduplicates_recipients(): void
    {
        $result = (new NotificationRecipientBuilder)
            ->addCampionati()
            ->addCampionati() // duplicato
            ->build();

        $this->assertCount(1, $result['to'],
            'DUP-03: Il builder deve deduplicare i destinatari TO.');
    }

    /**
     * allNames deve contenere i nomi di tutti i destinatari (TO + CC).
     */
    public function test_dup03_all_names_contains_to_and_cc_names(): void
    {
        $result = (new NotificationRecipientBuilder)
            ->addCampionati()
            ->addCrc()
            ->build();

        $this->assertCount(2, $result['allNames'],
            'DUP-03: allNames deve contenere 1 TO + 1 CC.');
        $this->assertEquals(2, $result['total']);
    }

    // ====================================================================
    // INC-01 — Query UserType::NationalAdmin->value (no stringa hardcoded)
    // ====================================================================

    /**
     * Il valore DB di NationalAdmin deve essere 'national_admin'.
     * Se questo test rompe, vuol dire che l'enum è cambiato e le migrazioni
     * vanno aggiornate di conseguenza.
     */
    public function test_inc01_national_admin_enum_value_is_national_admin_string(): void
    {
        $this->assertSame(
            'national_admin',
            UserType::NationalAdmin->value,
            'INC-01: UserType::NationalAdmin->value deve essere \'national_admin\'.'
        );
    }

    /**
     * Una query con UserType::NationalAdmin->value deve trovare gli admin nazionali.
     */
    public function test_inc01_query_with_enum_finds_national_admins(): void
    {
        $admin1 = $this->createNationalAdmin(['email' => 'na1@test.com', 'is_active' => true]);
        $admin2 = $this->createNationalAdmin(['email' => 'na2@test.com', 'is_active' => true]);
        $zone   = $this->createZoneAdmin(); // non deve essere incluso

        $found = User::where('user_type', UserType::NationalAdmin->value)
            ->where('is_active', true)
            ->get();

        $emails = $found->pluck('email')->toArray();
        $this->assertContains('na1@test.com', $emails,
            'INC-01: La query con UserType::NationalAdmin->value deve trovare admin1.');
        $this->assertContains('na2@test.com', $emails,
            'INC-01: La query con UserType::NationalAdmin->value deve trovare admin2.');
        $this->assertNotContains($zone->email, $emails,
            'INC-01: La query non deve restituire ZoneAdmin.');
    }

    /**
     * La query enum e la stringa raw devono produrre gli stessi risultati.
     * Questo documenta l'equivalenza e avvisa se il valore DB cambia.
     */
    public function test_inc01_enum_query_equals_raw_string_query(): void
    {
        $this->createNationalAdmin(['is_active' => true]);

        $viaEnum = User::where('user_type', UserType::NationalAdmin->value)
            ->where('is_active', true)
            ->pluck('id')->sort()->values();

        $viaString = User::where('user_type', 'national_admin')
            ->where('is_active', true)
            ->pluck('id')->sort()->values();

        $this->assertEquals($viaEnum, $viaString,
            'INC-01: La query con enum e la query raw devono restituire gli stessi risultati.');
    }

    /**
     * La query di pluck email per NationalAdmin (pattern AvailabilityController)
     * deve funzionare con UserType::NationalAdmin->value.
     */
    public function test_inc01_national_admin_email_pluck_pattern(): void
    {
        $this->createNationalAdmin([
            'email'     => 'natadmin@test.com',
            'is_active' => true,
        ]);

        $emails = User::where('user_type', UserType::NationalAdmin->value)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->toArray();

        $this->assertContains('natadmin@test.com', $emails,
            'INC-01: Il pattern pluck-email deve trovare l\'admin nazionale.');
    }
}
