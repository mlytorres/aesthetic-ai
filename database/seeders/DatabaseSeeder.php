<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Facades\TenantContext;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Global lookup data
        $this->call(ProcedureSeeder::class);

        // 2. Seed all billing plans
        // stripe_price_id is filled from the Stripe dashboard once products are created.
        Plan::updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'max_procedures' => null, // unlimited for internal/testing tenants
                'max_evaluations_mo' => null, // unlimited
                'stripe_price_id' => null,
                'features' => ['widget', 'dashboard', 'advanced_ai', 'analytics', 'webhooks', 'api_access', 'white_label', 'affiliate_program'],
                // Hidden from self-serve billing; assignable by super-admin only.
                'is_public' => false,
            ]
        );

        $starterPlan = Plan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'max_procedures' => 1,
                'max_evaluations_mo' => 50,
                'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
                'features' => ['widget', 'dashboard', 'basic_ai'],
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'max_procedures' => 5,
                'max_evaluations_mo' => 200,
                'stripe_price_id' => env('STRIPE_PRICE_GROWTH'),
                'features' => ['widget', 'dashboard', 'advanced_ai', 'analytics', 'webhooks'],
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'max_procedures' => null,   // unlimited
                'max_evaluations_mo' => null,   // unlimited
                'stripe_price_id' => env('STRIPE_PRICE_PRO'),
                'features' => ['widget', 'dashboard', 'advanced_ai', 'analytics', 'webhooks', 'api_access', 'white_label', 'affiliate_program'],
            ]
        );

        // 3. Seed the pilot clinic tenant (Miami Life)
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'miamilife'],
            [
                'name' => 'Miami Life Cosmetic Center',
                'plan_id' => $starterPlan->id,
                'settings' => [
                    'theme' => 'luxury-dark',
                    'procedures_enabled' => ['rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift'],
                    'coordinator_emails' => ['coordinator@miamilife.test'],
                ],
            ]
        );

        // Set context so any tenant-scoped reads that follow resolve correctly.
        TenantContext::set($tenant);

        // 4. Seed a coordinator user for the pilot clinic
        User::updateOrCreate(
            ['email' => 'coordinator@aesthetic-ai.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Sarah M.',
                'password' => Hash::make('password'),
                'role' => User::ROLE_COORDINATOR,
            ]
        );

        // 5. Seed an owner user
        User::updateOrCreate(
            ['email' => 'owner@aesthetic-ai.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Dr. Rivera',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
            ]
        );

        // 6. Seed the platform super-admin (tenant_id = null)
        User::updateOrCreate(
            ['email' => 'admin@aesthetic-ai.test'],
            [
                'tenant_id' => null,
                'name' => 'Platform Admin',
                'password' => Hash::make('password'),
                'role' => User::ROLE_OWNER,
            ]
        );

        $this->command->info('✅ Database seeded.');
        $this->command->info('   Clinic URL:   http://miamilife.aesthetic-ai.test');
        $this->command->info('   Coordinator:  coordinator@aesthetic-ai.test / password');
        $this->command->info('   Owner:        owner@aesthetic-ai.test / password');
        $this->command->info('   Super-admin:  admin@aesthetic-ai.test / password  → /admin');
    }
}
