<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\InstitutionalEmail;
use App\Models\NotificationClause;
use App\Models\NotificationClauseSelection;
use App\Models\TournamentNotification;
use Tests\Concerns\CollectsIds;
use Tests\TestCase;

/**
 * Impostazioni delle notifiche (super admin):
 *  - Email istituzionali: gli indirizzi che si possono aggiungere in copia
 *  - Clausole: i testi che entrano nelle lettere e nelle convocazioni
 */
class NotificationSettingsTest extends TestCase
{
    use CollectsIds;

    private int $emails = 0;

    private int $clauses = 0;

    /**
     * @param  array<string, mixed>  $over
     */
    private function email(array $over = []): InstitutionalEmail
    {
        $n = ++$this->emails;

        return InstitutionalEmail::create(array_merge([
            'name' => "Ufficio {$n}",
            'email' => "ufficio{$n}@example.test",
            'category' => 'federazione',
            'is_active' => true,
        ], $over));
    }

    /**
     * @param  array<string, mixed>  $over
     */
    private function clause(array $over = []): NotificationClause
    {
        $n = ++$this->clauses;

        return NotificationClause::create(array_merge([
            'code' => "clausola_{$n}",
            'category' => 'spese',
            'title' => "Clausola {$n}",
            'content' => 'Testo',
            'applies_to' => 'club',
            'is_active' => true,
            'sort_order' => $n,
        ], $over));
    }

    /**
     * @param  \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>  $response
     * @return list<int>
     */
    private function ids($response, string $key): array
    {
        $page = $response->viewData($key);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $page);

