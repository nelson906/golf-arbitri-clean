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
            $table->string('category', 50)->index();
            $table->string('title');
            $table->text('content');
            $table->enum('applies_to', ['club', 'referee', 'institutional', 'all'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'is_active']);
            $table->index(['applies_to', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_clauses');
    }
};
