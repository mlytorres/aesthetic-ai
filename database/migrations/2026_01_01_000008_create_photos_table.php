<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient photos metadata.
 * Actual files live in S3 (KMS encrypted). s3_key is encrypted at application layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // ✅ REQUIRED: tenant scope
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->constrained('evaluations')
                ->cascadeOnDelete();

            $table->string('type', 32);                         // front|left_profile|right_profile|additional
            $table->text('s3_key');                             // 🔒 PHI: encrypted S3 object path
            $table->string('s3_key_hash', 64);                  // HMAC hash for integrity verification
            $table->unsignedSmallInteger('quality_score')->nullable(); // 0–100 from validation job
            $table->string('analysis_status', 32)->default('pending');
            // pending | processing | complete | failed | skipped
            $table->jsonb('capture_metadata')->nullable();       // device, orientation, lighting estimate
            $table->timestamp('taken_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
