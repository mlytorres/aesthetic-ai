<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;

/**
 * Manages Stripe billing for the authenticated clinic (tenant).
 *
 * Flow:
 *   index()    → current plan, usage, available upgrade plans
 *   checkout() → Stripe Checkout session → redirect to Stripe-hosted page
 *   portal()   → Stripe Customer Portal (manage card, download invoices)
 *   success()  → post-checkout confirmation (Stripe redirects back here)
 */
class BillingController extends Controller
{
    public function index(): Response
    {
        $tenant = TenantContext::get();
        $tenant->load('plan');

        // Only return publicly purchasable plans. The FREE plan is admin-assigned only
        // and must not appear on the self-service billing page.
        $plans = Plan::where('is_public', true)
            ->orderByRaw("CASE slug WHEN 'starter' THEN 1 WHEN 'growth' THEN 2 WHEN 'pro' THEN 3 ELSE 4 END")
            ->get(['id', 'slug', 'name', 'max_procedures', 'max_evaluations_mo', 'stripe_price_id', 'features']);

        $subscription = $tenant->subscription('default');

        // Trial has run out and no subscription — show the paywall on the billing page.
        $trialExpired = ! $tenant->hasBillingAccess()
            && $tenant->trial_ends_at !== null
            && $tenant->trial_ends_at->isPast();

        return Inertia::render('clinic/billing', [
            'currentPlan' => $tenant->plan ? [
                'id' => $tenant->plan->id,
                'slug' => $tenant->plan->slug,
                'name' => $tenant->plan->name,
                'max_procedures' => $tenant->plan->max_procedures,
                'max_evaluations_mo' => $tenant->plan->max_evaluations_mo,
                'features' => $tenant->plan->features,
            ] : null,
            'plans' => $plans,
            'usage' => [
                'evals_this_month' => $tenant->currentMonthEvalCount(),
                'procedures_count' => count($tenant->enabledProcedures()),
                'procedures' => $tenant->enabledProcedures(),
            ],
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'ends_at' => $subscription->ends_at?->toDateString(),
                'on_trial' => $tenant->onTrial(),
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            ] : null,
            'has_billing_access' => $tenant->hasBillingAccess(),
            'trial_expired' => $trialExpired,
            'has_active_subscription' => $subscription !== null && $subscription->active(),
        ]);
    }

    /**
     * Create a Stripe Checkout session (new subscription) or swap an existing one.
     *
     * If the tenant already has an active subscription, we call swap() to
     * prorate and switch plans immediately — no new Checkout session needed.
     * If not, we open a Stripe Checkout session to collect payment details.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        abort_if($plan->stripe_price_id === null, 422, 'This plan is not yet configured for purchase. Contact support@symetrihealth.com.');

        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $baseUrl = "{$scheme}://{$tenant->slug}.{$domain}";

        // ── Plan swap (already subscribed) ────────────────────────────────────
        $subscription = $tenant->subscription('default');

        if ($subscription !== null && $subscription->active()) {
            try {
                $subscription->swap($plan->stripe_price_id);
                $tenant->update(['plan_id' => $plan->id]);

                return redirect($baseUrl.'/clinic/billing')->with('success', "Switched to {$plan->name}.");
            } catch (IncompletePayment $e) {
                return redirect()->route('cashier.payment', [
                    $e->payment->id,
                    'redirect' => $baseUrl.'/clinic/billing',
                ]);
            }
        }

        // ── New subscription (no active subscription yet) ─────────────────────
        try {
            return $tenant->newSubscription('default', $plan->stripe_price_id)
                ->checkout([
                    'success_url' => $baseUrl.'/clinic/billing/success?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => $baseUrl.'/clinic/billing',
                    'metadata' => [
                        'tenant_id' => $tenant->id,
                        'plan_slug' => $plan->slug,
                    ],
                ]);
        } catch (IncompletePayment $e) {
            return redirect()->route('cashier.payment', [
                $e->payment->id,
                'redirect' => $baseUrl.'/clinic/billing',
            ]);
        }
    }

    /**
     * Redirect to Stripe Customer Portal for self-service billing management.
     */
    public function portal(): RedirectResponse
    {
        $tenant = TenantContext::get();
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'https';
        $baseUrl = "{$scheme}://{$tenant->slug}.{$domain}";

        return $tenant->redirectToBillingPortal($baseUrl.'/clinic/billing');
    }

    /**
     * Stripe redirects here after a successful checkout.
     * Syncs plan_id from the new subscription's price.
     */
    public function success(): Response
    {
        $tenant = TenantContext::get();
        $tenant->refresh();

        // Sync plan_id from the newly activated subscription.
        $subscription = $tenant->subscription('default');

        if ($subscription !== null) {
            $plan = Plan::where('stripe_price_id', $subscription->stripe_price)->first();

            if ($plan !== null && $tenant->plan_id !== $plan->id) {
                $tenant->update(['plan_id' => $plan->id]);
            }
        }

        return Inertia::render('clinic/billing-success', [
            'plan_name' => $tenant->fresh()->plan?->name,
        ]);
    }
}
