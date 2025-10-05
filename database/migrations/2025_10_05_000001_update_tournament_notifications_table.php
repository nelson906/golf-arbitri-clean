<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Rimuovi colonne vecchie
            $table->dropColumn([
                'referee_list',
                'total_recipients',
                'templates_used',
                'error_message',
                'prepared_at'
            ]);

            // Aggiungi nuove colonne
            $table->json('recipients')->nullable()->after('tournament_id');
            $table->json('content')->nullable()->after('recipients');
            $table->json('documents')->nullable()->after('content');
            $table->json('metadata')->nullable()->after('documents');
            $table->string('status')->default('pending')->after('metadata');
            $table->foreignId('sent_by')->nullable()->constrained('users');
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('tournament_notifications', function (Blueprint $table) {
            // Ripristina colonne vecchie
            $table->string('referee_list')->nullable();
            $table->integer('total_recipients')->nullable();
            $table->json('templates_used')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('prepared_at')->nullable();

            // Rimuovi colonne nuove
            $table->dropColumn([
                'recipients',
                'content',
                'documents',
                'metadata',
                'status',
                'sent_by',
                'sent_at'
            ]);
        });
    }
};