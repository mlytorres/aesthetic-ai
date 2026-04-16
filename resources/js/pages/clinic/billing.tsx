import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle,
    CreditCard,
    Zap,
    FileText,
    ExternalLink,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface Plan {
    id: string;
    slug: string;
    name: string;
    max_procedures: number | null;
    max_evaluations_mo: number | null;
    stripe_price_id: string | null;
    features: string[];
}

interface CurrentPlan {
    id: string;
    slug: string;
    name: string;
    max_procedures: number | null;
    max_evaluations_mo: number | null;
    features: string[];
}

interface Subscription {
    status: string;
    ends_at: string | null;
    on_trial: boolean;
    trial_ends_at: string | null;
}

interface Usage {
    evals_this_month: number;
    procedures_count: number;
    procedures: string[];
}

interface Invoice {
    id: string;
    number: string;
    date: string;
    total: string;
    status: 'paid' | 'open' | 'void';
    pdf_url: string | null;
}

interface Props {
    currentPlan: CurrentPlan | null;
    plans: Plan[];
    usage: Usage;
    subscription: Subscription | null;
    has_billing_access: boolean;
    trial_expired: boolean;
    has_active_subscription: boolean;
    invoices: Invoice[];
}

const PLAN_PRICES: Record<string, string> = {
    starter: '$99/mo',
    growth: '$299/mo',
    pro: '$599/mo',
};

const PLAN_HIGHLIGHTS: Record<string, string[]> = {
    starter: [
        '1 procedure',
        '50 evaluations/mo',
        'AI analysis + simulation',
        'Dashboard & widget',
    ],
    growth: [
        '5 procedures',
        '200 evaluations/mo',
        'Advanced AI analysis',
        'Analytics + webhooks',
    ],
    pro: [
        'Unlimited procedures',
        'Unlimited evaluations',
        'API access',
        'White-label ready',
    ],
};

