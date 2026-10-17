<?php

namespace Tests\Unit\Models;

use App\Models\TournamentNotification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contatori e stato della notifica letti dalla colonna `details`, che nel
 * tempo ha avuto due forme (semplice e "complessa") e righe storiche salvate
 * come stringa JSON.
 */
class TournamentNotificationStatsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function notification(array $attributes = []): TournamentNotification
    {
        return TournamentNotification::create(array_merge([
            'tournament_id' => $this->createTournament()->id,
            'status' => 'pending',
        ], $attributes));
    }

    public function test_complex_format_counts_sent_and_failed(): void
    {
        $n = $this->notification(['details' => [
            'club' => ['sent' => 1, 'failed' => 0],
            'referees' => ['sent' => 3, 'failed' => 1],
            'institutional' => ['sent' => 2, 'failed' => 1],
            'total_recipients' => 8,
        ]]);

        $stats = $n->stats;

        $this->assertSame(1, $stats['club_sent']);
        $this->assertSame(3, $stats['referees_sent']);
        $this->assertSame(1, $stats['referees_failed']);
        $this->assertSame(2, $stats['institutional_sent']);
        $this->assertSame(1, $stats['institutional_failed']);
        $this->assertSame(8, $stats['total_sent']);
        $this->assertSame(2, $stats['total_failed']);
        $this->assertSame(75.0, $stats['success_rate']);
    }

    public function test_complex_format_without_recipients_has_zero_rate(): void
    {
        $n = $this->notification(['details' => ['club' => ['sent' => 0]]]);

        $this->assertSame(0.0, $n->stats['success_rate']);
        $this->assertSame(0, $n->stats['total_sent']);
    }

    public function test_simple_format_falls_back_to_total_recipients(): void
    {
        $n = $this->notification(['details' => ['sent' => 0, 'arbitri' => 2, 'club' => 1, 'total_recipients' => 5]]);

        $this->assertSame(5, $n->stats['total_sent']);
        $this->assertSame(2, $n->stats['referees_sent']);
        $this->assertSame(100.0, $n->stats['success_rate']);
    }

    public function test_details_saved_as_json_string_are_read(): void
    {
        $n = $this->notification();
        DB::table('tournament_notifications')->where('id', $n->id)->update([
            'details' => json_encode(json_encode(['sent' => 4, 'arbitri' => 3, 'club' => 1])),
        ]);

        $fresh = TournamentNotification::findOrFail($n->id);

        $this->assertIsString($fresh->details);
        $this->assertSame(4, $fresh->stats['total_sent']);
        $this->assertSame(1, $fresh->stats['club_sent']);
    }

    public function test_non_numeric_or_scalar_levels_count_as_zero(): void
    {
        $n = $this->notification(['details' => ['club' => 'tutto ok', 'referees' => ['sent' => 'tre']]]);

        $this->assertSame(0, $n->stats['club_sent']);
        $this->assertSame(0, $n->stats['referees_sent']);
    }

    public function test_state_labels(): void
    {
        $this->assertSame('Inviata', $this->notification(['status' => 'sent'])->stateLabel());
        $this->assertSame('Inviata in parte', $this->notification(['status' => 'partial'])->stateLabel());
        $this->assertSame('Non inviata', $this->notification(['status' => 'failed'])->stateLabel());
        $this->assertSame('Da inviare', $this->notification(['status' => 'pending'])->stateLabel());
    }
}
