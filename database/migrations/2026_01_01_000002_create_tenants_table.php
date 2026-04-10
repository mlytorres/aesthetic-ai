<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 63)->unique();                // subdomain identifier
            $table->string('name', 255);                         // clinic display name
            $table->foreignUuid('plan_id')->constrained('plans');
            $table->string('webhook_url', 2048)->nullable();     // CRM webhook target
            $table->string('webhook_secret', 64)->nullable();    // HMAC-SHA256 signing key
            $table->jsonb('settings')->default('{}');            // theme, logo, procedures, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');                               // fast lookup in TenantMiddleware
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