export default function BillingPage({
    currentPlan,
    plans,
    usage,
    subscription,
    has_billing_access,
    trial_expired,
    has_active_subscription,
    invoices,
}: Props) {
    const checkoutForm = useForm<{ plan_slug: string }>({ plan_slug: '' });

    const handleUpgrade = (planSlug: string) => {
        if (!planSlug) {
            return;
        }

        checkoutForm.setData('plan_slug', planSlug);
        checkoutForm.post('/clinic/billing/checkout');
    };

    const handlePortal = () => {
        router.post('/clinic/billing/portal');
    };

    const evalLimit = currentPlan?.max_evaluations_mo ?? null;
    const evalPercent = evalLimit
        ? Math.min(100, (usage.evals_this_month / evalLimit) * 100)
        : 0;
    const isNearLimit = evalLimit !== null && evalPercent >= 80;

    return (
        <>
            <Head title="Billing" />

            <div className="space-y-8">
                <Heading
                    title="Billing"
                    description="Manage your subscription and usage."
                />

                {/* ── Subscription status ─────────────────────────────────────── */}
                {trial_expired && (
                    <div className="rounded-xl border border-red-500/40 bg-red-500/10 px-6 py-5">
                        <div className="flex items-start gap-4">
                            <span className="text-2xl">🔒</span>
                            <div>
                                <p className="text-base font-bold text-red-300">
                                    Your free trial has ended
                                </p>
                                <p className="mt-1 text-sm text-red-400/80">
                                    Your clinic's 14-day trial has expired. All
                                    patient intake and dashboard features are
                                    paused. Choose a plan below to restore full
                                    access immediately.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {!trial_expired && !has_billing_access && (
                    <div className="rounded-lg border border-red-500/30 bg-red-500/10 px-5 py-4">
                        <p className="text-sm font-semibold text-red-400">
                            ⚠️ Your subscription has expired. New evaluations
                            are paused until you reactivate.
                        </p>
                    </div>
                )}

                {subscription?.on_trial && (
                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-5 py-4">
                        <p className="text-sm text-amber-300">
                            🎁 You're on a <strong>free trial</strong> — expires{' '}
                            <strong>{subscription.trial_ends_at}</strong>.
                            Subscribe before then to avoid interruption.
                        </p>
                    </div>
                )}

                {/* ── Current plan + usage ────────────────────────────────────── */}
                <div className="grid gap-6 md:grid-cols-2">
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="mb-1 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            Current Plan
                        </p>
                        <p className="text-2xl font-bold text-foreground">
                            {currentPlan?.name ?? 'No plan'}
                        </p>
                        {subscription && (
                            <Badge
                                className={cn(
                                    'mt-2 border-0',
                                    subscription.status === 'active'
                                        ? 'bg-emerald-500/20 text-emerald-400'
                                        : 'bg-amber-500/20 text-amber-400',
                                )}
                            >
                                {subscription.status}
                            </Badge>
                        )}

                        {subscription && (
                            <button
                                type="button"
                                onClick={handlePortal}
                                className="mt-4 flex items-center gap-2 text-sm text-[#0E9E8E] hover:underline"
                            >
                                <CreditCard className="h-4 w-4" />
                                Manage billing &amp; invoices
                            </button>
                        )}
                    </div>

                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="mb-4 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                            This Month's Usage
                        </p>

                        <div className="space-y-4">
                            <div>
                                <div className="mb-1 flex justify-between text-sm">
                                    <span className="text-foreground">
                                        Evaluations
                                    </span>
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            isNearLimit
                                                ? 'text-amber-400'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {usage.evals_this_month}
                                        {evalLimit !== null
                                            ? ` / ${evalLimit}`
                                            : ' (unlimited)'}
                                    </span>
                                </div>
                                {evalLimit !== null && (
                                    <div className="h-2 rounded-full bg-muted/40">
                                        <div
                                            className={cn(
                                                'h-2 rounded-full transition-all',
                                                evalPercent >= 100
                                                    ? 'bg-red-500'
                                                    : isNearLimit
                                                      ? 'bg-amber-400'
                                                      : 'bg-[#0E9E8E]',
                                            )}
                                            style={{ width: `${evalPercent}%` }}
                                        />
                                    </div>
                                )}
                            </div>

                            <div className="flex justify-between text-sm">
                                <span className="text-foreground">
                                    Procedures enabled
                                </span>
                                <span className="font-semibold text-muted-foreground">
                                    {usage.procedures_count}
                                    {currentPlan?.max_procedures !== null
                                        ? ` / ${currentPlan?.max_procedures ?? '∞'}`
                                        : ' (unlimited)'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Plan cards ──────────────────────────────────────────────── */}
                <div>
                    <h2 className="mb-4 text-sm font-semibold tracking-widest text-muted-foreground uppercase">
                        {trial_expired
                            ? 'Choose a plan to restore access'
                            : 'Available Plans'}
                    </h2>

                    <div className="grid gap-4 md:grid-cols-3">
                        {plans.map((plan) => {
                            const isCurrent = plan.id === currentPlan?.id;
                            const highlights = PLAN_HIGHLIGHTS[plan.slug] ?? [];
                            const price =
                                PLAN_PRICES[plan.slug] ?? 'Contact us';
                            const canPurchase =
                                plan.stripe_price_id !== null && !isCurrent;

                            return (
                                <div
                                    key={plan.id}
                                    className={cn(
                                        'rounded-xl border p-6 transition-colors',
                                        isCurrent
                                            ? 'border-[#0E9E8E]/50 bg-[#0E9E8E]/5'
                                            : 'border-border bg-card',
                                    )}
                                >
                                    <div className="mb-4 flex items-start justify-between">
                                        <div>
                                            <p className="text-lg font-bold text-foreground">
                                                {plan.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {price}
                                            </p>
                                        </div>
                                        {isCurrent && (
                                            <Badge className="border-0 bg-[#0E9E8E]/20 text-[#0E9E8E]">
                                                Current
                                            </Badge>
                                        )}
                                    </div>

                                    <ul className="mb-6 space-y-2">
                                        {highlights.map((h) => (
                                            <li
                                                key={h}
                                                className="flex items-center gap-2 text-sm text-muted-foreground"
                                            >
                                                <CheckCircle className="h-3.5 w-3.5 shrink-0 text-[#0E9E8E]" />
                                                {h}
                                            </li>
                                        ))}
                                    </ul>

                                    {canPurchase ? (
                                        <Button
                                            onClick={() =>
                                                handleUpgrade(plan.slug)
                                            }
                                            disabled={
                                                checkoutForm.processing &&
                                                checkoutForm.data.plan_slug ===
                                                    plan.slug
                                            }
                                            className="w-full bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#B8943D]"
                                        >
                                            <Zap className="mr-2 h-4 w-4" />
                                            {checkoutForm.processing &&
                                            checkoutForm.data.plan_slug ===
                                                plan.slug
                                                ? has_active_subscription
                                                    ? 'Switching...'
                                                    : 'Redirecting...'
                                                : has_active_subscription
                                                  ? `Switch to ${plan.name}`
                                                  : `Upgrade to ${plan.name}`}
                                        </Button>
                                    ) : isCurrent ? (
                                        <Button
                                            variant="outline"
                                            disabled
                                            className="w-full border-border text-muted-foreground"
                                        >
                                            Active plan
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            disabled
                                            className="w-full border-border text-muted-foreground"
                                        >
                                            Contact us
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
            {/* Invoice History */}
            {invoices.length > 0 && (
                <div className="rounded-xl border border-border bg-card p-6">
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-muted/50">
                            <FileText className="h-4 w-4 text-[#0E9E8E]" />
                        </div>
                        <div>
                            <h2 className="text-sm font-semibold text-foreground">
                                Invoice History
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Download past invoices for your records
                            </p>
                        </div>
                    </div>

                    <div className="divide-y divide-border/50">
                        {invoices.map((invoice) => (
                            <div
                                key={invoice.id}
                                className="flex items-center justify-between py-3"
                            >
                                <div className="flex items-center gap-4">
                                    <div>
                                        <p className="text-sm font-medium text-foreground">
                                            {invoice.number}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                invoice.date,
                                            ).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-4">
                                    <span className="text-sm font-semibold text-foreground">
                                        {invoice.total}
                                    </span>
                                    <span
                                        className={cn(
                                            'rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase',
                                            invoice.status === 'paid'
                                                ? 'bg-emerald-500/10 text-emerald-400'
                                                : invoice.status === 'open'
                                                  ? 'bg-amber-500/10 text-amber-400'
                                                  : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {invoice.status}
                                    </span>
                                    {invoice.pdf_url && (
                                        <a
                                            href={invoice.pdf_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-1 rounded-md border border-border px-2.5 py-1 text-xs text-muted-foreground transition-colors hover:border-[#0E9E8E]/50 hover:text-[#0E9E8E]"
                                        >
                                            <ExternalLink className="h-3 w-3" />
                                            PDF
                                        </a>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </>
    );
}

BillingPage.layout = {
    breadcrumbs: [
        { title: 'Clinic', href: '/clinic/settings' },
        { title: 'Billing', href: '#' },
    ],
};
