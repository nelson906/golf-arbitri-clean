<?php

namespace Tests\Unit;

use App\Enums\TournamentStatus;
use App\Enums\UserType;
use App\Models\Tournament;
use App\Models\User;
use App\Services\CalendarDataService;
use App\Services\TournamentColorService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Test di regressione per i cast Eloquent degli Enum.
 *
 * Quando un campo è dichiarato nel $casts di un Model con una classe Enum
 * (es. 'status' => TournamentStatus::class), Eloquent restituisce un'istanza
 * dell'Enum invece di una stringa. Questi test verificano che tutte le parti
 * del codice che accedono a questi campi usino `->value` correttamente, in
 * modo da prevenire errori come:
 *   - "Cannot access offset of type App\Enums\TournamentStatus on array"
 *   - "Cannot access offset of type App\Enums\UserType on array"
 *   - `in_array($enumInstance, ['string1', 'string2'])` che restituisce sempre false
 *   - `->groupBy('status')` che genera chiavi Enum invece di chiavi stringa
 */
class EnumCastRegressionTest extends TestCase
{
    // ============================================================
    // SEZIONE 1 — UserType: il cast restituisce istanza Enum
    // ============================================================

    /**
     * Il campo user_type deve essere castato a istanza UserType, non a stringa.
     */
    public function test_user_type_cast_returns_enum_instance(): void
    {
        $referee       = $this->createReferee();
        $zoneAdmin     = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();
        $superAdmin    = $this->createSuperAdmin();

        $this->assertInstanceOf(UserType::class, $referee->user_type);
        $this->assertInstanceOf(UserType::class, $zoneAdmin->user_type);
        $this->assertInstanceOf(UserType::class, $nationalAdmin->user_type);
        $this->assertInstanceOf(UserType::class, $superAdmin->user_type);
    }

    /**
     * Il valore stringa si ottiene tramite ->value, non confrontando l'istanza.
     * Questo è il pattern corretto per tutti i confronti nel codice.
     */
    public function test_user_type_value_returns_correct_string(): void
    {
        $referee       = $this->createReferee();
        $zoneAdmin     = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();
        $superAdmin    = $this->createSuperAdmin();

        $this->assertSame('referee',       $referee->user_type->value);
        $this->assertSame('admin',         $zoneAdmin->user_type->value);
        $this->assertSame('national_admin', $nationalAdmin->user_type->value);
        $this->assertSame('super_admin',   $superAdmin->user_type->value);
    }

