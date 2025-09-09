<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Aggiungi assignment_id solo se non esiste già
            if (!Schema::hasColumn('notifications', 'assignment_id')) {
                $table->foreignId('assignment_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('assignments')
                    ->onDelete('cascade');
                    
                $table->index(['assignment_id', 'recipient_type']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'assignment_id')) {
                // Prima rimuovi gli indici
                $table->dropIndex(['assignment_id', 'recipient_type']);
                
                // Poi rimuovi la foreign key e la colonna
                $table->dropForeign(['assignment_id']);
                $table->dropColumn('assignment_id');
            }
        });
    }
};
