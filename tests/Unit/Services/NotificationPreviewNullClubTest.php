<?php

namespace Tests\Unit\Services;

use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Services\NotificationPreparationService;
use Tests\TestCase;

/**
 * REGRESSIONE FIX C3: NotificationPreparationService::prepareEmailPreview()
 * deve usare l'accesso null-safe `$tournament->club?->email`.
 *
 * Prima del fix, con recipients.club = true ma circolo assente, l'anteprima
 * lanciava un null pointer (`$tournament->club->email`).
 *
 * NOTA: a livello DB il vincolo tournaments.club_id è `constrained('clubs')`
 * (restrict), quindi un torneo persistito ha SEMPRE un circolo. Lo scenario
 * "club null" è difensivo: lo riproduciamo forzando la relation a null in
 * memoria (setRelation), così il test esercita esattamente l'operatore `?->`.
 */
class NotificationPreviewNullClubTest extends TestCase
{
    public function test_preview_with_club_flag_but_null_club_does_not_throw(): void
    {
        $tournament = $this->createTournament([
            'tournament_type_id' => TournamentType::first()->id,
        ]);

        // Forza circolo assente (difensivo: il FK normalmente lo impedisce).
        $tournament->setRelation('club', null);

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null,
            'status'            => 'pending',
            'metadata'          => [
                'subject'    => 'Oggetto test',
                'message'    => 'Messaggio test',
                'recipients' => [
                    'club'          => true,   // richiede il circolo, che però è null
                    'referees'      => [],
                    'institutional' => [],
                ],
            ],
        ]);

        $preview = app(NotificationPreparationService::class)
            ->prepareEmailPreview($notification, $tournament);

        // Niente eccezione e il campo club è null, non un crash.
        $this->assertArrayHasKey('recipients', $preview);
        $this->assertNull($preview['recipients']['club']);
        $this->assertSame('Oggetto test', $preview['subject']);
    }
}
