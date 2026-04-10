<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds AI simulation columns to the evaluations table.
 *
 * simulation_status  — tracks request lifecycle: null | pending | processing | complete | failed
 * simulation_data    — JSON payload with before/after S3 URLs, model used, prompt, timestamps
 * simulation_requested_at — when the patient/staff requested a simulation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->string('simulation_status')->nullable()->after('status');
            $table->jsonb('simulation_data')->nullable()->after('simulation_status');
            $table->timestamp('simulation_requested_at')->nullable()->after('simulation_data');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropColumn(['simulation_status', 'simulation_data', 'simulation_requested_at']);
        });
    }
};
