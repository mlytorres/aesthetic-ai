<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_payout_ledgers', function (Blueprint $table): void {
            // Fraud velocity review queue columns (TKT-C5)
            $table->boolean('fraud_review_required')->default(false)->after('metadata');
            $table->timestamp('fraud_reviewed_at')->nullable()->after('fraud_review_required');
            $table->foreignId('fraud_reviewed_by_user_id')
                ->nullable()
                ->after('fraud_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'fraud_review_required']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_payout_ledgers', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'fraud_review_required']);
            $table->dropConstrainedForeignId('fraud_reviewed_by_user_id');
            $table->dropColumn(['fraud_review_required', 'fraud_reviewed_at']);
        });
    }
};
