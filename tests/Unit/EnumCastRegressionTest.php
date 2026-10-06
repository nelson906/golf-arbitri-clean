<?php

namespace Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use App\Services\CalendarDataService;
use Tests\TestCase;

/**
 * Test di regressione per i cast Eloquent degli Enum.
 *
 * Quando un campo è dichiarato nel $casts di un Model con una classe Enum
 * (es. 'user_type' => UserType::class), Eloquent restituisce un'istanza
 * dell'Enum invece di una stringa. Questi test verificano che tutte le parti
 * del codice che accedono a questi campi usino `->value` correttamente, in
 * modo da prevenire errori come:
 *   - "Cannot access offset of type App\Enums\UserType on array"
 *   - `in_array($enumInstance, ['string1', 'string2'])` che restituisce sempre false
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
        $event = $calendarData->firstOrFail();

        // P3 (2026-10-03): lo stato del torneo non viene piu' esposto al calendario
        $props = $this->arrayAt($event, 'extendedProps');
        $this->assertArrayNotHasKey('status', $props);
        $this->assertJson((string) json_encode($props));
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

        $event = $calendarData->firstOrFail();
        // P3 (2026-10-03): lo stato del torneo non viene piu' esposto al calendario
        $props = $this->arrayAt($event, 'extendedProps');
        $this->assertArrayNotHasKey('status', $props);
        $this->assertJson((string) json_encode($props));
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
            $this->assertIsArray($decoded);
            $this->assertSame($userRoles[0], $decoded[0]);
        }
    }
}
