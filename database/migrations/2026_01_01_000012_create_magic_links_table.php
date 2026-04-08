<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time portal access tokens sent in coordinator notification emails.
 * Raw token never stored — only SHA-256 hash.
 * Tokens expire after 15 minutes and can only be used once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->constrained('evaluations')
                ->cascadeOnDelete();

            $table->string('token_hash', 64)->unique();          // SHA-256(raw_token)
            $table->timestamp('used_at')->nullable();            // null = not yet used
            $table->timestamp('expires_at');                     // 15 minutes from creation
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — immutable after creation

            $table->index(['token_hash']);
            $table->index('expires_at');                         // for cleanup command
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');
    }
};
