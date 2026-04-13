<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the four canonical plans.
     *
     * FREE  — admin-assigned only (is_public = false), no Stripe price.
     *         Used for partner clinics (e.g. miamilife) that get access gratis.
     *
     * STARTER / GROWTH / PRO — self-service via Stripe Checkout.
     *         stripe_price_id is populated from .env so each environment
     *         (local, staging, production) can point to the correct Stripe price.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'max_procedures' => 1,
                'max_evaluations_mo' => 20,
                'stripe_price_id' => null,
                'is_public' => false,
                'features' => [
                    '1 procedure',
                    '20 evaluations/mo',
                    'AI analysis + simulation',
                    'Dashboard & widget',
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'max_procedures' => 1,
                'max_evaluations_mo' => 50,
                'stripe_price_id' => env('STRIPE_PRICE_STARTER'),
                'is_public' => true,
                'features' => [
                    '1 procedure',
                    '50 evaluations/mo',
                    'AI analysis + simulation',
                    'Dashboard & widget',
                ],
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'max_procedures' => 5,
                'max_evaluations_mo' => 200,
                'stripe_price_id' => env('STRIPE_PRICE_GROWTH'),
                'is_public' => true,
                'features' => [
                    '5 procedures',
                    '200 evaluations/mo',
                    'Advanced AI analysis',
                    'Analytics + webhooks',
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'max_procedures' => null,
                'max_evaluations_mo' => null,
                'stripe_price_id' => env('STRIPE_PRICE_PRO'),
                'is_public' => true,
                'features' => [
                    'Unlimited procedures',
                    'Unlimited evaluations',
                    'API access',
                    'White-label ready',
                ],
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
