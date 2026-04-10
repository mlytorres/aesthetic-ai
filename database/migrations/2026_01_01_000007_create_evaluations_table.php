<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ✅ REQUIRED: tenant scope
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('patient_id')
                ->constrained('patients')
                ->cascadeOnDelete();

            $table->string('procedure_slug', 64)
                ->constrained('procedures', 'slug');

            $table->string('status', 32)->default('draft');
            // draft | submitted | analyzing | complete | contacted | booked | no_show | not_a_fit | failed

            $table->jsonb('quiz_answers')->default('{}');        // all intake responses
            $table->jsonb('analysis_data')->default('{}');       // AI pipeline output (landmarks, proportions, recommendations)

            $table->unsignedSmallInteger('lead_score')->nullable(); // 0–100, null until AI complete
            $table->string('priority', 16)->nullable();          // urgent|high|medium|standard

            $table->string('secure_token', 64)->unique();        // SHA-256 random — used in magic links + webhooks

            $table->text('coordinator_notes')->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->string('external_id', 128)->nullable();      // CRM reference after webhook sync

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Performance indexes
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority', 'lead_score']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('secure_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