    /**
     * Regressione: usare user_type come chiave array senza ->value causa l'errore
     * "Cannot access offset of type UserType on array".
     * Con ->value l'accesso funziona correttamente.
     */
    public function test_user_type_value_can_be_used_as_array_key(): void
    {
        $typeColors = [
            'referee'       => 'bg-green-100',
            'admin'         => 'bg-blue-100',
            'national_admin' => 'bg-purple-100',
            'super_admin'   => 'bg-red-100',
        ];
        $typeLabels = [
            'referee'       => 'Arbitro',
            'admin'         => 'Admin Zona',
            'national_admin' => 'Admin Nazionale',
            'super_admin'   => 'Super Admin',
        ];

        foreach ([
            $this->createReferee(),
            $this->createZoneAdmin(),
            $this->createNationalAdmin(),
            $this->createSuperAdmin(),
        ] as $user) {
            // Questo deve funzionare senza eccezioni (era il bug su admin/users/index.blade.php:215)
            $color = $typeColors[$user->user_type->value] ?? 'bg-gray-100';
            $label = $typeLabels[$user->user_type->value] ?? $user->user_type->value;

            $this->assertIsString($color);
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }

    // ============================================================
    // SEZIONE 2 — UserType: metodi helper del modello User
    // ============================================================

    /**
     * Regressione: i metodi isAdmin/isReferee/ecc. devono usare i metodi dell'Enum
     * invece di confrontare la stringa.  Se qualcuno ripristinasse i vecchi confronti
     * (=== 'admin'), questi test fallirebbero.
     */
    public function test_user_is_admin_methods_are_consistent_with_enum(): void
    {
        $referee       = $this->createReferee();
        $zoneAdmin     = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();
        $superAdmin    = $this->createSuperAdmin();

        // isAdmin() deve includere tutti i tipi admin
        $this->assertFalse($referee->isAdmin());
        $this->assertTrue($zoneAdmin->isAdmin());
        $this->assertTrue($nationalAdmin->isAdmin());
        $this->assertTrue($superAdmin->isAdmin());

        // isReferee() — solo i referee
        $this->assertTrue($referee->isReferee());
        $this->assertFalse($zoneAdmin->isReferee());
        $this->assertFalse($nationalAdmin->isReferee());
        $this->assertFalse($superAdmin->isReferee());

        // isSuperAdmin() — solo i super admin
        $this->assertFalse($referee->isSuperAdmin());
        $this->assertFalse($zoneAdmin->isSuperAdmin());
        $this->assertFalse($nationalAdmin->isSuperAdmin());
        $this->assertTrue($superAdmin->isSuperAdmin());

        // isNationalAdmin() — delega a UserType::isNational() che include
        // NationalAdmin E SuperAdmin (entrambi hanno visibilità nazionale).
        $this->assertFalse($referee->isNationalAdmin());
        $this->assertFalse($zoneAdmin->isNationalAdmin());
        $this->assertTrue($nationalAdmin->isNationalAdmin());
        $this->assertTrue($superAdmin->isNationalAdmin(),
            'Il SuperAdmin ha visibilità nazionale quindi isNationalAdmin() deve essere true');

        // isZoneAdmin() — solo i zone admin (UserType::ZoneAdmin = 'admin')
        $this->assertFalse($referee->isZoneAdmin());
        $this->assertTrue($zoneAdmin->isZoneAdmin());
        $this->assertFalse($nationalAdmin->isZoneAdmin());
        $this->assertFalse($superAdmin->isZoneAdmin());
    }

    /**
     * Regressione: hasRole('zone_admin') deve ritornare TRUE solo per ZoneAdmin,
     * non per tutti gli admin. Il bug era che 'zone_admin' era mappato a isAdmin().
     */
    public function test_has_role_zone_admin_is_exclusive_to_zone_admin(): void
    {
        $zoneAdmin     = $this->createZoneAdmin();
        $nationalAdmin = $this->createNationalAdmin();
        $superAdmin    = $this->createSuperAdmin();
        $referee       = $this->createReferee();

        $this->assertTrue($zoneAdmin->hasRole('zone_admin'),
            'Lo ZoneAdmin deve passare hasRole("zone_admin")');
        $this->assertFalse($nationalAdmin->hasRole('zone_admin'),
            'Il NationalAdmin NON deve passare hasRole("zone_admin")');
        $this->assertFalse($superAdmin->hasRole('zone_admin'),
            'Il SuperAdmin NON deve passare hasRole("zone_admin")');
        $this->assertFalse($referee->hasRole('zone_admin'),
            'Il Referee NON deve passare hasRole("zone_admin")');
    }

    /**
     * hasRole('admin') deve coprire tutti i tipi admin (alias generico).
     */
    public function test_has_role_admin_covers_all_admin_types(): void
    {
        $this->assertTrue($this->createZoneAdmin()->hasRole('admin'));
        $this->assertTrue($this->createNationalAdmin()->hasRole('admin'));
        $this->assertTrue($this->createSuperAdmin()->hasRole('admin'));
        $this->assertFalse($this->createReferee()->hasRole('admin'));
    }

    /**
     * hasRole per i singoli ruoli specifici deve essere esclusivo.
     */
    public function test_has_role_specificity(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $referee    = $this->createReferee();

        $this->assertTrue($superAdmin->hasRole('super_admin'));
        $this->assertFalse($referee->hasRole('super_admin'));

        $this->assertTrue($referee->hasRole('referee'));
        $this->assertFalse($superAdmin->hasRole('referee'));
    }

    // ============================================================
    // SEZIONE 3 — TournamentStatus: il cast restituisce istanza Enum
    // ============================================================

    /**
     * Il campo status deve essere castato a istanza TournamentStatus, non a stringa.
     */
    public function test_tournament_status_cast_returns_enum_instance(): void
    {
        $tournament = $this->createTournament(['status' => 'open']);

        $this->assertInstanceOf(TournamentStatus::class, $tournament->status);
    }

    /**
     * Il valore stringa si ottiene tramite ->value.
     */
    public function test_tournament_status_value_returns_correct_string(): void
    {
        foreach (TournamentStatus::cases() as $case) {
            $tournament = $this->createTournament(['status' => $case->value]);

            $this->assertSame($case->value, $tournament->status->value,
                "Lo status '{$case->value}' deve avere ->value === '{$case->value}'");
        }
    }

    /**
     * Regressione: accesso a STATUS_COLORS tramite ->value non deve lanciare
     * "Cannot access offset of type TournamentStatus on array".
     */
    public function test_tournament_status_value_can_be_used_as_array_key(): void
    {
        $statusColors = [
            'draft'     => '#gray',
            'open'      => '#green',
            'closed'    => '#yellow',
            'assigned'  => '#blue',
            'completed' => '#purple',
            'cancelled' => '#red',
        ];

        foreach (TournamentStatus::cases() as $case) {
            $tournament = $this->createTournament(['status' => $case->value]);

            // Questo non deve lanciare eccezioni (era il bug su TournamentColorService:107)
            $color = $statusColors[$tournament->status->value] ?? '#default';

            $this->assertIsString($color);
            $this->assertNotEmpty($color);
        }
    }

    // ============================================================
    // SEZIONE 4 — TournamentColorService
    // ============================================================

    /**
     * Regressione: getAdminBorderColor() deve restituire una stringa colore
     * senza lanciare "Cannot access offset of type TournamentStatus on array".
     */
    public function test_tournament_color_service_border_color_works_for_all_statuses(): void
    {
        $colorService = app(TournamentColorService::class);

        foreach (TournamentStatus::cases() as $case) {
            $tournament = $this->createTournament(['status' => $case->value]);

            // Non deve lanciare eccezioni
            $borderColor = $colorService->getAdminBorderColor($tournament);

            $this->assertIsString($borderColor,
                "getAdminBorderColor() deve restituire una stringa per status '{$case->value}'");
            $this->assertStringStartsWith('#', $borderColor,
                "Il colore deve essere un valore hex per status '{$case->value}'");
        }
    }

    // ============================================================
    // SEZIONE 5 — Collection::groupBy con enum cast
    // ============================================================

    /**
     * Regressione: groupBy('status') su una collection con enum-cast produce chiavi
     * Enum e non chiavi stringa, causando che ->get('open') non trovi nulla.
     * La soluzione è usare groupBy(fn($t) => $t->status->value).
     */
    public function test_collection_group_by_with_enum_requires_value(): void
    {
        $open      = $this->createTournament(['status' => 'open']);
        $closed    = $this->createTournament(['status' => 'closed']);
        $draft     = $this->createTournament(['status' => 'draft']);

        $collection = collect([$open, $closed, $draft]);

        // ❌ Questo è il pattern rotto (groupBy per nome campo con enum-cast)
        // $byStatusBroken = $collection->groupBy('status');
        // $byStatusBroken->get('open') restituisce null perché la chiave è un'istanza Enum

        // ✅ Pattern corretto
        $byStatus = $collection->groupBy(fn ($t) => $t->status->value);

        $this->assertCount(1, $byStatus->get('open', collect()),
            'groupBy con ->value deve trovare il torneo "open"');
        $this->assertCount(1, $byStatus->get('closed', collect()),
            'groupBy con ->value deve trovare il torneo "closed"');
        $this->assertCount(1, $byStatus->get('draft', collect()),
            'groupBy con ->value deve trovare il torneo "draft"');
        $this->assertNull($byStatus->get('completed'),
            'Non ci sono tornei completati in questo test');
    }

    /**
     * Regressione: calculateTournamentStats conta correttamente per status.
     * Verifica che la logica del TournamentControllerTrait (groupBy + get) funzioni.
     */
    public function test_tournament_stats_count_by_status(): void
    {
        // Crea tornei con status diversi
        $this->createTournament(['status' => 'open']);
        $this->createTournament(['status' => 'open']);
        $this->createTournament(['status' => 'draft']);
        $this->createTournament(['status' => 'completed']);

        $collection = Tournament::all();

        // Replica la logica di TournamentControllerTrait::calculateTournamentStats()
        $byStatus = $collection->groupBy(fn ($t) => $t->status->value);

        $stats = [
            'total'     => $collection->count(),
            'draft'     => $byStatus->get('draft',     collect())->count(),
            'open'      => $byStatus->get('open',      collect())->count(),
            'closed'    => $byStatus->get('closed',    collect())->count(),
            'assigned'  => $byStatus->get('assigned',  collect())->count(),
            'completed' => $byStatus->get('completed', collect())->count(),
        ];

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['open'],      'Devono esserci 2 tornei open');
        $this->assertEquals(1, $stats['draft'],     'Deve esserci 1 torneo draft');
        $this->assertEquals(1, $stats['completed'], 'Deve esserci 1 torneo completed');
        $this->assertEquals(0, $stats['closed'],    'Non ci devono essere tornei closed');
        $this->assertEquals(0, $stats['assigned'],  'Non ci devono essere tornei assigned');
    }

