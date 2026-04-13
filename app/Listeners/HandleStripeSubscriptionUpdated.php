<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Syncs the tenant's plan_id whenever Stripe fires a subscription event.
 *
 * Handles:
 *   - customer.subscription.updated  (plan change, renewal)
 *   - customer.subscription.deleted  (cancellation completed)
 *
 * Registered in EventServiceProvider via the WebhookReceived event.
 */
class HandleStripeSubscriptionUpdated
{
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';
        $object = $event->payload['data']['object'] ?? [];
        $stripeId = $object['customer'] ?? null;

        if ($stripeId === null) {
            return;
        }

        // checkout.session.completed — look up customer differently
        if ($type === 'checkout.session.completed') {
            $stripeId = $object['customer'] ?? null;
            if ($stripeId === null) {
                return;
            }
            $tenant = Tenant::where('stripe_id', $stripeId)->first();
            if ($tenant === null) {
                return;
            }
            // The subscription was just activated; sync plan_id by refreshing
            // from the subscription created event that Cashier stores locally.
            $subscription = $tenant->subscriptions()->latest()->first();
            if ($subscription) {
                $plan = Plan::where('stripe_price_id', $subscription->stripe_price)->first();
                if ($plan !== null && $tenant->plan_id !== $plan->id) {
                    $tenant->update(['plan_id' => $plan->id]);
                    Log::info('Tenant plan synced from checkout.session.completed', [
                        'tenant_id' => $tenant->id,
                        'plan_slug' => $plan->slug,
                    ]);
                }
            }

            return;
        }

        if (! in_array($type, [
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'customer.subscription.created',
        ], true)) {
            return;
        }

        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant === null) {
            Log::warning('HandleStripeSubscriptionUpdated: no tenant for stripe_id', ['stripe_id' => $stripeId]);

            return;
        }

        if ($type === 'customer.subscription.deleted') {
            // Subscription cancelled — keep plan_id but log for tracking.
            Log::info('Stripe subscription cancelled', ['tenant_id' => $tenant->id]);

            return;
        }

        // Sync plan_id from the active Stripe price.
        $items = $object['items']['data'] ?? [];
        $priceId = $items[0]['price']['id'] ?? null;

        if ($priceId === null) {
            return;
        }

        $plan = Plan::where('stripe_price_id', $priceId)->first();

        if ($plan !== null && $tenant->plan_id !== $plan->id) {
            $tenant->update(['plan_id' => $plan->id]);
            Log::info('Tenant plan synced from Stripe', [
                'tenant_id' => $tenant->id,
                'plan_slug' => $plan->slug,
            ]);
        }
    }
}
