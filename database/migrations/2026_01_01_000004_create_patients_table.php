<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patient identity records.
 * All PHI columns store AES-256-GCM ciphertext — never raw values.
 * Hashed columns (email_hash, name_hash) are HMAC digests for dedup/search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));

            // ✅ REQUIRED: tenant scope
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // 🔒 PHI columns — encrypted at application layer (EncryptedString cast)
            $table->text('name_encrypted')->nullable();
            $table->text('email_encrypted');
            $table->text('phone_encrypted')->nullable();
            $table->text('dob_encrypted')->nullable();

            // Hashed identifiers for deduplication without decrypting
            $table->string('name_hash', 64)->nullable();
            $table->string('email_hash', 64);                    // HMAC-SHA256

            $table->string('external_crm_id', 128)->nullable(); // ID in clinic's CRM
            $table->string('created_via', 32)->default('widget'); // widget|import|api

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'email_hash']);
            $table->index(['tenant_id', 'external_crm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
