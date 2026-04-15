<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->constrained()
                ->cascadeOnDelete();

            // The staff member who scheduled the consultation
            $table->foreignId('coordinator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);

            // Daily.co room details
            $table->string('daily_room_name')->unique();
            $table->string('daily_room_url');

            // Secure token for the public patient-facing join page (/consult/{token})
            $table->uuid('token')->unique();

            $table->string('status')->default('scheduled'); // scheduled | active | completed | cancelled

            $table->text('notes')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'scheduled_at']);
            $table->index(['evaluation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