    // ============================================================
    // SEZIONE 6 — CalendarDataService: serializzazione come stringa
    // ============================================================

    /**
     * Regressione: prepareFullCalendarData() deve serializzare userType come stringa,
     * non come istanza Enum (che non è JSON-serializzabile nativamente).
     */
    public function test_calendar_data_service_serializes_user_type_as_string(): void
    {
        $calendarService = app(CalendarDataService::class);
        $admin           = $this->createZoneAdmin();
        $tournaments     = collect([]);

        $data = $calendarService->prepareFullCalendarData(
            $tournaments,
            $admin,
            'admin',
            [
                'zones'           => collect([]),
                'clubs'           => collect([]),
                'tournamentTypes' => collect([]),
            ]
        );

        $this->assertArrayHasKey('userType', $data);
        $this->assertIsString($data['userType'],
            "userType deve essere una stringa, non un'istanza Enum");
        $this->assertSame('admin', $data['userType'],
            "Lo ZoneAdmin deve avere userType === 'admin'");
    }

    /**
     * Regressione: prepareAdminCalendarData() deve serializzare status come stringa
     * in extendedProps, non come istanza Enum.
     */
    public function test_calendar_data_service_admin_serializes_status_as_string(): void
    {
        $calendarService = app(CalendarDataService::class);
        $tournament      = $this->createTournament(['status' => 'open']);
        $tournaments     = collect([$tournament]);

        // Non deve lanciare eccezioni
        $calendarData = $calendarService->prepareAdminCalendarData($tournaments);

        $this->assertCount(1, $calendarData);
        $event = $calendarData->first();

        $this->assertArrayHasKey('extendedProps', $event);
        $this->assertArrayHasKey('status', $event['extendedProps']);
        $this->assertIsString($event['extendedProps']['status'],
            "extendedProps.status deve essere una stringa, non un'istanza Enum");
        $this->assertSame('open', $event['extendedProps']['status']);
    }

