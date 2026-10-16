<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Busca sem diferenciar acentos ("licitacao" encontra "Licitação").
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');

        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title')->nullable();
            $table->text('subject')->nullable();
            $table->string('interested_party')->nullable();
            $table->date('produced_at')->nullable();
            $table->foreignId('classification_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('retention_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('secrecy', 20);
            $table->string('status', 20);
            $table->timestampTz('captured_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['unit_id', 'secrecy']);
            $table->index('status');
            $table->index('produced_at');
        });

        Schema::create('document_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('processing_status', 20);
            $table->unsignedInteger('page_count')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampTz('last_fixity_check_at')->nullable();
            $table->boolean('fixity_ok')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            // Duplicidade garantida pelo banco, não só pela aplicação.
            $table->unique(['document_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('documents');
    }
};
