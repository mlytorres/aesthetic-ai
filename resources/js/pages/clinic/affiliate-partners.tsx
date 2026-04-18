import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    rotateToken as rotatePartnerToken,
    store as storeAffiliatePartner,
} from '@/routes/clinic/affiliates/partners';

interface AffiliatePartner {
    id: string;
    name: string;
    email: string;
    platform: string;
    handle: string;
    status: string;
    payout_cents: number;
    currency: string;
    monthly_cap_cents: number | null;
    hold_days: number;
    portal_url: string;
}

interface PartnerForm {
    name: string;
    email: string;
    platform: string;
    handle: string;
    payout_cents: number;
    currency: string;
    monthly_cap_cents: number | null;
    hold_days: number;
}

interface Props {
    partners: AffiliatePartner[];
}

export default function AffiliatePartners({ partners }: Props) {
    const { data, setData, post, processing, errors, reset } =
        useForm<PartnerForm>({
            name: '',
            email: '',
            platform: 'instagram',
            handle: '',
            payout_cents: 5000,
            currency: 'USD',
            monthly_cap_cents: null,
            hold_days: 14,
        });

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();

        post(storeAffiliatePartner.url(), {
            onSuccess: () => reset('name', 'email', 'handle'),
        });
    };

    const rotate = (partnerId: string): void => {
        router.post(rotatePartnerToken.url(partnerId));
    };

    return (
        <>
            <Head title="Affiliate Partners" />

            <div className="space-y-6">
                <Heading
                    title="Affiliate Partners"
                    description="Manage influencer partners and share secure portal links"
                />

                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-base font-semibold text-foreground">
                        Add Partner
                    </h3>

                    <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="platform">Platform</Label>
                            <Input
                                id="platform"
                                value={data.platform}
                                onChange={(event) =>
                                    setData('platform', event.target.value)
                                }
                            />
                            <InputError message={errors.platform} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="handle">Handle</Label>
                            <Input
                                id="handle"
                                value={data.handle}
                                onChange={(event) =>
                                    setData('handle', event.target.value)
                                }
                            />
                            <InputError message={errors.handle} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payout">Payout (cents)</Label>
                            <Input
                                id="payout"
                                type="number"
                                min={0}
                                value={data.payout_cents}
                                onChange={(event) =>
                                    setData('payout_cents', Number(event.target.value))
                                }
                            />
                            <InputError message={errors.payout_cents} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="hold_days">Hold Days</Label>
                            <Input
                                id="hold_days"
                                type="number"
                                min={1}
                                value={data.hold_days}
                                onChange={(event) =>
                                    setData('hold_days', Number(event.target.value))
                                }
                            />
                            <InputError message={errors.hold_days} />
                        </div>

                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                            >
                                {processing ? 'Saving...' : 'Save Partner'}
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-base font-semibold text-foreground">
                        Partners
                    </h3>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/50">
                                    <th className="px-4 py-2 text-left">Name</th>
                                    <th className="px-4 py-2 text-left">Platform</th>
                                    <th className="px-4 py-2 text-left">Portal URL</th>
                                    <th className="px-4 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {partners.map((partner) => (
                                    <tr
                                        key={partner.id}
                                        className="border-b border-sidebar-border/30"
                                    >
                                        <td className="px-4 py-2">
                                            <div className="font-medium text-foreground">
                                                {partner.name}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {partner.email}
                                            </div>
                                        </td>
                                        <td className="px-4 py-2">
                                            {partner.platform} ({partner.handle})
                                        </td>
                                        <td className="max-w-[280px] px-4 py-2 text-xs text-muted-foreground">
                                            <a
                                                className="underline"
                                                href={partner.portal_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                {partner.portal_url}
                                            </a>
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => rotate(partner.id)}
                                            >
                                                Rotate URL
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