    /**
     * Regressione: prepareRefereeCalendarData() deve serializzare status come stringa.
     */
    public function test_calendar_data_service_referee_serializes_status_as_string(): void
    {
        $calendarService = app(CalendarDataService::class);
        $referee         = $this->createReferee();
        $tournament      = $this->createTournament(['status' => 'closed']);
        $tournaments     = collect([$tournament]);

        $calendarData = $calendarService->prepareRefereeCalendarData($tournaments, $referee);

        $event = $calendarData->first();
        $this->assertIsString($event['extendedProps']['status'],
            "extendedProps.status deve essere una stringa per la vista arbitro");
        $this->assertSame('closed', $event['extendedProps']['status']);
    }

    // ============================================================
    // SEZIONE 7 — UserType: serializzazione in array / JSON
    // ============================================================

    /**
     * Regressione: $calendarData['userRoles'] = [$user->user_type] mette un'istanza Enum
     * nell'array. Con ->value si ottiene una stringa serializzabile.
     */
    public function test_user_roles_array_contains_strings_not_enum_instances(): void
    {
        foreach ([
            $this->createReferee(),
            $this->createZoneAdmin(),
            $this->createNationalAdmin(),
            $this->createSuperAdmin(),
        ] as $user) {
            // Pattern corretto (TournamentControllerTrait)
            $userRoles = [$user->user_type->value];

            $this->assertIsString($userRoles[0],
                "userRoles deve contenere stringhe, non istanze Enum");
            $this->assertNotEmpty($userRoles[0]);

            // Deve essere JSON-serializzabile senza eccezioni
            $json = json_encode($userRoles);
            $this->assertIsString($json);
            $decoded = json_decode($json, true);
            $this->assertSame($userRoles[0], $decoded[0]);
        }
    }

