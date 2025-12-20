<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Prima aggiungi le nuove colonne
        Schema::table('tournament_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('tournament_notifications', 'recipients')) {
                $table->json('recipients')->nullable()->after('tournament_id');
            }
            if (! Schema::hasColumn('tournament_notifications', 'content')) {
                $table->json('content')->nullable()->after('recipients');
            }
            if (! Schema::hasColumn('tournament_notifications', 'documents')) {
                $table->json('documents')->nullable()->after('content');
            }
            if (! Schema::hasColumn('tournament_notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('documents');
            }
            if (! Schema::hasColumn('tournament_notifications', 'status')) {
                $table->string('status')->default('pending')->after('metadata');
            }
            if (! Schema::hasColumn('tournament_notifications', 'sent_by')) {
                $table->foreignId('sent_by')->nullable()->constrained('users');
            }
            if (! Schema::hasColumn('tournament_notifications', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
        });

        // Poi rimuovi le vecchie colonne se esistono
        Schema::table('tournament_notifications', function (Blueprint $table) {
            $toDrop = [];

            if (Schema::hasColumn('tournament_notifications', 'referee_list')) {
                $toDrop[] = 'referee_list';
            }
            if (Schema::hasColumn('tournament_notifications', 'total_recipients')) {
                $toDrop[] = 'total_recipients';
            }
            if (Schema::hasColumn('tournament_notifications', 'templates_used')) {
                $toDrop[] = 'templates_used';
            }
            if (Schema::hasColumn('tournament_notifications', 'error_message')) {
                $toDrop[] = 'error_message';
            }
            if (Schema::hasColumn('tournament_notifications', 'prepared_at')) {
                $toDrop[] = 'prepared_at';
            }

            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }

    public function down()
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Rimuovi colonne nuove se esistono
            $toDrop = [];
            foreach (['recipients', 'content', 'documents', 'metadata', 'status', 'sent_by', 'sent_at'] as $column) {
                if (Schema::hasColumn('tournament_notifications', $column)) {
                    $toDrop[] = $column;
                }
            }
            if (! empty($toDrop)) {
                $table->dropColumn($toDrop);
            }

            // Ripristina colonne vecchie se non esistono
            if (! Schema::hasColumn('tournament_notifications', 'referee_list')) {
                $table->string('referee_list')->nullable();
            }
            if (! Schema::hasColumn('tournament_notifications', 'total_recipients')) {
                $table->integer('total_recipients')->nullable();
            }
            if (! Schema::hasColumn('tournament_notifications', 'templates_used')) {
                $table->json('templates_used')->nullable();
            }
            if (! Schema::hasColumn('tournament_notifications', 'error_message')) {
                $table->text('error_message')->nullable();
            }
            if (! Schema::hasColumn('tournament_notifications', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable();
            }
        });
    }
};
