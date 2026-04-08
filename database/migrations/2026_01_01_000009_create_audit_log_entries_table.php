<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only HIPAA audit log. Never deleted, never updated.
 * Uses bigserial (auto-increment) for performance on high-volume inserts.
 * NO updated_at — immutable by design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_entries', function (Blueprint $table) {
            $table->bigIncrements('id');                         // high-volume: bigserial not UUID

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('user_id')                         // null for patient/system actions
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 128);                       // e.g., 'evaluation.photos.viewed'
            $table->string('subject_type', 64)->nullable();      // 'Evaluation', 'Patient', etc.
            $table->uuid('subject_id')->nullable();

            $table->jsonb('metadata')->default('{}');            // safe context — NO PHI ever
            $table->string('ip_address', 45)->nullable();        // cast to inet below; varchar(45) covers IPv6
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();
            // NO updated_at — this table is immutable

            $table->index(['tenant_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });

        // Upgrade the ip_address column to PostgreSQL's native inet type.
        // Blueprint has no first-class method for inet, so we use a raw ALTER.
        // The USING clause safely casts NULL → NULL; non-null values are validated by PostgreSQL.
        DB::statement(
            'ALTER TABLE audit_log_entries ALTER COLUMN ip_address TYPE inet USING ip_address::inet'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_entries');
    }
};
