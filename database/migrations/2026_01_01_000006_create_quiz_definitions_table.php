<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quiz branching definitions per procedure.
 * No tenant_id — quiz logic is global, managed by AestheticAI.
 * Only one active definition per procedure slug at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('procedure_slug', 64)
                ->constrained('procedures', 'slug')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->jsonb('questions');                          // full branching question tree
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Only one active definition per procedure
            $table->unique(['procedure_slug', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_definitions');
    }
};
