<?php

namespace Tests\Unit\Enums;

use App\Enums\RefereeLevel;
use App\Helpers\RefereeLevelsHelper;
use Tests\TestCase;

/**
 * Test di regressione per RefereeLevel enum.
 *
 * Verifica che l'enum sia l'unica fonte di verità per i livelli arbitro
 * e che RefereeLevelsHelper deleghi correttamente ad esso.
 */
class RefereeLevelTest extends TestCase
{
    // ──────────────────────────────────────────────
    // label()
    // ──────────────────────────────────────────────

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertEquals('Aspirante',      RefereeLevel::Aspirante->label());
        $this->assertEquals('Primo Livello',  RefereeLevel::PrimoLivello->label());
        $this->assertEquals('Regionale',      RefereeLevel::Regionale->label());
        $this->assertEquals('Nazionale',      RefereeLevel::Nazionale->label());
        $this->assertEquals('Internazionale', RefereeLevel::Internazionale->label());
        $this->assertEquals('Archivio',       RefereeLevel::Archivio->label());
    }

    public function test_label_for_primo_livello_differs_from_value(): void
    {
        // Il valore DB è '1_livello', l'etichetta è 'Primo Livello'
        $this->assertNotEquals(RefereeLevel::PrimoLivello->value, RefereeLevel::PrimoLivello->label());
        $this->assertEquals('1_livello',     RefereeLevel::PrimoLivello->value);
        $this->assertEquals('Primo Livello', RefereeLevel::PrimoLivello->label());
    }

    // ──────────────────────────────────────────────
    // isNational()
    // ──────────────────────────────────────────────

    public function test_is_national_returns_true_for_high_levels(): void
    {
        $this->assertTrue(RefereeLevel::Nazionale->isNational());
        $this->assertTrue(RefereeLevel::Internazionale->isNational());
    }

    public function test_is_national_returns_false_for_low_levels(): void
    {
        $this->assertFalse(RefereeLevel::Aspirante->isNational());
        $this->assertFalse(RefereeLevel::PrimoLivello->isNational());
        $this->assertFalse(RefereeLevel::Regionale->isNational());
        $this->assertFalse(RefereeLevel::Archivio->isNational());
    }

    // ──────────────────────────────────────────────
    // isActive()
    // ──────────────────────────────────────────────

    public function test_is_active_returns_false_only_for_archivio(): void
    {
        $this->assertFalse(RefereeLevel::Archivio->isActive());

        foreach (RefereeLevel::activeLevels() as $level) {
            $this->assertTrue($level->isActive(), "{$level->value} should be active");
        }
    }

    // ──────────────────────────────────────────────
    // activeLevels()
    // ──────────────────────────────────────────────

    public function test_active_levels_excludes_archivio(): void
    {
        $active = RefereeLevel::activeLevels();

        $this->assertNotContains(RefereeLevel::Archivio, $active);
        $this->assertCount(5, $active);
    }

    public function test_active_levels_contains_all_five_levels(): void
    {
        $active = RefereeLevel::activeLevels();

        $this->assertContains(RefereeLevel::Aspirante,      $active);
        $this->assertContains(RefereeLevel::PrimoLivello,   $active);
        $this->assertContains(RefereeLevel::Regionale,      $active);
        $this->assertContains(RefereeLevel::Nazionale,      $active);
        $this->assertContains(RefereeLevel::Internazionale, $active);
    }

    // ──────────────────────────────────────────────
    // selectOptions()
    // ──────────────────────────────────────────────

    public function test_select_options_returns_associative_array(): void
    {
        $opts = RefereeLevel::selectOptions();

        $this->assertIsArray($opts);
        $this->assertArrayHasKey('Aspirante',      $opts);
        $this->assertArrayHasKey('1_livello',      $opts);
        $this->assertArrayHasKey('Regionale',      $opts);
        $this->assertArrayHasKey('Nazionale',      $opts);
        $this->assertArrayHasKey('Internazionale', $opts);
    }

    public function test_select_options_values_are_human_labels(): void
    {
        $opts = RefereeLevel::selectOptions(true);

        $this->assertEquals('Primo Livello', $opts['1_livello']);
        $this->assertEquals('Aspirante',     $opts['Aspirante']);
        $this->assertEquals('Archivio',      $opts['Archivio']);
    }

    public function test_select_options_excludes_archivio_by_default(): void
    {
        $opts = RefereeLevel::selectOptions();

        $this->assertArrayNotHasKey('Archivio', $opts);
        $this->assertCount(5, $opts);
    }

    public function test_select_options_includes_archivio_when_requested(): void
    {
        $opts = RefereeLevel::selectOptions(true);

        $this->assertArrayHasKey('Archivio', $opts);
        $this->assertCount(6, $opts);
    }

    // ──────────────────────────────────────────────
    // canAccessNational()
    // ──────────────────────────────────────────────

    public function test_can_access_national_true_for_valid_high_levels(): void
    {
        $this->assertTrue(RefereeLevel::canAccessNational('Nazionale'));
        $this->assertTrue(RefereeLevel::canAccessNational('Internazionale'));
    }

    public function test_can_access_national_false_for_low_levels(): void
    {
        $this->assertFalse(RefereeLevel::canAccessNational('Aspirante'));
        $this->assertFalse(RefereeLevel::canAccessNational('1_livello'));
        $this->assertFalse(RefereeLevel::canAccessNational('Regionale'));
        $this->assertFalse(RefereeLevel::canAccessNational('Archivio'));
    }

    public function test_can_access_national_false_for_null(): void
    {
        $this->assertFalse(RefereeLevel::canAccessNational(null));
    }

    public function test_can_access_national_false_for_unknown_string(): void
    {
        $this->assertFalse(RefereeLevel::canAccessNational('SomethingRandom'));
    }

    // ──────────────────────────────────────────────
    // Coerenza con RefereeLevelsHelper (delegazione)
    // ──────────────────────────────────────────────

    public function test_helper_select_options_matches_enum_select_options(): void
    {
        $fromEnum   = RefereeLevel::selectOptions();
        $fromHelper = RefereeLevelsHelper::getSelectOptions();

        $this->assertEquals($fromEnum, $fromHelper,
            'RefereeLevelsHelper::getSelectOptions() deve delegare a RefereeLevel::selectOptions()');
    }

    public function test_helper_select_options_with_archived_matches_enum(): void
    {
        $fromEnum   = RefereeLevel::selectOptions(true);
        $fromHelper = RefereeLevelsHelper::getSelectOptions(true);

        $this->assertEquals($fromEnum, $fromHelper);
    }

    public function test_helper_can_access_national_matches_enum(): void
    {
        foreach (['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale', 'Archivio'] as $level) {
            $fromEnum   = RefereeLevel::canAccessNational($level);
            $fromHelper = RefereeLevelsHelper::canAccessNationalTournaments($level);

            $this->assertEquals($fromEnum, $fromHelper,
                "Divergenza per livello '{$level}' tra enum e helper");
        }
    }

    // ──────────────────────────────────────────────
    // Integrità valori DB
    // ──────────────────────────────────────────────

    public function test_all_enum_values_match_db_enum_values(): void
    {
        // I valori dell'enum devono corrispondere esattamente a DB_ENUM_VALUES
        // (la costante legacy mantenuta per retrocompatibilità)
        $enumKeys   = array_column(RefereeLevel::cases(), 'value');
        $helperKeys = array_keys(RefereeLevelsHelper::DB_ENUM_VALUES);

        sort($enumKeys);
        sort($helperKeys);

        $this->assertEquals($helperKeys, $enumKeys,
            'I valori DB_ENUM_VALUES devono essere in sync con i case del RefereeLevel enum');
    }
}
