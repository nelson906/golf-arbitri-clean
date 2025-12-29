<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Questa migration documenta che zone_id in tournaments è un campo
     * calcolato dinamicamente da club->zone_id tramite accessor.
     * Il campo nel DB rimane nullable per retrocompatibilità ma non
     * dovrebbe essere usato direttamente.
     */
    public function up(): void
    {
        // Questa migration è solo documentativa per MySQL
        // SQLite non supporta MODIFY, quindi skippiamo per SQLite
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' && Schema::hasColumn('tournaments', 'zone_id')) {
            DB::statement('ALTER TABLE tournaments MODIFY zone_id BIGINT UNSIGNED NULL COMMENT "DEPRECATO: Usare accessor zone_id che calcola da club->zone_id"');
        }

        // Assicurati che tutti i tornei abbiano club_id valido
        // (zone_id verrà calcolato automaticamente dall'accessor)
        // Solo se la tabella esiste (per test)
        if (Schema::hasTable('tournaments')) {
            DB::table('tournaments')
                ->whereNull('club_id')
                ->update(['club_id' => 1]); // Fallback a un club di default
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rimuovi il commento (solo per MySQL)
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' && Schema::hasColumn('tournaments', 'zone_id')) {
            DB::statement('ALTER TABLE tournaments MODIFY zone_id BIGINT UNSIGNED NULL');
        }
    }
};
