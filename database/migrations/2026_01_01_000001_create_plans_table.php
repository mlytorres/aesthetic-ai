<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->string('name', 64);                          // 'Starter', 'Growth', 'Pro'
            $table->string('slug', 32)->unique();
            $table->unsignedSmallInteger('max_procedures')->default(1);
            $table->unsignedInteger('max_evaluations_mo')->nullable(); // null = unlimited
            $table->string('stripe_price_id', 64)->nullable();
            $table->jsonb('features')->default('{}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
