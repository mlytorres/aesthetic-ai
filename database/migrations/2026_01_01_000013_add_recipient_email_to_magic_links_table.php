<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds recipient_email to magic_links so the controller can log in the
 * correct coordinator when the link is clicked (instead of always using
 * the first owner/coordinator for the tenant).
 *
 * Nullable — existing rows (if any) will have null and fall back to the
 * tenant's first owner/coordinator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            // Store the email address the notification was sent to.
            // Used to resolve which User to authenticate on link consumption.
            // NOT encrypted — email used only to match against users.email.
            $table->string('recipient_email')->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('magic_links', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });
    }
};
