<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_emails', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('category');
            $table->text('description')->nullable();
            $table->boolean('is_global')->default(false);
            $table->foreignId('zone_id')->nullable()->constrained('zones');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('zone_id');
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('original_name');
            $table->string('file_path');
            $table->bigInteger('file_size');
            $table->string('mime_type');
            $table->enum('category', ['general', 'tournament', 'regulation', 'form', 'template'])->default('general');
            $table->enum('type', ['pdf', 'document', 'spreadsheet', 'image', 'text', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->onDelete('cascade');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('cascade');
            $table->foreignId('uploader_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_public')->default(false);
            $table->integer('download_count')->default(0);
            $table->timestamps();

            $table->index(['category', 'type'], 'idx_documents_category_type');
            $table->index(['zone_id', 'is_public'], 'idx_documents_zone_public');
            $table->index('uploader_id', 'idx_documents_uploader');
            $table->index('tournament_id', 'idx_documents_tournament');
            $table->index(['created_at', 'category'], 'idx_documents_date_category');
            $table->index('file_size', 'idx_documents_size');
        });

        Schema::create('communications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['announcement', 'alert', 'maintenance', 'info'])->default('info');
            $table->enum('status', ['draft', 'published', 'expired'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type'], 'idx_communications_status_type');
            $table->index(['zone_id', 'status'], 'idx_communications_zone_status');
            $table->index('scheduled_at', 'idx_communications_scheduled');
            $table->index(['expires_at', 'status'], 'idx_communications_expires_status');
            $table->index('author_id', 'idx_communications_author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('institutional_emails');
    }
};
