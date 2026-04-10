<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bearer tokens for the External REST API v1.
 * Raw token shown once at creation — only SHA-256 hash stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name', 128);
            $table->string('token_hash', 64)->unique();          // SHA-256(raw_token) — never stored raw
            $table->text('scopes')->default('{}');               // PostgreSQL text[] as JSON
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();         // null = no expiry
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
