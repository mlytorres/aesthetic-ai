import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    destroy as destroyPartner,
    rotateToken as rotatePartnerToken,
    store as storeAffiliatePartner,
    update as updateAffiliatePartner,
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
    status: string;
    payout_cents: number;
    currency: string;
    monthly_cap_cents: number | null;
    hold_days: number;
}

interface Props {
    partners: AffiliatePartner[];
}

const PLATFORMS = ['instagram', 'tiktok', 'youtube', 'other'] as const;
const STATUSES  = ['active', 'paused', 'blocked'] as const;

function formatCurrency(cents: number, currency: string): string {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
}

function PartnerFormFields({
    data,
    setData,
    errors,
    showStatus = false,
}: {
    data: PartnerForm;
    setData: (key: keyof PartnerForm, value: string | number | null) => void;
    errors: Partial<Record<keyof PartnerForm, string>>;
    showStatus?: boolean;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="platform">Platform</Label>
                <Select value={data.platform} onValueChange={(v) => setData('platform', v)}>
                    <SelectTrigger id="platform">
                        <SelectValue placeholder="Select platform" />
                    </SelectTrigger>
                    <SelectContent>
                        {PLATFORMS.map((p) => (
                            <SelectItem key={p} value={p} className="capitalize">{p.charAt(0).toUpperCase() + p.slice(1)}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.platform} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="handle">Handle</Label>
                <Input id="handle" value={data.handle} onChange={(e) => setData('handle', e.target.value)} placeholder="username (no @)" />
                <InputError message={errors.handle} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="payout_cents">Payout (cents)</Label>
                <Input
                    id="payout_cents"
                    type="number"
                    min={0}
                    value={data.payout_cents}
                    onChange={(e) => setData('payout_cents', Number(e.target.value))}
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
                    onChange={(e) => setData('hold_days', Number(e.target.value))}
                />
                <InputError message={errors.hold_days} />
            </div>

            {showStatus && (
                <div className="grid gap-2">
                    <Label htmlFor="status">Status</Label>
                    <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                        <SelectTrigger id="status">
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            {STATUSES.map((s) => (
                                <SelectItem key={s} value={s} className="capitalize">{s.charAt(0).toUpperCase() + s.slice(1)}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>
            )}
        </div>
    );
}

export default function AffiliatePartners({ partners }: Props) {
    const [editingPartner, setEditingPartner] = useState<AffiliatePartner | null>(null);
    const [deletingPartner, setDeletingPartner] = useState<AffiliatePartner | null>(null);

    // ── Create form ────────────────────────────────────────────────────
    const createForm = useForm<PartnerForm>({
        name: '',
        email: '',
        platform: 'instagram',
        handle: '',
        status: 'active',
        payout_cents: 5000,
        currency: 'USD',
        monthly_cap_cents: null,
        hold_days: 14,
    });

    const submitCreate = (e: React.FormEvent): void => {
        e.preventDefault();
        createForm.post(storeAffiliatePartner.url(), {
            onSuccess: () => createForm.reset('name', 'email', 'handle'),
        });
    };

    // ── Edit form ──────────────────────────────────────────────────────
    const editForm = useForm<PartnerForm>({
        name: '',
        email: '',
        platform: 'instagram',
        handle: '',
        status: 'active',
        payout_cents: 5000,
        currency: 'USD',
        monthly_cap_cents: null,
        hold_days: 14,
    });

    const openEdit = (partner: AffiliatePartner): void => {
        editForm.setData({
            name:               partner.name,
            email:              partner.email,
            platform:           partner.platform,
            handle:             partner.handle,
            status:             partner.status,
            payout_cents:       partner.payout_cents,
            currency:           partner.currency,
            monthly_cap_cents:  partner.monthly_cap_cents,
            hold_days:          partner.hold_days,
        });
        setEditingPartner(partner);
    };

    const submitEdit = (e: React.FormEvent): void => {
        e.preventDefault();
        if (!editingPartner) return;
        editForm.patch(updateAffiliatePartner.url(editingPartner.id), {
            onSuccess: () => setEditingPartner(null),
        });
    };

    // ── Delete ─────────────────────────────────────────────────────────
    const confirmDelete = (): void => {
        if (!deletingPartner) return;
        router.delete(destroyPartner.url(deletingPartner.id), {
            onSuccess: () => setDeletingPartner(null),
        });
    };

    // ── Token rotate ───────────────────────────────────────────────────
    const rotate = (partnerId: string): void => {
        router.post(rotatePartnerToken.url(partnerId));
    };

    const copyPortalUrl = (url: string): void => {
        void navigator.clipboard.writeText(url);
    };

    return (
        <>
            <Head title="Affiliate Partners" />

            <div className="space-y-6">
                <Heading
                    title="Affiliate Partners"
                    description="Manage influencer partners and share secure portal links"
                />

                {/* ── Add partner form ───────────────────────────────── */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-base font-semibold text-foreground">Add Partner</h3>
                    <form onSubmit={submitCreate}>
                        <PartnerFormFields
                            data={createForm.data}
                            setData={createForm.setData}
                            errors={createForm.errors}
                        />
                        <div className="mt-4">
                            <Button
                                type="submit"
                                disabled={createForm.processing}
                                className="bg-[#0E9E8E] text-white hover:bg-[#0B7A6E]"
                            >
                                {createForm.processing ? 'Saving...' : 'Save Partner'}
                            </Button>
                        </div>
                    </form>
                </div>

                {/* ── Partners table ─────────────────────────────────── */}
                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-base font-semibold text-foreground">Partners</h3>

                    {partners.length === 0 ? (
                        <div className="rounded-md border border-dashed border-sidebar-border/50 p-10 text-center text-sm text-muted-foreground">
                            No affiliate partners yet. Add your first partner above.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-sidebar-border/50">
                                        {['Partner', 'Platform', 'Status', 'Payout', 'Hold', 'Actions'].map((label, i) => (
                                            <th
                                                key={label}
                                                className={`px-4 py-2 text-xs font-medium uppercase tracking-wider text-muted-foreground ${
                                                    i >= 3 ? 'text-right' : 'text-left'
                                                }`}
                                            >
                                                {label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {partners.map((partner) => (
                                        <tr key={partner.id} className="border-b border-sidebar-border/30 last:border-0">
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">{partner.name}</div>
                                                <div className="text-xs text-muted-foreground">{partner.email}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="capitalize text-foreground">{partner.platform}</div>
                                                <div className="text-xs text-muted-foreground">{partner.handle}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        partner.status === 'active'
                                                            ? 'default'
                                                            : partner.status === 'paused'
                                                              ? 'secondary'
                                                              : 'destructive'
                                                    }
                                                    className={
                                                        partner.status === 'active'
                                                            ? 'bg-[#0E9E8E] text-white hover:bg-[#0E9E8E]/90'
                                                            : ''
                                                    }
                                                >
                                                    <span className="capitalize">{partner.status}</span>
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-foreground">
                                                {formatCurrency(partner.payout_cents, partner.currency)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-muted-foreground">
                                                {partner.hold_days}d
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => copyPortalUrl(partner.portal_url)}
                                                        title="Copy portal URL"
                                                    >
                                                        Copy URL
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => openEdit(partner)}
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => rotate(partner.id)}
                                                        title="Revoke current portal URL and issue a new one"
                                                    >
                                                        Rotate
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => setDeletingPartner(partner)}
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* ── Edit dialog ────────────────────────────────────────── */}
            <Dialog open={!!editingPartner} onOpenChange={(open) => !open && setEditingPartner(null)}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Edit Partner</DialogTitle>
                        <DialogDescription>Update this partner's details and status.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEdit}>
                        <PartnerFormFields
                            data={editForm.data}
                            setData={editForm.setData}
                            errors={editForm.errors}
                            showStatus
                        />
                        <DialogFooter className="mt-6">
                            <Button type="button" variant="outline" onClick={() => setEditingPartner(null)}>
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                                className="bg-[#0E9E8E] text-white hover:bg-[#0B7A6E]"
                            >
                                {editForm.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* ── Delete confirmation dialog ─────────────────────────── */}
            <Dialog open={!!deletingPartner} onOpenChange={(open) => !open && setDeletingPartner(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Remove Partner</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove{' '}
                            <span className="font-semibold">{deletingPartner?.name}</span>? Their payout
                            history will be preserved, but their portal access and tracking links will be
                            deactivated.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingPartner(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete}>
                            Remove Partner
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
