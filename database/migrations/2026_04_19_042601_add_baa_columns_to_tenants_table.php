<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * HIPAA: Records when a Business Associate Agreement was executed with the clinic.
     * Optional signed PDF stored on the private disk; path may be application-encrypted at rest.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('baa_signed_at')->nullable()->after('settings');
            $table->text('baa_document_path')->nullable()->after('baa_signed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['baa_signed_at', 'baa_document_path']);
        });
    }
};
