<?php

namespace Tests\Unit\Models;

use App\Models\TournamentNotification;
use Tests\TestCase;

/**
 * Regression test per FIX B-3.
 *
 * Il metodo canBeResent() restituiva sempre true a causa di un doppio return:
 *
 *   if ($this->sent_at && $this->sent_at->lt(now()->subHour())) {
 *       return true;  // condizione
 *   }
 *   return true;  // ← sempre eseguito — il commento "for testing" era rimasto in prod
 *
 * Il fix implementa la logica corretta:
 *   - sent_at null     → reinviabile (mai inviata)
 *   - inviata > 1 ora  → reinviabile
 *   - inviata < 1 ora  → NON reinviabile (debounce)
 *
 * Eseguire con: php artisan test --filter=TournamentNotificationCanBeResentTest
 */
class TournamentNotificationCanBeResentTest extends TestCase
{
    /**
     * Una notifica mai inviata (sent_at null) deve essere reinviabile.
     */
    public function test_notification_never_sent_can_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'pending',
            'sent_at' => null,
        ]);

        $this->assertTrue(
            $notification->canBeResent(),
            'Una notifica mai inviata deve sempre poter essere reinviata.'
        );
    }

    /**
     * Una notifica inviata più di 1 ora fa deve essere reinviabile.
     */
    public function test_notification_sent_more_than_one_hour_ago_can_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_at' => now()->subHours(2),
        ]);

        $this->assertTrue(
            $notification->canBeResent(),
            'Una notifica inviata 2 ore fa deve poter essere reinviata.'
        );
    }

    /**
     * Una notifica inviata esattamente 61 minuti fa deve essere reinviabile.
     */
    public function test_notification_sent_61_minutes_ago_can_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(61),
        ]);

        $this->assertTrue($notification->canBeResent());
    }

    /**
     * Una notifica inviata meno di 1 ora fa NON deve essere reinviabile.
     * Questo era il comportamento mai applicato prima del fix.
     */
    public function test_notification_sent_recently_cannot_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_at' => now()->subMinutes(5),
        ]);

        $this->assertFalse(
            $notification->canBeResent(),
            'Una notifica inviata 5 minuti fa NON deve poter essere reinviata (debounce 1h).'
        );
    }

    /**
     * Una notifica inviata 30 secondi fa NON deve essere reinviabile.
     */
    public function test_notification_sent_30_seconds_ago_cannot_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'sent',
            'sent_at' => now()->subSeconds(30),
        ]);

        $this->assertFalse($notification->canBeResent());
    }

    /**
     * Una notifica con status 'failed' e sent_at recente NON deve essere reinviabile.
     * Il debounce vale indipendentemente dallo status.
     */
    public function test_failed_notification_sent_recently_cannot_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'failed',
            'sent_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($notification->canBeResent());
    }

    /**
     * Una notifica con status 'failed' inviata > 1h fa DEVE essere reinviabile.
     */
    public function test_failed_notification_sent_over_one_hour_ago_can_be_resent(): void
    {
        $tournament = $this->createTournament();

        $notification = TournamentNotification::create([
            'tournament_id' => $tournament->id,
            'status' => 'failed',
            'sent_at' => now()->subHours(3),
        ]);

        $this->assertTrue($notification->canBeResent());
    }
}
