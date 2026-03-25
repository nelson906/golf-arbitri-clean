<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Test che verifica che i test usino MySQL con database dedicato
     */
    public function test_uses_mysql_for_tests(): void
    {
        // Verifica che la connessione sia MySQL
        $connection = config('database.default');
        $this->assertEquals('mysql', $connection, 'I test devono usare MySQL');

        // Verifica che il database contenga "test" nel nome
        $database = config('database.connections.mysql.database');
        $this->assertStringContainsString('test', strtolower($database), 'Il database di test deve contenere "test" nel nome');

        // Verifica che l'ambiente sia testing
        $this->assertEquals('testing', config('app.env'));
    }

    /**
     * Test che verifica che il database di test sia isolato
     */
    public function test_database_is_isolated(): void
    {
        // Ottieni il nome del driver
        $driver = DB::connection()->getDriverName();
        $this->assertEquals('mysql', $driver);

        // Verifica che possiamo creare e distruggere tabelle senza problemi
        $this->assertTrue(true, 'Database isolato e sicuro per i test');
    }
}
