<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Decisione 2026-10-03 (P15): la registrazione libera non esiste piu'.
 * Gli account li crea l'amministratore di zona.
 */
class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_post_does_not_create_users(): void
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
        $this->assertGuest();
    }
}
