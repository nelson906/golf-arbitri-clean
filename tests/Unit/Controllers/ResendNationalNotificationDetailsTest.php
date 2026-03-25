<?php

namespace Tests\Unit\Controllers;

use App\Models\TournamentNotification;
use Tests\TestCase;

/**
 * Regression test per FIX C-2.
 *
 * Il metodo resendNationalNotification() tentava di salvare 'total_recipients'
 * come colonna diretta di tournament_notifications. Tale colonna non esiste
 * nella migrazione: il dato veniva silenziosamente ignorato da Eloquent
 * (non era nemmeno in $fillable).
 *
 * Il fix salva total_recipients nel campo JSON 'details', coerentemente
 * con sendNationalNotification() e con getStatsAttribute().
 *
 * Eseguire con: php artisan test --filter=ResendNationalNotificationDetailsTest
 */
class ResendNationalNotificationDetailsTest extends TestCase
{
    /**
     * Verifica che 'total_recipients' NON sia nel $fillable di TournamentNotification.
     * Questo è il contratto che il fix mantiene: total_recipients vive in details JSON.
     */
    public function test_total_recipients_is_not_in_fillable(): void
    {
        $notification = new TournamentNotification;

        $this->assertNotContains(
            'total_recipients',
            $notification->getFillable(),
            'total_recipients non deve essere in $fillable — è salvato in details JSON.'
        );
    }

    /**
     * Verifica che 'details' sia in $fillable e sia castato come array.
     */
    public function test_details_is_fillable_and_cast_as_array(): void
    {
        $notification = new TournamentNotification;

        $this->assertContains('details', $notification->getFillable());

        $casts = $notification->getCasts();
        $this->assertArrayHasKey('details', $casts);
        $this->assertEquals('array', $casts['details']);
    }

    /**
     * Simula il pattern del fix: total_recipients viene salvato in details JSON.
     * Verifica che dopo update() il valore sia recuperabile.
     */
    public function test_total_recipients_saved_in_details_json_is_retrievable(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_by' => $this->createZoneAdmin()->id,
            'details' => ['total_recipients' => 0],
        ]);

        $totalRecipients = 7;
        $currentDetails = is_array($notification->details) ? $notification->details : [];

        $notification->update([
            'details' => array_merge($currentDetails, [
                'total_recipients' => $totalRecipients,
                'success_count' => 7,
                'error_count' => 0,
            ]),
        ]);

        $fresh = $notification->fresh();

        $this->assertIsArray($fresh->details);
        $this->assertEquals(7, $fresh->details['total_recipients']);
        $this->assertEquals(7, $fresh->details['success_count']);
        $this->assertEquals(0, $fresh->details['error_count']);
    }

    /**
     * Verifica che l'update con 'total_recipients' come chiave diretta
     * NON persista (comportamento previsto: ignorato silenziosamente).
     * Questo è il bug originale — il test lo documenta come "già rotto".
     */
    public function test_direct_total_recipients_update_is_silently_ignored(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
        ]);

        // Tenta il pattern SBAGLIATO (il bug originale)
        $notification->update(['total_recipients' => 99]);

        $fresh = $notification->fresh();

        // Verifica che la colonna fantasma non abbia persistito il valore
        // (non è in $fillable, quindi Eloquent la ignora)
        $this->assertNull(
            $fresh->getAttribute('total_recipients'),
            'total_recipients non deve persistere come colonna diretta.'
        );
    }

    /**
     * Verifica che getStatsAttribute() legga total_recipients da details JSON.
     */
    public function test_get_stats_attribute_reads_total_recipients_from_details(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'details' => [
                'total_recipients' => 5,
                'club' => ['sent' => 1, 'failed' => 0],
                'referees' => ['sent' => 4, 'failed' => 0],
                'institutional' => ['sent' => 0, 'failed' => 0],
            ],
        ]);

        $stats = $notification->stats;

        $this->assertEquals(5, $stats['total_sent']);
        $this->assertEquals(0, $stats['total_failed']);
    }
}
