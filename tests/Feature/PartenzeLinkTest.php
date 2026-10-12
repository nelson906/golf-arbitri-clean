<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voce "Simulatore Tempi Partenza": link firmato verso Schema Partenze.
 */
class PartenzeLinkTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segreto-di-prova-condiviso-tra-le-due-app';

    private function configura(): void
    {
        config([
            'services.partenze.url'        => 'https://esempio.test/partenze/',
            'services.partenze.sso_secret' => self::SECRET,
        ]);
    }

    public function test_reindirizza_a_partenze_con_token_firmato_per_l_utente(): void
    {
        $this->configura();
        $user = User::factory()->referee()->create(['email' => 'arbitro@example.com', 'name' => 'Mario Rossi']);

        $location = $this->actingAs($user)->get(route('user.partenze'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertIsString($location);
        $this->assertStringStartsWith('https://esempio.test/partenze/sso?token=', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $token = $query['token'];
        $this->assertIsString($token);
        [$body, $firma] = explode('.', $token);

        $attesa = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertSame($attesa, $firma);

        $dati = json_decode((string) base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertIsArray($dati);
        $this->assertSame('partenze', $dati['aud']);
        $this->assertSame('arbitro@example.com', $dati['email']);
        $this->assertSame('Mario Rossi', $dati['name']);
        $this->assertEqualsWithDelta(time(), $dati['iat'], 5);
        $this->assertGreaterThanOrEqual(16, strlen($dati['nonce']));
    }

    public function test_due_click_danno_token_diversi(): void
    {
        $this->configura();
        $user = User::factory()->referee()->create();

        $a = $this->actingAs($user)->get(route('user.partenze'))->headers->get('Location');
        $b = $this->actingAs($user)->get(route('user.partenze'))->headers->get('Location');

        $this->assertNotSame($a, $b);
    }

    public function test_richiede_login(): void
    {
        $this->configura();

        $this->get(route('user.partenze'))->assertRedirect(route('login'));
    }

    public function test_non_configurato_pagina_inesistente_e_voce_nascosta(): void
    {
        config(['services.partenze.url' => null, 'services.partenze.sso_secret' => null]);
        $user = User::factory()->referee()->create();

        $this->actingAs($user)->get(route('user.partenze'))->assertNotFound();
        $this->followingRedirects()->actingAs($user)->get(route('dashboard'))->assertDontSee('Simulatore Tempi Partenza');
    }

    public function test_configurato_voce_di_menu_visibile(): void
    {
        $this->configura();
        $user = User::factory()->referee()->create();

        $this->followingRedirects()->actingAs($user)->get(route('dashboard'))
            ->assertSee('Simulatore Tempi Partenza')
            ->assertSee(route('user.partenze'), false);
    }
}
