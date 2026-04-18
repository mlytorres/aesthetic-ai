<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliate_partners')) {
            return;
        }

        if (Schema::hasColumn('affiliate_partners', 'portal_access_token')) {
            return;
        }

        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->string('portal_access_token', 80)->nullable()->after('status');
        });

        // Backfill generated tokens for existing rows before making NOT NULL + unique.
        DB::table('affiliate_partners')
            ->select(['id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $row): void {
                DB::table('affiliate_partners')
                    ->where('id', $row->id)
                    ->update(['portal_access_token' => bin2hex(random_bytes(24))]);
            });

        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->string('portal_access_token', 80)->nullable(false)->change();
            $table->unique('portal_access_token');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('affiliate_partners')) {
            return;
        }

        if (! Schema::hasColumn('affiliate_partners', 'portal_access_token')) {
            return;
        }

        Schema::table('affiliate_partners', function (Blueprint $table): void {
            $table->dropUnique(['portal_access_token']);
            $table->dropColumn('portal_access_token');
        });
    }
};
