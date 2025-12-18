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
        // Aggiungi commento al campo per documentare che è deprecato
        if (Schema::hasColumn('tournaments', 'zone_id')) {
            DB::statement('ALTER TABLE tournaments MODIFY zone_id BIGINT UNSIGNED NULL COMMENT "DEPRECATO: Usare accessor zone_id che calcola da club->zone_id"');
        }

        // Assicurati che tutti i tornei abbiano club_id valido
        // (zone_id verrà calcolato automaticamente dall'accessor)
        DB::table('tournaments')
            ->whereNull('club_id')
            ->update(['status' => 'draft']); // Marca come bozza i tornei senza club
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rimuovi il commento
        if (Schema::hasColumn('tournaments', 'zone_id')) {
            DB::statement('ALTER TABLE tournaments MODIFY zone_id BIGINT UNSIGNED NULL');
        }
    }
};