        return $this->sortedIds($page);
    }

    // ── Accesso ──────────────────────────────────────────────────────────────

    public function test_only_super_admin_opens_the_settings(): void
    {
        foreach ([$this->createZoneAdmin(1), $this->createNationalAdmin(), $this->createReferee()] as $user) {
            $this->actingAs($user)->get(route('super-admin.institutional-emails.index'))->assertForbidden();
            $this->actingAs($user)->get(route('super-admin.clauses.index'))->assertForbidden();
        }
    }

    // ── Email istituzionali ──────────────────────────────────────────────────

    public function test_emails_list_filters(): void
    {
        $a = $this->email(['name' => 'Segreteria Campionati', 'category' => 'comitati', 'zone_id' => null]);
        $b = $this->email(['description' => 'riceve i referti', 'category' => 'zone', 'zone_id' => 2]);
        $c = $this->email(['is_active' => false, 'zone_id' => 2]);
        $this->actingAsSuperAdmin();

        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['search' => 'Campionati'])), 'institutionalEmails'));
        $this->assertSame([$b->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['search' => 'referti'])), 'institutionalEmails'));
        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['category' => 'comitati'])), 'institutionalEmails'));
        $this->assertSame([$b->id, $c->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['zone_id' => 2])), 'institutionalEmails'));
        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['zone_id' => 'null'])), 'institutionalEmails'));
        $this->assertSame([$c->id], $this->ids($this->get(route('super-admin.institutional-emails.index', ['is_active' => '0'])), 'institutionalEmails'));
    }

    public function test_email_create_form_opens(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.institutional-emails.create'))
            ->assertOk()
            ->assertViewHas('categories', InstitutionalEmail::CATEGORIES);
    }

    public function test_email_store_is_active_by_default(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.institutional-emails.store'), [
                'name' => 'Ufficio Tecnico',
                'email' => 'tecnico@example.test',
                'category' => 'federazione',
                'zone_id' => 3,
            ])
            ->assertRedirect(route('super-admin.institutional-emails.index'))
            ->assertSessionHas('success', 'Email istituzionale creata con successo.');

        $this->assertDatabaseHas('institutional_emails', ['email' => 'tecnico@example.test', 'is_active' => 1, 'zone_id' => 3]);
    }

    public function test_email_store_validation(): void
    {
        $this->email(['email' => 'doppia@example.test']);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.institutional-emails.store'), [
                'name' => '',
                'email' => 'doppia@example.test',
                'category' => 'boh',
                'zone_id' => 999,
            ])
            ->assertSessionHasErrors(['name', 'email', 'category', 'zone_id']);

        $this->actingAsSuperAdmin()
            ->post(route('super-admin.institutional-emails.store'), [
                'name' => 'X',
                'email' => 'non-una-email',
                'category' => 'altro',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_email_edit_and_update_unchecked_means_inactive(): void
    {
        $e = $this->email();
        $this->actingAsSuperAdmin();

        $this->get(route('super-admin.institutional-emails.edit', $e))->assertOk();

        $this->put(route('super-admin.institutional-emails.update', $e), [
            'name' => 'Nuovo nome',
            'email' => $e->email,
            'category' => 'altro',
        ])
            ->assertRedirect(route('super-admin.institutional-emails.index'))
            ->assertSessionHas('success', 'Email istituzionale aggiornata con successo.');

        $e->refresh();
        $this->assertSame('Nuovo nome', $e->name);
        $this->assertSame('altro', $e->category);
        $this->assertFalse($e->is_active);
    }

    public function test_email_update_cannot_take_address_of_another(): void
    {
        $a = $this->email();
        $b = $this->email();

        $this->actingAsSuperAdmin()
            ->put(route('super-admin.institutional-emails.update', $a), [
                'name' => 'X',
                'email' => $b->email,
                'category' => 'altro',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_email_destroy(): void
    {
        $e = $this->email();

        $this->actingAsSuperAdmin()
            ->delete(route('super-admin.institutional-emails.destroy', $e))
            ->assertRedirect(route('super-admin.institutional-emails.index'))
            ->assertSessionHas('success', 'Email istituzionale eliminata con successo.');

        $this->assertDatabaseMissing('institutional_emails', ['id' => $e->id]);
    }

    // ── Clausole ─────────────────────────────────────────────────────────────

    public function test_clauses_list_filters_and_order(): void
    {
        $b = $this->clause(['code' => 'rimborso_km', 'title' => 'Rimborso chilometrico', 'sort_order' => 2]);
        $a = $this->clause(['category' => 'logistica', 'applies_to' => 'referee', 'content' => 'parcheggio riservato', 'sort_order' => 1]);
        $off = $this->clause(['is_active' => false, 'sort_order' => 3]);
        $this->actingAsSuperAdmin();

        $all = $this->get(route('super-admin.clauses.index'));
        $all->assertOk();
        $page = $all->viewData('clauses');
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\Paginator::class, $page);
        $this->assertSame([$a->id, $b->id, $off->id], collect($page->items())->pluck('id')->all());

        $this->assertSame([$b->id], $this->ids($this->get(route('super-admin.clauses.index', ['search' => 'rimborso_km'])), 'clauses'));
        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.clauses.index', ['search' => 'parcheggio'])), 'clauses'));
        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.clauses.index', ['category' => 'logistica'])), 'clauses'));
        $this->assertSame([$a->id], $this->ids($this->get(route('super-admin.clauses.index', ['applies_to' => 'referee'])), 'clauses'));
        $this->assertSame([$off->id], $this->ids($this->get(route('super-admin.clauses.index', ['is_active' => '0'])), 'clauses'));
    }

    public function test_clause_create_form_opens(): void
    {
        $this->actingAsSuperAdmin()
            ->get(route('super-admin.clauses.create'))
            ->assertOk()
            ->assertViewHas('appliesTo', NotificationClause::APPLIES_TO);
    }

    public function test_clause_store_defaults(): void
    {
        $this->actingAsSuperAdmin()
            ->post(route('super-admin.clauses.store'), [
                'code' => 'nuova_clausola',
                'category' => 'spese',
                'title' => 'Nuova',
                'content' => 'Testo nuovo',
                'applies_to' => 'all',
            ])
            ->assertRedirect(route('super-admin.clauses.index'))
            ->assertSessionHas('success', 'Clausola creata con successo.');

        $this->assertDatabaseHas('notification_clauses', ['code' => 'nuova_clausola', 'is_active' => 1, 'sort_order' => 0]);
    }

    public function test_clause_code_must_be_lowercase_and_unique(): void
    {
        $this->clause(['code' => 'gia_usato']);
        $this->actingAsSuperAdmin();

        foreach (['Con Spazi', 'MAIUSCOLO', 'gia_usato'] as $code) {
            $this->post(route('super-admin.clauses.store'), [
                'code' => $code,
                'category' => 'spese',
                'title' => 'T',
                'content' => 'C',
                'applies_to' => 'club',
            ])->assertSessionHasErrors('code');
        }

        $this->post(route('super-admin.clauses.store'), [
            'code' => 'ok_code',
            'category' => 'boh',
            'title' => 'T',
            'content' => 'C',
            'applies_to' => 'nessuno',
            'sort_order' => -1,
        ])->assertSessionHasErrors(['category', 'applies_to', 'sort_order']);
    }

    public function test_clause_edit_shows_how_many_notifications_use_it(): void
    {
        $c = $this->clause();
        $n = TournamentNotification::create(['tournament_id' => $this->createTournament()->id, 'status' => 'pending']);
        NotificationClauseSelection::create(['tournament_notification_id' => $n->id, 'clause_id' => $c->id, 'placeholder_code' => 'spese']);

        $this->actingAsSuperAdmin()
            ->get(route('super-admin.clauses.edit', $c))
            ->assertOk()
            ->assertViewHas('usageCount', 1);
    }

    public function test_clause_update_keeps_own_code_and_unchecked_means_inactive(): void
    {
        $c = $this->clause(['code' => 'mio_codice']);

        $this->actingAsSuperAdmin()
            ->put(route('super-admin.clauses.update', $c), [
                'code' => 'mio_codice',
                'category' => 'altro',
                'title' => 'Titolo nuovo',
                'content' => 'Contenuto nuovo',
                'applies_to' => 'institutional',
                'sort_order' => 7,
            ])
            ->assertRedirect(route('super-admin.clauses.index'))
            ->assertSessionHas('success', 'Clausola aggiornata con successo.');

        $c->refresh();
        $this->assertSame('Titolo nuovo', $c->title);
        $this->assertSame('institutional', $c->applies_to);
        $this->assertSame(7, $c->sort_order);
        $this->assertFalse($c->is_active);
    }

    public function test_clause_update_cannot_take_code_of_another(): void
    {
        $a = $this->clause();
        $b = $this->clause();

        $this->actingAsSuperAdmin()
            ->put(route('super-admin.clauses.update', $a), [
                'code' => $b->code,
                'category' => 'spese',
                'title' => 'T',
                'content' => 'C',
                'applies_to' => 'club',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_clause_in_use_cannot_be_deleted(): void
    {
        $c = $this->clause();
        $n = TournamentNotification::create(['tournament_id' => $this->createTournament()->id, 'status' => 'pending']);
        NotificationClauseSelection::create(['tournament_notification_id' => $n->id, 'clause_id' => $c->id, 'placeholder_code' => 'spese']);

        $this->actingAsSuperAdmin()
            ->from(route('super-admin.clauses.index'))
            ->delete(route('super-admin.clauses.destroy', $c))
            ->assertRedirect(route('super-admin.clauses.index'))
            ->assertSessionHas('warning', 'La clausola è utilizzata in 1 notifiche. Eliminazione non consentita.');

        $this->assertDatabaseHas('notification_clauses', ['id' => $c->id]);
    }

    public function test_unused_clause_is_deleted(): void
    {
        $c = $this->clause();

        $this->actingAsSuperAdmin()
            ->delete(route('super-admin.clauses.destroy', $c))
            ->assertRedirect(route('super-admin.clauses.index'))
            ->assertSessionHas('success', 'Clausola eliminata con successo.');

        // Eliminazione definitiva (2026-10-17): il codice torna libero
        $this->assertDatabaseMissing('notification_clauses', ['id' => $c->id]);
    }

    public function test_clause_toggle_active_both_ways(): void
    {
        $c = $this->clause(['is_active' => true]);
        $this->actingAsSuperAdmin();

        $this->from(route('super-admin.clauses.index'))
            ->post(route('super-admin.clauses.toggle-active', $c))
            ->assertRedirect(route('super-admin.clauses.index'))
            ->assertSessionHas('success', 'Clausola disattivata con successo.');
        $this->assertFalse(NotificationClause::findOrFail($c->id)->is_active);

        $this->post(route('super-admin.clauses.toggle-active', $c))
            ->assertSessionHas('success', 'Clausola attivata con successo.');
        $this->assertTrue(NotificationClause::findOrFail($c->id)->is_active);
    }
}
