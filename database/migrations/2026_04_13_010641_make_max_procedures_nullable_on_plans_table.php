<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // null means unlimited (Pro plan). Default 1 removed to allow nullable.
            $table->smallInteger('max_procedures')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->smallInteger('max_procedures')->nullable(false)->default(1)->change();
        });
    }
};
