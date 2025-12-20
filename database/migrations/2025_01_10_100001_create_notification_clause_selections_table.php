<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_clause_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_notification_id')
                ->constrained('tournament_notifications')
                ->onDelete('cascade')
                ->name('fk_clause_sel_tournament_notif');  // ← NOME BREVE CUSTOM
            $table->string('placeholder_code', 50);
            $table->foreignId('clause_id')
                ->constrained('notification_clauses')
                ->onDelete('cascade')
                ->name('fk_clause_sel_clause');  // ← NOME BREVE CUSTOM
            $table->timestamps();

            $table->unique(
                ['tournament_notification_id', 'placeholder_code'],
                'uniq_notif_placeholder'  // ← NOME BREVE CUSTOM
            );
            $table->index('placeholder_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_clause_selections');
    }
};
