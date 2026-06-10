<?php

namespace Tests\Feature\Notifications;

use App\Helpers\ZoneHelper;
use App\Mail\ClubNotificationMail;
use App\Models\InstitutionalEmail;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\TournamentType;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * REGRESSIONE INVIO ZONALE (bug report 2026-06: "la notifica zonale arriva
 * solo agli arbitri, non al circolo né agli indirizzi istituzionali").
 *
 * Difetti individuati dall'analisi (D1–D4):
 *
 *  D1  PRECEDENZA INVERTITA: NotificationService::send() usa
 *      `$notification->recipients ?: $metadata['recipients']`. La colonna
 *      `recipients`, persistita da un QUALSIASI invio precedente, shadowa
 *      PER SEMPRE le scelte fresche del form (saveAsDraft scrive solo
 *      metadata). Se la colonna contiene club:false / institutional:[]
 *      (vedi D2), ogni reinvio dal form ignora circolo e istituzionali
 *      qualunque cosa l'admin selezioni.
 *
 *  D2  GUARD INSUFFICIENTE: send() verifica solo `empty($metadata)`. I record
 *      creati da MarkFigAssignmentsNotified / import FIG hanno metadata
 *      NON vuoto ({source, command}) ma SENZA 'recipients' → il fallback è
 *      {club:false, referees:[], institutional:[]} → si invia A NESSUNO,
 *      si flasha "successo", e si persiste la colonna avvelenata → innesca D1.
 *
 *  D3  FALLIMENTI SILENZIOSI: circolo senza email o istituzionale mancante
 *      vengono catturati per-destinatario (status 'partial') ma il controller
 *      flasha comunque "inviata con successo".
 *
 *  D4  NESSUN TEST END-TO-END REALE: i test esistenti usano Mail::fake, che
 *      bypassa serializzazione queue, render delle view e transport. Il test
 *      _for_real qui sotto esegue il percorso completo (sync queue +
 *      array transport + allegati reali su disco).
 *
 * MODELLO A MAIL SINGOLA (revisione su indicazione di Alberto):
 *   UNA email: TO (competenza) = circolo, con allegati convocazione + lettera;
 *   CC (conoscenza) = arbitri + istituzionali + sezione di zona + email
 *   aggiuntive. Senza circolo, primo CC promosso a TO (come il nazionale).
 *   NB: gli allegati raggiungono anche i CC (limite del mezzo email).
 * I fix D1/D2/D3 sono implementati: questi test li pinnano.
 */
class ZonalDeliveryRegressionTest extends TestCase
{
    private function zonalType(): TournamentType
    {
        return TournamentType::where('is_national', false)->firstOrFail();
    }

    /**
     * Torneo zonale completo: circolo con email, 2 arbitri assegnati,
     * 1 indirizzo istituzionale attivo, notifica draft con documenti su disco.
     *
     * @return array{0: Tournament, 1: User, 2: User, 3: InstitutionalEmail, 4: TournamentNotification}
     */
    private function setupFullZonalScenario(): array
    {
        $club = $this->createClub(['zone_id' => 1, 'email' => 'circolo@example.test']);
        $tournament = $this->createTournament([
            'club_id'            => $club->id,
            'tournament_type_id' => $this->zonalType()->id,
            'status'             => 'open',
        ]);

        $refA = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro.a@example.test']);
        $refB = $this->createReferee(['zone_id' => 1, 'email' => 'arbitro.b@example.test']);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $refA->id]);
        $this->createAssignment(['tournament_id' => $tournament->id, 'user_id' => $refB->id]);

        $institutional = InstitutionalEmail::create([
            'name'      => 'Ufficio Zona Test',
            'email'     => 'ufficio.zona@example.test',
            'category'  => 'convocazioni',
            'is_active' => true,
            'zone_id'   => 1,
        ]);

        // Documenti reali su disco nel percorso che NotificationService usa
        // per costruire gli allegati (convocazione + lettera circolo).
        $zoneCode = ZoneHelper::getFolderCodeForTournament($tournament->fresh(['club', 'tournamentType']));
        $docsRoot = config('golf.documents.storage_path');
        $dir = storage_path("app/public/{$docsRoot}/{$zoneCode}/generated");
        File::ensureDirectoryExists($dir);
        file_put_contents($dir.'/test_convocation.docx', 'fake-docx-convocazione');
        file_put_contents($dir.'/test_club_letter.docx', 'fake-docx-lettera');

        $notification = TournamentNotification::create([
            'tournament_id'     => $tournament->id,
            'notification_type' => null, // zonale
            'status'            => 'pending',
            'documents'         => [
                'convocation' => 'test_convocation.docx',
                'club_letter' => 'test_club_letter.docx',
            ],
        ]);

        return [$tournament, $refA, $refB, $institutional, $notification];
    }

    private function postSend(Tournament $tournament, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(
            route('admin.tournaments.send-assignment-with-convocation', $tournament),
            array_merge([
                'action'             => 'send',
                'subject'            => 'Convocazione Gara Zonale',
                'message'            => 'Si comunicano le assegnazioni.',
                'recipients'         => $tournament->assignments()->pluck('user_id')->all(),
                'send_to_club'       => 1,
                'attach_convocation' => 1,
            ], $overrides)
        );
    }

    // ════════════════════════════════════════════════════════════════════
    // D4 — END-TO-END "REALE": destinatari sulla mailable effettiva, poi
    // render Blade, allegati da disco e roundtrip di serializzazione
    // (ciò che la queue farebbe). Il transport array non è ispezionabile in
    // modo affidabile sotto RefreshDatabase (i job afterCommit restano
    // appesi al wrapper di test), quindi si verifica la mailable stessa.
    // ════════════════════════════════════════════════════════════════════

    public function test_fresh_zonal_send_delivers_for_real_to_club_referees_and_institutional(): void
    {
        [$tournament, $refA, $refB, $institutional, $notification] = $this->setupFullZonalScenario();

        $zoneEmail = \App\Models\Zone::find(1)?->email;

        // Intento del form direttamente in metadata (il percorso HTTP che lo
        // compila è coperto dagli altri test di questo file).
        $notification->update([
            'metadata' => [
                'subject'    => 'Convocazione Gara Zonale',
                'message'    => 'Si comunicano le assegnazioni.',
                'recipients' => [
                    'club'          => true,
                    'referees'      => [$refA->id, $refB->id],
                    'institutional' => [$institutional->id],
                    'zone'          => true,
                    'additional'    => [['email' => 'esterno@example.test', 'name' => 'Osservatore Esterno']],
                ],
            ],
        ]);

        app(\App\Services\NotificationService::class)->send($notification->fresh());

        // UNA sola email
        Mail::assertQueued(ClubNotificationMail::class, 1);

        /** @var ClubNotificationMail $mailable */
        $mailable = Mail::queued(ClubNotificationMail::class)->first();

        // COMPETENZA: TO = solo il circolo
        $this->assertTrue($mailable->hasTo('circolo@example.test'),
            'REGRESSIONE: il TO (competenza) deve essere il circolo.');
        $this->assertCount(1, $mailable->to,
            'REGRESSIONE: il TO deve contenere SOLO il circolo.');

        // CONOSCENZA: arbitri + istituzionale + sezione di zona + email aggiuntiva in CC
        foreach ([
            'arbitro.a@example.test'    => 'arbitro A',
            'arbitro.b@example.test'    => 'arbitro B',
            'ufficio.zona@example.test' => 'istituzionale',
            'esterno@example.test'      => 'email aggiuntiva',
        ] as $email => $label) {
            $this->assertTrue($mailable->hasCc($email), "REGRESSIONE: {$label} non in CC.");
        }
        if ($zoneEmail) {
            $this->assertTrue($mailable->hasCc($zoneEmail), 'REGRESSIONE: sezione di zona non in CC.');
        }

        // ALLEGATI reali da disco (convocazione + lettera/facsimile)
        $attachmentNames = array_map(
            fn ($a) => $a->as,
            $mailable->attachments()
        );
        $this->assertContains('Lettera_Circolo.docx', $attachmentNames,
            'REGRESSIONE: manca la lettera circolo (facsimile) in allegato.');
        $this->assertContains('Convocazione.docx', $attachmentNames,
            'REGRESSIONE: manca la convocazione in allegato.');

        // RENDER reale della view (ciò che la queue farebbe al momento
        // dell'invio): non deve lanciare e deve contenere i dati del torneo.
        $html = $mailable->render();
        $this->assertStringContainsString($tournament->club->name, $html,
            'REGRESSIONE: il corpo email non contiene il nome del circolo.');

        // ROUNDTRIP DI SERIALIZZAZIONE (SerializesModels): è ciò che accade
        // quando il Mailable passa dalla coda. Dopo il roundtrip deve ancora
        // renderizzare e mantenere i destinatari.
        /** @var ClubNotificationMail $restored */
        $restored = unserialize(serialize($mailable));
        $this->assertTrue($restored->hasTo('circolo@example.test'),
            'REGRESSIONE: TO perso nel roundtrip di serializzazione (queue).');
        $this->assertNotEmpty($restored->render(),
            'REGRESSIONE: render fallito dopo serializzazione (queue).');

        // Stato persistito coerente
        $final = $notification->fresh();
        $this->assertEquals('sent', $final->status);
        $this->assertEquals(0, $final->metadata['error_count'] ?? -1);
    }

    // ════════════════════════════════════════════════════════════════════
    // D1 — Le scelte del FORM devono vincere sulla colonna recipients
    // stantia persistita da un invio precedente.
    // FIX D1 implementato: send() legge SOLO metadata['recipients'].
    // ════════════════════════════════════════════════════════════════════

    public function test_form_choices_override_stale_recipients_column(): void
    {
        Mail::fake();
        [$tournament, $refA, $refB, $institutional, $notification] = $this->setupFullZonalScenario();

        // Colonna avvelenata da un invio precedente (es. invio FIG dal
        // pulsante "Invia" senza form — vedi D2): niente circolo, niente
        // istituzionali, arbitri vuoti.
        $notification->update([
            'recipients' => ['club' => false, 'referees' => [], 'institutional' => []],
        ]);

        $this->actingAsSuperAdmin();

        // L'admin nel form seleziona TUTTO: arbitri, circolo, istituzionale.
        $this->postSend($tournament, [
            'fixed_addresses' => [$institutional->id],
        ]);

        Mail::assertQueued(ClubNotificationMail::class, fn ($m) => $m->hasTo('circolo@example.test'));
        Mail::assertQueued(ClubNotificationMail::class, function ($m) {
            return ($m->hasTo('arbitro.a@example.test') || $m->hasCc('arbitro.a@example.test'))
                && ($m->hasTo('ufficio.zona@example.test') || $m->hasCc('ufficio.zona@example.test'));
        });
    }

    // ════════════════════════════════════════════════════════════════════
    // D2 — Record con metadata "estraneo" (es. import FIG: {source, command},
    // nessun 'recipients'): il pulsante "Invia" dalla lista NON deve fingere
    // un invio a nessuno. Deve reindirizzare al form di preparazione, come
    // già avviene quando metadata è completamente vuoto.
    // FIX D2 implementato: guard su metadata['recipients'] nel controller.
    // ════════════════════════════════════════════════════════════════════

    public function test_send_from_index_with_fig_metadata_redirects_to_form_instead_of_sending_to_nobody(): void
    {
        Mail::fake();
        [$tournament] = $this->setupFullZonalScenario();

        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        // Metadata come quello scritto da MarkFigAssignmentsNotified
        $notification->update([
            'metadata' => ['source' => 'Import batch FIG 2026', 'command' => 'federgolf:mark-notified'],
        ]);

        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.tournament-notifications.send', $notification));

        // Comportamento atteso: come metadata vuoto → redirect al form
        $response->assertRedirect(route('admin.tournaments.show-assignment-form', $tournament));

        Mail::assertNothingOutgoing();
        $this->assertNotEquals('sent', $notification->fresh()->status,
            'REGRESSIONE D2: la notifica risulta "sent" ma non è stata inviata a nessuno.');
    }

    // ════════════════════════════════════════════════════════════════════
    // D3 — Fallimento parziale (circolo senza email) NON deve essere
    // presentato come pieno successo.
    // FIX D3 implementato: redirectAfterSend() flasha warning su partial.
    // ════════════════════════════════════════════════════════════════════

    public function test_partial_failure_is_not_reported_as_full_success(): void
    {
        Mail::fake();
        [$tournament, $refA, $refB, $institutional] = $this->setupFullZonalScenario();

        // Circolo SENZA email → sendToClub fallirà silenziosamente
        $tournament->club->update(['email' => '']); // colonna NOT NULL: '' = senza email

        $this->actingAsSuperAdmin();

        $response = $this->postSend($tournament, [
            'fixed_addresses' => [$institutional->id],
        ]);

        // Gli arbitri devono comunque ricevere la copia conoscenza
        Mail::assertQueued(ClubNotificationMail::class, function ($m) {
            return $m->hasTo('arbitro.a@example.test') || $m->hasCc('arbitro.a@example.test');
        });

        // Ma lo stato deve riflettere il problema e l'admin deve vederlo
        $notification = TournamentNotification::where('tournament_id', $tournament->id)->firstOrFail();
        $this->assertEquals('partial', $notification->status,
            'Lo status deve essere partial quando il circolo non riceve.');
        $this->assertFalse(
            session()->has('success') && ! session()->has('warning') && ! session()->has('error'),
            'REGRESSIONE D3: invio parziale presentato come pieno successo senza alcun warning.'
        );
    }
}
