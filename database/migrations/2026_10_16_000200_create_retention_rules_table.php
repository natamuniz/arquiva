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
        Schema::create('retention_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('classification_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedSmallInteger('current_years');
            $table->unsignedSmallInteger('intermediate_years');
            $table->string('final_destination', 30);
            $table->text('notes')->nullable();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['classification_id', 'version']);
        });

        // No máximo uma versão vigente por classe, garantido pelo banco (protege contra corrida).
        DB::statement(
            'CREATE UNIQUE INDEX retention_rules_one_current_per_classification '
            .'ON retention_rules (classification_id) WHERE valid_to IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_rules');
    }
};
