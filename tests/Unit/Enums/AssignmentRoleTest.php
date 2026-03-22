<?php

namespace Tests\Unit\Enums;

use App\Enums\AssignmentRole;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Test di regressione per AssignmentRole enum.
 *
 * Verifica che l'enum sia la fonte di verità per ruoli, ordinamento
 * e normalizzazione (sostituendo RefereeRoleHelper).
 */
class AssignmentRoleTest extends TestCase
{
    // ──────────────────────────────────────────────
    // Valori DB
    // ──────────────────────────────────────────────

    public function test_enum_values_match_database_strings(): void
    {
        $this->assertEquals('Arbitro',           AssignmentRole::Referee->value);
        $this->assertEquals('Direttore di Torneo', AssignmentRole::TournamentDirector->value);
        $this->assertEquals('Osservatore',        AssignmentRole::Observer->value);
    }

    public function test_label_equals_value_for_all_roles(): void
    {
        // Nel dominio italiano i valori DB coincidono già con le etichette
        foreach (AssignmentRole::cases() as $role) {
            $this->assertEquals($role->value, $role->label(),
                "label() deve coincidere con value() per {$role->name}");
        }
    }

    // ──────────────────────────────────────────────
    // values()
    // ──────────────────────────────────────────────

    public function test_values_returns_all_three_strings(): void
    {
        $values = AssignmentRole::values();

        $this->assertCount(3, $values);
        $this->assertContains('Arbitro',             $values);
        $this->assertContains('Direttore di Torneo', $values);
        $this->assertContains('Osservatore',         $values);
    }

    public function test_values_are_strings(): void
    {
        foreach (AssignmentRole::values() as $v) {
            $this->assertIsString($v);
        }
    }

    // ──────────────────────────────────────────────
    // default()
    // ──────────────────────────────────────────────

    public function test_default_returns_referee(): void
    {
        $this->assertSame(AssignmentRole::Referee, AssignmentRole::default());
    }

    public function test_default_value_is_arbitro(): void
    {
        $this->assertEquals('Arbitro', AssignmentRole::default()->value);
    }

    // ──────────────────────────────────────────────
    // sortOrder()
    // ──────────────────────────────────────────────

    public function test_sort_order_director_has_highest_priority(): void
    {
        $this->assertLessThan(
            AssignmentRole::Referee->sortOrder(),
            AssignmentRole::TournamentDirector->sortOrder()
        );
    }

    public function test_sort_order_observer_has_lowest_priority(): void
    {
        $this->assertGreaterThan(
            AssignmentRole::Referee->sortOrder(),
            AssignmentRole::Observer->sortOrder()
        );
    }

    public function test_sort_order_all_distinct(): void
    {
        $orders = array_map(fn($r) => $r->sortOrder(), AssignmentRole::cases());

        $this->assertEquals(count($orders), count(array_unique($orders)),
            'Ogni ruolo deve avere sortOrder unico');
    }

    // ──────────────────────────────────────────────
    // sortCollection()
    // ──────────────────────────────────────────────

    private function makeAssignment(string $role, string $name): object
    {
        return (object) [
            'role' => $role,
            'user' => (object) ['name' => $name],
        ];
    }

    public function test_sort_collection_director_comes_first(): void
    {
        $assignments = new Collection([
            $this->makeAssignment('Arbitro',             'Bianchi'),
            $this->makeAssignment('Osservatore',         'Verdi'),
            $this->makeAssignment('Direttore di Torneo', 'Rossi'),
        ]);

        $sorted = AssignmentRole::sortCollection($assignments)->values();

        $this->assertEquals('Direttore di Torneo', $sorted[0]->role);
        $this->assertEquals('Arbitro',             $sorted[1]->role);
        $this->assertEquals('Osservatore',         $sorted[2]->role);
    }

    public function test_sort_collection_alphabetical_within_same_role(): void
    {
        $assignments = new Collection([
            $this->makeAssignment('Arbitro', 'Zeta'),
            $this->makeAssignment('Arbitro', 'Alpha'),
            $this->makeAssignment('Arbitro', 'Mele'),
        ]);

        $sorted = AssignmentRole::sortCollection($assignments)->values();

        $this->assertEquals('Alpha', $sorted[0]->user->name);
        $this->assertEquals('Mele',  $sorted[1]->user->name);
        $this->assertEquals('Zeta',  $sorted[2]->user->name);
    }

    public function test_sort_collection_handles_unknown_role_gracefully(): void
    {
        $assignments = new Collection([
            $this->makeAssignment('RuoloSconosciuto', 'Xyz'),
            $this->makeAssignment('Direttore di Torneo', 'Alfa'),
        ]);

        // Non deve lanciare eccezioni
        $sorted = AssignmentRole::sortCollection($assignments)->values();

        // Il direttore deve venire prima, il ruolo sconosciuto (peso 999) dopo
        $this->assertEquals('Direttore di Torneo', $sorted[0]->role);
    }

