<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->nullable()
                ->constrained('evaluations')
                ->nullOnDelete();

            $table->string('event', 64);                         // 'evaluation.analysis_complete', etc.
            $table->jsonb('payload');                             // payload sent — NO PHI, reference tokens only
            $table->string('status', 16)->default('pending');    // pending|retrying|delivered|failed
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->jsonb('last_response')->nullable();           // {status_code, body, latency_ms}
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('next_retry_at');                       // for queue retry worker
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
