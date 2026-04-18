<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_partners', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('platform', 32); // instagram | tiktok | youtube | other
            $table->string('handle', 120);
            $table->string('status', 24)->default('active'); // active | paused | blocked
            $table->string('portal_access_token', 80)->unique();

            $table->unsignedInteger('payout_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('monthly_cap_cents')->nullable();
            $table->unsignedSmallInteger('hold_days')->default(14);

            $table->jsonb('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'platform']);
        });

        Schema::create('affiliate_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 120);
            $table->string('status', 24)->default('draft'); // draft | active | paused | archived
            $table->text('description')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('default_payout_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('monthly_cap_cents')->nullable();
            $table->unsignedSmallInteger('hold_days')->default(14);

            $table->jsonb('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('campaign_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_campaign_id')
                ->constrained('affiliate_campaigns')
                ->cascadeOnDelete();

            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('asset_type', 24); // image | video | caption
            $table->text('storage_path');
            $table->string('checksum', 128)->nullable();
            $table->string('status', 24)->default('pending'); // pending | approved | rejected | revoked
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('compliance_notes')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('affiliate_campaign_id');
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('affiliate_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_partner_id')
                ->constrained('affiliate_partners')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_campaign_id')
                ->constrained('affiliate_campaigns')
                ->cascadeOnDelete();

            $table->foreignUuid('campaign_asset_id')
                ->nullable()
                ->constrained('campaign_assets')
                ->nullOnDelete();

            $table->string('token', 64)->unique();
            $table->string('status', 24)->default('active'); // active | paused | revoked
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('last_clicked_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'affiliate_partner_id']);
            $table->index(['tenant_id', 'affiliate_campaign_id']);
        });

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->foreignUuid('affiliate_link_id')
                ->nullable()
                ->after('procedure_slug')
                ->constrained('affiliate_links')
                ->nullOnDelete();

            $table->index(['tenant_id', 'affiliate_link_id']);
        });

        Schema::create('attribution_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_link_id')
                ->constrained('affiliate_links')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_partner_id')
                ->constrained('affiliate_partners')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_campaign_id')
                ->constrained('affiliate_campaigns')
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->nullable()
                ->constrained('evaluations')
                ->nullOnDelete();

            $table->string('event_type', 32); // click | intake_started | evaluation_completed
            $table->string('idempotency_key', 190)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('occurred_at');

            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'event_type']);
            $table->index(['tenant_id', 'affiliate_link_id']);
            $table->index(['tenant_id', 'evaluation_id']);
            $table->unique(['tenant_id', 'event_type', 'idempotency_key']);
        });

        Schema::create('affiliate_payout_ledgers', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_partner_id')
                ->constrained('affiliate_partners')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_campaign_id')
                ->constrained('affiliate_campaigns')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_link_id')
                ->constrained('affiliate_links')
                ->cascadeOnDelete();

            $table->foreignUuid('attribution_event_id')
                ->constrained('attribution_events')
                ->cascadeOnDelete();

            $table->foreignUuid('evaluation_id')
                ->nullable()
                ->constrained('evaluations')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 24)->default('pending_hold'); // pending_hold | approved | rejected | released
            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'affiliate_partner_id']);
            $table->index(['tenant_id', 'hold_until']);
            $table->unique(['tenant_id', 'evaluation_id', 'affiliate_link_id']);
        });

        Schema::create('affiliate_terms_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('affiliate_partner_id')
                ->constrained('affiliate_partners')
                ->cascadeOnDelete();

            $table->string('terms_version', 64);
            $table->timestamp('accepted_at');
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64)->nullable();
            $table->string('proof_url')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'affiliate_partner_id']);
            $table->index(['tenant_id', 'terms_version']);
            $table->unique(['tenant_id', 'affiliate_partner_id', 'terms_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_terms_acceptances');
        Schema::dropIfExists('affiliate_payout_ledgers');
        Schema::dropIfExists('attribution_events');

        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'affiliate_link_id']);
            $table->dropConstrainedForeignId('affiliate_link_id');
        });

        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('campaign_assets');
        Schema::dropIfExists('affiliate_campaigns');
        Schema::dropIfExists('affiliate_partners');
    }
};
