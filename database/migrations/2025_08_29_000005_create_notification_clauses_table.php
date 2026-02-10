<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_clauses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('category', 50);
            $table->string('title');
            $table->text('content');
            $table->enum('applies_to', ['club', 'referee', 'institutional', 'all'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
            $table->index(['applies_to', 'is_active']);
            $table->index('category');
        });

        Schema::create('notification_clause_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_notification_id')->unsigned();
            $table->string('placeholder_code', 50);
            $table->foreignId('clause_id')->unsigned();
            $table->timestamps();

            $table->unique(
                ['tournament_notification_id', 'placeholder_code'],
                'uniq_notif_placeholder'
            );
            $table->index('placeholder_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_clause_selections');
        Schema::dropIfExists('notification_clauses');
    }
};
