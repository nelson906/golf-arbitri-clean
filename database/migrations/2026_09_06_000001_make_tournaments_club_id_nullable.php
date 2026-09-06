<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rende `tournaments.club_id` nullable per i tornei T.B.A.
 *
 * Nel calendario federale una gara puo' avere data e zona gia' fissate mentre
 * il circolo e' ancora "To Be Assigned". Con `club_id` NOT NULL quei tornei non
 * erano rappresentabili: Tournaments2026Seeder li contava fra gli "⊘ Saltati"
 * e li buttava (`if ($data['circolo'] === 'T.B.A.') { continue; }`), quindi
 * sparivano dal calendario finche' qualcuno non li reinseriva a mano.
 *
 * `zone_id` era gia' nullable ed e' quello che tiene in piedi la visibilita':
 * TournamentVisibility legge Tournament::getZoneIdAttribute(), che usa il club
 * quando c'e' e altrimenti la colonna zone_id. Un torneo T.B.A. con la zona
 * valorizzata resta quindi visibile al proprio admin di zona.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedBigInteger('club_id')->nullable()->change();
        });
    }

    /**
     * ATTENZIONE: il rollback fallisce se nel frattempo esiste anche un solo
     * torneo con club_id null. E' voluto: tornare indietro significa decidere
     * cosa farne, e la migration non puo' deciderlo al posto di chi la lancia.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedBigInteger('club_id')->nullable(false)->change();
        });
    }
};
