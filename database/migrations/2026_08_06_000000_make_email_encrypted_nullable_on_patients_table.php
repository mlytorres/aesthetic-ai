<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patients can legitimately have no email on file (e.g. phone-only intake).
 * The `email_encrypted` column was created NOT NULL, which conflicts with
 * that case — Laravel's `encrypted` cast stores a literal NULL when the
 * attribute is null (it does not encrypt an empty value), so any patient
 * without an email fails the NOT NULL constraint at the DB layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('email_encrypted')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->text('email_encrypted')->nullable(false)->change();
        });
    }
};
