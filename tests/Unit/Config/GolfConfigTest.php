<?php

namespace Tests\Unit\Config;

use App\Enums\AssignmentRole;
use Tests\TestCase;

/**
 * Test di regressione — Bug: config/golf.php dichiarava il ruolo
 * 'assistant' => 'Assistente' che non esiste nell'enum AssignmentRole
 * né nel database (enum DB: 'Direttore di Torneo', 'Arbitro', 'Osservatore').
 *
 * Se un select HTML usasse questa config, potrebbe inviare 'Assistente'
 * al DB causando un errore di validazione o un silent failure.
 *
 * Fix: rimosso 'assistant' da config/golf.php.
 *
 * @see config/golf.php
 * @see app/Enums/AssignmentRole.php
 */
class GolfConfigTest extends TestCase
{
    // ============================================================
    // SEZIONE 1 — Coerenza config assignment_roles con AssignmentRole enum
    // ============================================================

    /**
     * Regressione: nessun valore in config('golf.assignment_roles')
     * deve referenziare un ruolo non esistente nell'AssignmentRole enum.
     *
     * Nota: le chiavi della config (default, director, referee, observer)
     * sono identificatori interni (non valori DB). I valori invece
     * devono corrispondere ai valori dell'enum.
     */
    public function test_all_config_assignment_role_values_exist_in_enum(): void
    {
        $configRoles = config('golf.assignment_roles');
        $this->assertIsArray($configRoles, 'golf.assignment_roles deve essere un array');

        $validEnumValues = AssignmentRole::values();

        // Eccezioni: 'default' è un alias che punta a 'Arbitro', non è un ruolo distinto
        // ma i valori devono tutti essere valori dell'enum
        foreach ($configRoles as $key => $label) {
            $this->assertContains(
                $label,
                $validEnumValues,
                "config('golf.assignment_roles.{$key}') = '{$label}' non è un valore valido " .
                "dell'AssignmentRole enum. Valori validi: " . implode(', ', $validEnumValues)
            );
        }
    }

    /**
     * Regressione specifica per 'assistant' — deve essere assente dalla config.
     */
    public function test_assistant_role_is_not_in_config(): void
    {
        $configRoles = config('golf.assignment_roles');

        $this->assertArrayNotHasKey(
            'assistant',
            $configRoles,
            "La chiave 'assistant' NON deve essere presente in config('golf.assignment_roles'). " .
            "Il ruolo 'Assistente' non esiste nell'enum AssignmentRole né nel database."
        );
    }

    /**
     * Il valore 'Assistente' non deve apparire in nessun valore della config.
     */
    public function test_assistente_label_is_not_in_config_values(): void
    {
        $configRoles = config('golf.assignment_roles');

        $this->assertNotContains(
            'Assistente',
            array_values($configRoles),
            "Il label 'Assistente' NON deve essere presente nei valori di config('golf.assignment_roles')."
        );
    }

    // ============================================================
    // SEZIONE 2 — Verifica che i ruoli attesi esistano
    // ============================================================

    /**
     * I ruoli fondamentali (director, referee, observer) devono essere presenti.
     */
    public function test_required_assignment_roles_are_present_in_config(): void
    {
        $configRoles = config('golf.assignment_roles');

        // Questi tre devono sempre esserci
        $this->assertArrayHasKey('director', $configRoles,
            "La chiave 'director' deve essere presente");
        $this->assertArrayHasKey('referee', $configRoles,
            "La chiave 'referee' deve essere presente");
        $this->assertArrayHasKey('observer', $configRoles,
            "La chiave 'observer' deve essere presente");
    }

    /**
     * I valori dei ruoli config devono corrispondere esattamente ai valori dell'enum.
     */
    public function test_config_role_values_match_enum_exactly(): void
    {
        $configRoles = config('golf.assignment_roles');

        // Verifica mapping diretto
        if (isset($configRoles['referee'])) {
            $this->assertSame(
                AssignmentRole::Referee->value,
                $configRoles['referee'],
                "config.director deve corrispondere a AssignmentRole::Referee->value"
            );
        }

        if (isset($configRoles['director'])) {
            $this->assertSame(
                AssignmentRole::TournamentDirector->value,
                $configRoles['director'],
                "config.director deve corrispondere a AssignmentRole::TournamentDirector->value"
            );
        }

        if (isset($configRoles['observer'])) {
            $this->assertSame(
                AssignmentRole::Observer->value,
                $configRoles['observer'],
                "config.observer deve corrispondere a AssignmentRole::Observer->value"
            );
        }
    }

    // ============================================================
    // SEZIONE 3 — Verifica coerenza AssignmentRole enum con DB schema
    // ============================================================

    /**
     * L'enum AssignmentRole deve avere esattamente 3 casi
     * (corrispondenti ai 3 valori nell'enum DB).
     */
    public function test_assignment_role_enum_has_correct_cases(): void
    {
        $cases = AssignmentRole::cases();

        $this->assertCount(3, $cases,
            'AssignmentRole deve avere esattamente 3 casi: Referee, TournamentDirector, Observer');

        $values = AssignmentRole::values();
        $this->assertContains('Arbitro', $values);
        $this->assertContains('Direttore di Torneo', $values);
        $this->assertContains('Osservatore', $values);
    }
}
