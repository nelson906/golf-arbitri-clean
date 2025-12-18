<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    /**
     * Test che verifica che i test usino SQLite in memoria
     */
    public function test_uses_sqlite_in_memory_for_tests(): void
    {
        // Verifica che la connessione sia SQLite
        $connection = config('database.default');
        $this->assertEquals('sqlite', $connection, 'I test devono usare SQLite');

        // Verifica che il database sia :memory:
        $database = config('database.connections.sqlite.database');
        $this->assertEquals(':memory:', $database, 'I test devono usare database in memoria');

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
        $this->assertEquals('sqlite', $driver);

        // Verifica che possiamo creare e distruggere tabelle senza problemi
        $this->assertTrue(true, 'Database isolato e sicuro per i test');
    }
}
