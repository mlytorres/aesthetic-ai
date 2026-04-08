<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global lookup table for supported procedures.
 * No tenant_id — this is a shared configuration table managed by AestheticAI.
 * Tenants enable/disable procedures via tenants.settings->>'procedures_enabled'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->string('slug', 64)->primary();               // 'rhinoplasty', 'bbl', etc.
            $table->string('label', 128);
            $table->string('category', 32);                      // 'face' | 'body'
            $table->jsonb('photo_protocol')->default('[]');      // required photo angles + guides
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
