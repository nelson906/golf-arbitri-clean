<?php

namespace Tests;

use App\Models\Assignment;
use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Setup eseguito prima di ogni test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Verifica che stiamo usando il database di test
        $this->assertDatabaseIsTest();

        // Fake mail per tutti i test
        Mail::fake();

        // Seed dati base se necessari
        $this->seedBaseData();
    }

    /**
     * Verifica che il database usato sia quello di test
     * Previene la distruzione accidentale del database di sviluppo
     */
    protected function assertDatabaseIsTest(): void
    {
        $connection = config('database.default');

        // Se non è SQLite in memoria, verifica che sia un database di test
        if ($connection !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $dbName = config("database.connections.{$connection}.database");

            // Il database deve contenere 'test' nel nome o essere :memory:
            $this->assertTrue(
                str_contains(strtolower($dbName), 'test') || $dbName === ':memory:',
                "⚠️ ATTENZIONE: Stai usando il database '{$dbName}' per i test! Usa un database di test separato."
            );
        }
    }

    /**
     * Seed dati base per i test
     */
    protected function seedBaseData(): void
    {
        // Zone
        if (Zone::count() === 0) {
            $this->seedZones();
        }

        // Tournament Types
        if (TournamentType::count() === 0) {
            $this->seedTournamentTypes();
        }
    }

    /**
     * Crea zone territoriali
     */
    protected function seedZones(): void
    {
        $zones = [
            ['code' => 'SZR1', 'name' => 'Zona 1 - Nord Ovest'],
            ['code' => 'SZR2', 'name' => 'Zona 2 - Nord Est'],
            ['code' => 'SZR3', 'name' => 'Zona 3 - Centro'],
            ['code' => 'SZR4', 'name' => 'Zona 4 - Sud'],
            ['code' => 'SZR5', 'name' => 'Zona 5 - Isole'],
            ['code' => 'SZR6', 'name' => 'Zona 6 - Sardegna'],
            ['code' => 'SZR7', 'name' => 'Zona 7 - Sicilia'],
            ['code' => 'CRC', 'name' => 'Commissione Regole e Competizioni'],
        ];

        foreach ($zones as $zone) {
            Zone::create($zone);
        }
    }

    /**
     * Crea tipi torneo base
     */
    protected function seedTournamentTypes(): void
    {
        $types = [
            [
                'name' => 'Nazionale',
                'short_name' => 'NAZ',
                'is_national' => true,
                'level' => 'nazionale',
                'required_level' => 'nazionale',
                'calendar_color' => '#FF0000',
                'is_active' => true,
                'sort_order' => 1,
                'min_referees' => 2,
                'max_referees' => 3,
            ],
            [
                'name' => 'Zonale',
                'short_name' => 'ZON',
                'is_national' => false,
                'level' => 'zonale',
                'required_level' => '1_livello',
                'calendar_color' => '#0000FF',
                'is_active' => true,
                'sort_order' => 2,
                'min_referees' => 1,
                'max_referees' => 2,
            ],
            [
                'name' => 'Giovanile',
                'short_name' => 'GIO',
                'is_national' => false,
                'level' => 'zonale',
                'required_level' => 'aspirante',
                'calendar_color' => '#00FF00',
                'is_active' => true,
                'sort_order' => 3,
                'min_referees' => 1,
                'max_referees' => 2,
            ],
        ];

        foreach ($types as $type) {
            TournamentType::create($type);
        }
    }

    // ==========================================
    // HELPER METHODS - USER CREATION
    // ==========================================

    /**
     * Crea un utente arbitro
     */
    protected function createReferee(array $attributes = []): User
    {
        if (! isset($attributes['zone_id'])) {
            $attributes['zone_id'] = 1;
        }

        return User::factory()->referee()->create($attributes);
    }

    /**
     * Crea un admin di zona
     */
    protected function createZoneAdmin(int $zoneId = 1, array $attributes = []): User
    {
        return User::factory()->zoneAdmin()->create(
            array_merge(['zone_id' => $zoneId], $attributes)
        );
    }

    /**
     * Crea un admin nazionale
     */
    protected function createNationalAdmin(array $attributes = []): User
    {
        return User::factory()->nationalAdmin()->create($attributes);
    }

    /**
     * Crea un super admin
     */
    protected function createSuperAdmin(array $attributes = []): User
    {
        return User::factory()->superAdmin()->create($attributes);
    }

    // ==========================================
    // HELPER METHODS - DATA CREATION
    // ==========================================

    /**
     * Crea un circolo
     */
    protected function createClub(array $attributes = []): Club
    {
        if (! isset($attributes['zone_id'])) {
            $attributes['zone_id'] = 1;
        }

        return Club::factory()->create($attributes);
    }

    /**
     * Crea un torneo
     */
    protected function createTournament(array $attributes = []): Tournament
    {
        // Se non specificato club, ne crea uno
        if (!isset($attributes['club_id'])) {
            $club = $this->createClub();
            $attributes['club_id'] = $club->id;
        }

        // Se non specificato type, usa il primo disponibile
        if (!isset($attributes['tournament_type_id'])) {
            $attributes['tournament_type_id'] = TournamentType::first()->id;
        }

        return Tournament::factory()->create($attributes);
    }

    /**
     * Crea un'assegnazione
     */
    protected function createAssignment(array $attributes = []): Assignment
    {
        return Assignment::factory()->create($attributes);
    }

    // ==========================================
    // HELPER METHODS - AUTHENTICATION
    // ==========================================

    /**
     * Login come specifico user
     */
    protected function actingAsUser(User $user): self
    {
        $this->actingAs($user);
        return $this;
    }

    /**
     * Login come arbitro
     */
    protected function actingAsReferee(array $attributes = []): self
    {
        $referee = $this->createReferee($attributes);
        $this->actingAs($referee);
        return $this;
    }

    /**
     * Login come admin zona
     */
    protected function actingAsZoneAdmin(int $zoneId = 1, array $attributes = []): self
    {
        $admin = $this->createZoneAdmin($zoneId, $attributes);
        $this->actingAs($admin);
        return $this;
    }

    /**
     * Login come admin nazionale
     */
    protected function actingAsNationalAdmin(array $attributes = []): self
    {
        $admin = $this->createNationalAdmin($attributes);
        $this->actingAs($admin);
        return $this;
    }

    /**
     * Login come super admin
     */
    protected function actingAsSuperAdmin(array $attributes = []): self
    {
        $superAdmin = $this->createSuperAdmin($attributes);
        $this->actingAs($superAdmin);
        return $this;
    }

    // ==========================================
    // CUSTOM ASSERTIONS
    // ==========================================

    /**
     * Assert che un model ha una relazione
     */
    protected function assertHasRelation($model, string $relation): void
    {
        $this->assertTrue(
            method_exists($model, $relation),
            "Model " . get_class($model) . " does not have '{$relation}' relationship"
        );
    }

    /**
     * Assert che user può accedere a una route
     */
    protected function assertUserCanAccess(User $user, string $route): void
    {
        $response = $this->actingAs($user)->get($route);
        $response->assertStatus(200);
    }

    /**
     * Assert che user NON può accedere a una route
     */
    protected function assertUserCannotAccess(User $user, string $route): void
    {
        $response = $this->actingAs($user)->get($route);
        $this->assertTrue(
            in_array($response->status(), [403, 302]),
            "User should not be able to access {$route}"
        );
    }
}
