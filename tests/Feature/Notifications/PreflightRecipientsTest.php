<?php

namespace Tests\Feature\Notifications;

use App\Services\NotificationPreparationService;
use Tests\TestCase;

/**
 * Pre-flight destinatari nel form prepare_notification (2026-06-11).
 *
 * Raccomandazione del risk assessment 2026-05-30 (vedi docs/STORICO.md):
 * il RecipientBuilder SCARTA silenziosamente le email invalide (filter_var
 * + Log::warning) — il pre-flight rende visibile all'admin, PRIMA del send,
 * chi verrà scartato. Questi test verificano che buildPreflight() usi la
 * stessa regola di validazione dell'invio reale.
 */
class PreflightRecipientsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $clubAttrs
     * @param  list<string>  $refereeEmails
     * @return array<string, mixed>
     */
    private function preflightFor(array $clubAttrs, array $refereeEmails = []): array
    {
        $club = $this->createClub(array_merge(['zone_id' => 1], $clubAttrs));
        $tournament = $this->createTournament(['club_id' => $club->id]);

        foreach ($refereeEmails as $email) {
            $referee = $this->createReferee(['zone_id' => 1, 'email' => $email]);
            $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $referee->id]);
        }

        return app(NotificationPreparationService::class)->buildPreflight($tournament->refresh());
    }

    public function test_all_valid_emails_produce_zero_invalid(): void
    {
        $preflight = $this->preflightFor(
            ['email' => 'circolo@example.test'],
            ['arbitro@example.test']
        );

        $this->assertSame(0, $preflight['invalid']);
        // circolo + sezione zona + 1 arbitro
        $this->assertCount(3, $this->arrayAt($preflight, 'entries'));
    }

    public function test_club_with_empty_email_is_flagged(): void
    {
        // clubs.email è NOT NULL: lo sporco reale è la stringa vuota
        $preflight = $this->preflightFor(['email' => '']);

        $clubEntry = collect($this->arrayAt($preflight, 'entries'))->firstWhere('type', 'Circolo (TO)');
        $this->assertIsArray($clubEntry);
        $this->assertFalse($clubEntry['valid']);
        $this->assertGreaterThanOrEqual(1, $preflight['invalid']);
    }

    public function test_zone_with_name_instead_of_email_is_flagged(): void
    {
        // il dato corrotto reale in produzione: zones.email = "Sezione Zonale Regole 6"
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $club->zone->update(['email' => 'Sezione Zonale Regole 6']);
        $tournament = $this->createTournament(['club_id' => $club->id]);

        $preflight = app(NotificationPreparationService::class)->buildPreflight($tournament->refresh());

        $zoneEntry = collect((array) $preflight['entries'])->firstWhere('type', 'Sezione di zona (CC)');
        $this->assertIsArray($zoneEntry);
        $this->assertFalse($zoneEntry['valid']);
        // Il nome della zona deve arrivare dal DB, non dal fallback 'N/A':
        // senza questa asserzione il mutante che sostituisce `$zone->name ?? 'N/A'`
        // con la sola costante sopravvive (Infection 2026-09-05).
        $this->assertSame($club->zone->name, $zoneEntry['name']);
    }

    public function test_referee_with_malformed_email_is_flagged_others_valid(): void
    {
        $preflight = $this->preflightFor(
            ['email' => 'circolo@example.test'],
            ['valido@example.test', 'non-una-email']
        );

        $referees = collect($this->arrayAt($preflight, 'entries'))->where('type', 'Arbitro (CC)');
        $this->assertCount(2, $referees);
        $this->assertSame(1, $referees->where('valid', false)->count());

        $invalido = $referees->firstWhere('valid', false);
        $this->assertIsArray($invalido);
        $this->assertSame('non-una-email', $invalido['email']);
    }
}