    public function test_sort_collection_preserves_count(): void
    {
        $assignments = new Collection([
            $this->makeAssignment('Arbitro', 'A'),
            $this->makeAssignment('Osservatore', 'B'),
            $this->makeAssignment('Direttore di Torneo', 'C'),
        ]);

        $sorted = AssignmentRole::sortCollection($assignments);

        $this->assertCount(3, $sorted);
    }

    public function test_sort_collection_handles_empty_collection(): void
    {
        $sorted = AssignmentRole::sortCollection(new Collection());

        $this->assertInstanceOf(Collection::class, $sorted);
        $this->assertCount(0, $sorted);
    }

    // ──────────────────────────────────────────────
    // normalize()
    // ──────────────────────────────────────────────

    public function test_normalize_italian_values_round_trip(): void
    {
        $this->assertSame(AssignmentRole::TournamentDirector, AssignmentRole::normalize('Direttore di Torneo'));
        $this->assertSame(AssignmentRole::Observer,           AssignmentRole::normalize('Osservatore'));
        $this->assertSame(AssignmentRole::Referee,            AssignmentRole::normalize('Arbitro'));
    }

    public function test_normalize_english_variants(): void
    {
        $this->assertSame(AssignmentRole::TournamentDirector, AssignmentRole::normalize('Tournament Director'));
        $this->assertSame(AssignmentRole::Observer,           AssignmentRole::normalize('Observer'));
        $this->assertSame(AssignmentRole::Referee,            AssignmentRole::normalize('Referee'));
    }

    public function test_normalize_case_insensitive(): void
    {
        $this->assertSame(AssignmentRole::TournamentDirector, AssignmentRole::normalize('TOURNAMENT DIRECTOR'));
        $this->assertSame(AssignmentRole::Observer,           AssignmentRole::normalize('OSSERVATORE'));
        $this->assertSame(AssignmentRole::Referee,            AssignmentRole::normalize('ARBITRO'));
    }

    public function test_normalize_trims_whitespace(): void
    {
        $this->assertSame(AssignmentRole::TournamentDirector, AssignmentRole::normalize('  Direttore di Torneo  '));
        $this->assertSame(AssignmentRole::Observer,           AssignmentRole::normalize('  osservatore  '));
    }

    public function test_normalize_unknown_input_defaults_to_referee(): void
    {
        // Comportamento post-refactoring: nessun pass-through, default sicuro
        $this->assertSame(AssignmentRole::Referee, AssignmentRole::normalize('Unknown Role'));
        $this->assertSame(AssignmentRole::Referee, AssignmentRole::normalize(''));
        $this->assertSame(AssignmentRole::Referee, AssignmentRole::normalize('XYZ123'));
    }

    public function test_normalize_returns_value_usable_in_db_query(): void
    {
        // Il valore ritornato da normalize() deve essere un valore valido nel DB
        $validValues = AssignmentRole::values();

        foreach (['Arbitro', 'Direttore di Torneo', 'Osservatore', 'Unknown'] as $input) {
            $normalized = AssignmentRole::normalize($input)->value;
            $this->assertContains($normalized, $validValues,
                "normalize('{$input}')->value deve essere un valore DB valido");
        }
    }

    // ──────────────────────────────────────────────
    // Integrità complessiva
    // ──────────────────────────────────────────────

    public function test_all_roles_are_tryable_from_their_own_value(): void
    {
        // Ogni case deve essere recuperabile con tryFrom(->value)
        foreach (AssignmentRole::cases() as $role) {
            $found = AssignmentRole::tryFrom($role->value);
            $this->assertSame($role, $found,
                "tryFrom('{$role->value}') deve restituire AssignmentRole::{$role->name}");
        }
    }

    public function test_tryFrom_unknown_returns_null(): void
    {
        $this->assertNull(AssignmentRole::tryFrom('RuoloInesistente'));
        $this->assertNull(AssignmentRole::tryFrom(''));
    }

    public function test_sort_and_normalize_consistency(): void
    {
        // Un ruolo normalizzato deve avere un sortOrder definito (non 999)
        foreach (['Arbitro', 'Direttore di Torneo', 'Osservatore'] as $roleStr) {
            $role = AssignmentRole::normalize($roleStr);
            $this->assertLessThan(999, $role->sortOrder(),
                "Il ruolo '{$roleStr}' normalizzato non deve avere sortOrder=999");
        }
    }
}
