<?php

namespace Tests\Unit\Services;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\Zone;
use App\Services\DocumentGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentGenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DocumentGenerationService;
    }

    // ==========================================
    // ZONE FOLDER TESTS
    // ==========================================

    // ==========================================
    // TEMPLATE PATH TESTS
    // ==========================================

    /**
     * Ogni zona usa la sua carta intestata; zona sconosciuta o assente: default.
     */
    public function test_zone_template_path_logic(): void
    {
        $method = (new \ReflectionClass($this->service))->getMethod('getZoneTemplatePath');
        $method->setAccessible(true);

        foreach (range(1, 7) as $zone) {
            $path = $method->invoke($this->service, $zone);
            $this->assertIsString($path);
            $this->assertStringEndsWith("lettera_intestata_szr{$zone}.docx", $path);
            $this->assertFileExists($path);
        }
        $fallback = $method->invoke($this->service, 42);
        $this->assertIsString($fallback);
        $this->assertStringEndsWith('lettera_intestata_default.docx', $fallback);
    }

    // ==========================================
    // DATE FORMATTING TESTS
    // ==========================================

    /**
     * Torneo di un giorno, di piu' giorni nello stesso mese, a cavallo di due mesi.
     */
    public function test_tournament_dates_formatting(): void
    {
        $method = (new \ReflectionClass($this->service))->getMethod('formatTournamentDates');
        $method->setAccessible(true);
        $base = now()->addYear()->startOfMonth();

        $one = new Tournament(['start_date' => $base->copy()->addDays(9), 'end_date' => $base->copy()->addDays(9)]);
        $this->assertSame($base->copy()->addDays(9)->format('d/m/Y'), $method->invoke($this->service, $one));

        $same = new Tournament(['start_date' => $base->copy()->addDays(9), 'end_date' => $base->copy()->addDays(11)]);
        $this->assertSame($base->copy()->addDays(9)->format('d').'-'.$base->copy()->addDays(11)->format('d/m/Y'), $method->invoke($this->service, $same));

        $start = $base->copy()->endOfMonth()->startOfDay();
        $end = $base->copy()->addMonth()->addDay();
        $cross = new Tournament(['start_date' => $start, 'end_date' => $end]);
        $this->assertSame($start->format('d/m/Y').' - '.$end->format('d/m/Y'), $method->invoke($this->service, $cross));
    }

    // ==========================================
    // INTEGRATION TESTS (leggeri)
    // ==========================================

    // ==========================================
    // EDGE CASES
    // ==========================================

}
