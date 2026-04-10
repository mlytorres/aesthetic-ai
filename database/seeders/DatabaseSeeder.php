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

        // 2. Seed a starter plan
        $starterPlan = Plan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name'               => 'Starter',
                'max_procedures'     => 1,
                'max_evaluations_mo' => 50,
                'features'           => ['widget', 'dashboard', 'basic_ai'],
            ]
        );

        // 3. Seed the pilot clinic tenant (Miami Life)
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'miamilife'],
            [
                'name'    => 'Miami Life Cosmetic Center',
                'plan_id' => $starterPlan->id,
                'settings' => [
                    'theme'               => 'luxury-dark',
                    'procedures_enabled'  => ['rhinoplasty', 'bbl', 'lipo_360', 'breast_augmentation', 'facelift'],
                    'coordinator_emails'  => ['coordinator@miamilife.test'],
                ],
            ]
        );

        // Set context so any tenant-scoped reads that follow resolve correctly.
        TenantContext::set($tenant);

        // 4. Seed a coordinator user for the pilot clinic
        User::updateOrCreate(
            ['email' => 'coordinator@miamilife.test'],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Sarah M.',
                'password'  => Hash::make('password'),
                'role'      => User::ROLE_COORDINATOR,
            ]
        );

        // 5. Seed an owner user
        User::updateOrCreate(
            ['email' => 'owner@miamilife.test'],
            [
                'tenant_id' => $tenant->id,
                'name'      => 'Dr. Rivera',
                'password'  => Hash::make('password'),
                'role'      => User::ROLE_OWNER,
            ]
        );

        $this->command->info('✅ Database seeded.');
        $this->command->info("   Clinic URL: http://miamilife.aesthetic-ai.test");
        $this->command->info("   Coordinator: coordinator@miamilife.test / password");
        $this->command->info("   Owner:       owner@miamilife.test / password");
    }
}
