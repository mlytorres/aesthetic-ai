<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds intake funnel step tracking to evaluations.
 *
 * funnel_step records the furthest step the patient reached in the wizard:
 *   1 — procedure selected   (evaluation created)
 *   2 — quiz completed
 *   3 — photos uploaded      (at least one photo accepted)
 *   4 — submitted            (contact info + consent recorded)
 *
 * Rows never decrease — each controller update uses MAX(current, new).
 * This lets the analytics dashboard compute step-level drop-off rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->tinyInteger('funnel_step')
                ->unsigned()
                ->default(1)
                ->after('status')
                ->comment('Furthest intake wizard step reached (1=procedure, 2=quiz, 3=photos, 4=submitted)');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropColumn('funnel_step');
        });
    }
};
