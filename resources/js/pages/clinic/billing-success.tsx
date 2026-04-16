import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';

interface Props {
    plan_name: string | null;
}

export default function BillingSuccess({ plan_name }: Props) {
    return (
        <>
            <Head title="Subscription Active" />

            <div className="flex min-h-[60vh] flex-col items-center justify-center text-center">
                <CheckCircle className="mb-4 h-16 w-16 text-[#0E9E8E]" />
                <h1 className="mb-2 text-2xl font-bold text-foreground">
                    You're all set!
                </h1>
                <p className="mb-6 text-muted-foreground">
                    {plan_name ? (
                        <>
                            Your{' '}
                            <strong className="text-foreground">
                                {plan_name}
                            </strong>{' '}
                            subscription is now active.
                        </>
                    ) : (
                        'Your subscription is now active.'
                    )}
                </p>
                <Link
                    href="/clinic/billing"
                    className="rounded-lg bg-[#0E9E8E] px-6 py-2.5 text-sm font-semibold text-[#0A0A0F] transition-colors hover:bg-[#B8943D]"
                >
                    View billing
                </Link>
            </div>
        </>
    );
}

BillingSuccess.layout = {
    breadcrumbs: [
        { title: 'Clinic', href: '/clinic/settings' },
        { title: 'Billing', href: '/clinic/billing' },
        { title: 'Success', href: '#' },
    ],
};
