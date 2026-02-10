<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['assignment', 'convocation', 'club', 'institutional'])->default('assignment');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->text('body');
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->foreignId('tournament_type_id')->nullable()->constrained('tournament_types');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index(['zone_id', 'type']);
            $table->index(['tournament_type_id', 'type']);
        });

        Schema::create('letterheads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('cascade');
            $table->string('logo_path')->nullable();
            $table->text('header_text')->nullable();
            $table->text('header_content')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('footer_content')->nullable();
            $table->json('contact_info')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['zone_id', 'is_active']);
            $table->index(['zone_id', 'is_default']);
            $table->index('updated_by');
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterheads');
        Schema::dropIfExists('letter_templates');
    }
};