    // ============================================================
    // SEZIONE 8 — TournamentStatus: metodi dell'Enum
    // ============================================================

    /**
     * I metodi dell'Enum TournamentStatus devono funzionare correttamente.
     */
    public function test_tournament_status_enum_methods(): void
    {
        $this->assertTrue(TournamentStatus::Open->isActive());
        $this->assertTrue(TournamentStatus::Closed->isActive());
        $this->assertTrue(TournamentStatus::Assigned->isActive());
        $this->assertFalse(TournamentStatus::Draft->isActive());
        $this->assertFalse(TournamentStatus::Completed->isActive());
        $this->assertFalse(TournamentStatus::Cancelled->isActive());

        $this->assertTrue(TournamentStatus::Draft->isEditable());
        $this->assertTrue(TournamentStatus::Open->isEditable());
        $this->assertFalse(TournamentStatus::Completed->isEditable());
        $this->assertFalse(TournamentStatus::Cancelled->isEditable());
    }

    /**
     * TournamentStatus::activeValues() deve restituire un array di stringhe, non di Enum.
     */
    public function test_tournament_status_active_values_returns_strings(): void
    {
        $activeValues = TournamentStatus::activeValues();

        $this->assertIsArray($activeValues);
        $this->assertNotEmpty($activeValues);

        foreach ($activeValues as $value) {
            $this->assertIsString($value,
                'activeValues() deve restituire stringhe, non istanze Enum');
        }

        $this->assertContains('open',     $activeValues);
        $this->assertContains('closed',   $activeValues);
        $this->assertContains('assigned', $activeValues);
        $this->assertNotContains('draft',     $activeValues);
        $this->assertNotContains('completed', $activeValues);
    }

    /**
     * Regressione: colorClass() deve restituire una stringa CSS senza eccezioni.
     * Verifica l'integrità dei metodi dell'Enum dopo le modifiche.
     */
    public function test_tournament_status_color_class_returns_string(): void
    {
        foreach (TournamentStatus::cases() as $case) {
            $colorClass = $case->colorClass();
            $this->assertIsString($colorClass,
                "colorClass() deve essere una stringa per status '{$case->value}'");
            $this->assertNotEmpty($colorClass);
        }
    }
}
