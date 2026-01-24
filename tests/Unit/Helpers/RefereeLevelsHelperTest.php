<?php

namespace Tests\Unit\Helpers;

use App\Helpers\RefereeLevelsHelper;
use Tests\TestCase;

class RefereeLevelsHelperTest extends TestCase
{
    /**
     * Test: getSelectOptions ritorna array corretto
     */
    public function test_get_select_options_returns_array(): void
    {
        $options = RefereeLevelsHelper::getSelectOptions();

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('Aspirante', $options);
        $this->assertArrayHasKey('Nazionale', $options);
        $this->assertArrayHasKey('Internazionale', $options);
    }

    /**
     * Test: getSelectOptions esclude Archivio per default
     */
    public function test_get_select_options_excludes_archived_by_default(): void
    {
        $options = RefereeLevelsHelper::getSelectOptions();

        $this->assertArrayNotHasKey('Archivio', $options);
    }

    /**
     * Test: getSelectOptions include Archivio se richiesto
     */
    public function test_get_select_options_includes_archived_when_requested(): void
    {
        $options = RefereeLevelsHelper::getSelectOptions(true);

        $this->assertArrayHasKey('Archivio', $options);
    }

    /**
     * Test: normalize converte varianti corrette
     */
    public function test_normalize_converts_variants_correctly(): void
    {
        $testCases = [
            'aspirante' => 'Aspirante',
            'asp' => 'Aspirante',
            'ASPIRANTE' => 'Aspirante',
            'Aspirante' => 'Aspirante',
            
            'primo livello' => '1_livello',
            'primo_livello' => '1_livello',
            'Primo Livello' => '1_livello',
            '1_livello' => '1_livello',
            
            'regionale' => 'Regionale',
            'reg' => 'Regionale',
            'REGIONALE' => 'Regionale',
            
            'nazionale' => 'Nazionale',
            'naz' => 'Nazionale',
            
            'internazionale' => 'Internazionale',
            'int' => 'Internazionale',
        ];

        foreach ($testCases as $input => $expected) {
            $result = RefereeLevelsHelper::normalize($input);
            $this->assertEquals($expected, $result, "Failed to normalize: {$input}");
        }
    }

    /**
     * Test: normalize gestisce null
     */
    public function test_normalize_handles_null(): void
    {
        $result = RefereeLevelsHelper::normalize(null);
        $this->assertNull($result);
    }

    /**
     * Test: normalize gestisce stringa vuota
     */
    public function test_normalize_handles_empty_string(): void
    {
        $result = RefereeLevelsHelper::normalize('');
        $this->assertNull($result);
    }

    /**
     * Test: normalize restituisce input per valore sconosciuto
     */
    public function test_normalize_returns_original_for_unknown_value(): void
    {
        $unknown = 'LivelloSconosciuto';
        $result = RefereeLevelsHelper::normalize($unknown);
        
        $this->assertEquals($unknown, $result);
    }

    /**
     * Test: getLabel ritorna label corretta
     */
    public function test_get_label_returns_correct_labels(): void
    {
        $testCases = [
            'Aspirante' => 'Aspirante',
            '1_livello' => 'Primo Livello',
            'Regionale' => 'Regionale',
            'Nazionale' => 'Nazionale',
            'Internazionale' => 'Internazionale',
        ];

        foreach ($testCases as $input => $expected) {
            $result = RefereeLevelsHelper::getLabel($input);
            $this->assertEquals($expected, $result, "Failed to get label for: {$input}");
        }
    }

    /**
     * Test: getLabel gestisce null
     */
    public function test_get_label_handles_null(): void
    {
        $result = RefereeLevelsHelper::getLabel(null);
        $this->assertEquals('Non specificato', $result);
    }

    /**
     * Test: getLabel gestisce stringa vuota
     */
    public function test_get_label_handles_empty_string(): void
    {
        $result = RefereeLevelsHelper::getLabel('');
        $this->assertEquals('Non specificato', $result);
    }

    /**
     * Test: isValid riconosce livelli validi
     */
    public function test_is_valid_recognizes_valid_levels(): void
    {
        $validLevels = ['Aspirante', '1_livello', 'Regionale', 'Nazionale', 'Internazionale'];

        foreach ($validLevels as $level) {
            $this->assertTrue(
                RefereeLevelsHelper::isValid($level),
                "{$level} should be recognized as valid"
            );
        }
    }

    /**
     * Test: isValid riconosce varianti valide
     */
    public function test_is_valid_recognizes_valid_variants(): void
    {
        $validVariants = ['aspirante', 'primo livello', 'reg', 'naz', 'int'];

        foreach ($validVariants as $variant) {
            $this->assertTrue(
                RefereeLevelsHelper::isValid($variant),
                "{$variant} should be recognized as valid variant"
            );
        }
    }

    /**
     * Test: isValid rifiuta livelli invalidi
     */
    public function test_is_valid_rejects_invalid_levels(): void
    {
        $invalidLevels = ['', null, 'InvalidLevel', 'XYZ'];

        foreach ($invalidLevels as $level) {
            $this->assertFalse(
                RefereeLevelsHelper::isValid($level),
                var_export($level, true) . " should be recognized as invalid"
            );
        }
    }

    /**
     * Test: canAccessNationalTournaments ritorna true per livelli alti
     */
    public function test_can_access_national_tournaments_for_high_levels(): void
    {
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('Nazionale'));
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('Internazionale'));
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('nazionale'));
        $this->assertTrue(RefereeLevelsHelper::canAccessNationalTournaments('int'));
    }

    /**
     * Test: canAccessNationalTournaments ritorna false per livelli bassi
     */
    public function test_can_access_national_tournaments_for_low_levels(): void
    {
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('Aspirante'));
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('1_livello'));
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('Regionale'));
        $this->assertFalse(RefereeLevelsHelper::canAccessNationalTournaments('asp'));
    }

    /**
     * Test: getAllVariants ritorna tutte le varianti
     */
    public function test_get_all_variants_returns_all_variants(): void
    {
        $variants = RefereeLevelsHelper::getAllVariants();

        $this->assertIsArray($variants);
        $this->assertNotEmpty($variants);
        $this->assertContains('Aspirante', $variants);
        $this->assertContains('aspirante', $variants);
        $this->assertContains('primo livello', $variants);
    }

    /**
     * Test: debugLevel ritorna informazioni complete
     */
    public function test_debug_level_returns_complete_info(): void
    {
        $debug = RefereeLevelsHelper::debugLevel('naz');

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('original', $debug);
        $this->assertArrayHasKey('lowercase', $debug);
        $this->assertArrayHasKey('normalized', $debug);
        $this->assertArrayHasKey('label', $debug);
        $this->assertArrayHasKey('is_valid', $debug);
        $this->assertArrayHasKey('can_access_national', $debug);
        
        $this->assertEquals('naz', $debug['original']);
        $this->assertEquals('Nazionale', $debug['normalized']);
        $this->assertTrue($debug['is_valid']);
        $this->assertTrue($debug['can_access_national']);
    }

    /**
     * Test: funzioni helper globali funzionano
     */
    public function test_global_helper_functions_work(): void
    {
        // referee_levels()
        $levels = referee_levels();
        $this->assertIsArray($levels);
        $this->assertNotEmpty($levels);

        // normalize_referee_level()
        $normalized = normalize_referee_level('naz');
        $this->assertEquals('Nazionale', $normalized);

        // referee_level_label()
        $label = referee_level_label('Nazionale');
        $this->assertEquals('Nazionale', $label);
    }
}
