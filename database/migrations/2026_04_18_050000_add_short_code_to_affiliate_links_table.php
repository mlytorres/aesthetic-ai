<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_links', function (Blueprint $table): void {
            $table->string('short_code', 12)->nullable()->unique()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_links', function (Blueprint $table): void {
            $table->dropColumn('short_code');
        });
    }
};
