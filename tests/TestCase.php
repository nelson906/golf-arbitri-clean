<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Setup eseguito prima di ogni test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Verifica che stiamo usando il database di test
        $this->assertDatabaseIsTest();
    }

    /**
     * Verifica che il database usato sia quello di test
     * Previene la distruzione accidentale del database di sviluppo
     */
    protected function assertDatabaseIsTest(): void
    {
        $connection = config('database.default');

        // Se non è SQLite in memoria, verifica che sia un database di test
        if ($connection !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $dbName = config("database.connections.{$connection}.database");

            // Il database deve contenere 'test' nel nome o essere :memory:
            $this->assertTrue(
                str_contains(strtolower($dbName), 'test') || $dbName === ':memory:',
                "⚠️ ATTENZIONE: Stai usando il database '{$dbName}' per i test! Usa un database di test separato."
            );
        }
    }
}
