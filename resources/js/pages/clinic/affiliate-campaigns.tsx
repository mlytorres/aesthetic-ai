import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
    store as storeAffiliateCampaign,
    storeAsset as storeCampaignAsset,
} from '@/actions/App/Http/Controllers/Clinic/AffiliateCampaignController';
import { store as storeAffiliateLink } from '@/actions/App/Http/Controllers/Clinic/AffiliateLinkController';

interface CampaignAsset {
    id: string;
    name: string;
    asset_type: string;
    status: string;
    storage_path: string;
}

interface CampaignLink {
    id: string;
    status: string;
    click_count: number;
    token: string;
    partner: {
        name: string;
        handle: string;
    } | null;
    asset: {
        name: string;
        status: string;
    } | null;
}

interface Campaign {
    id: string;
    name: string;
    slug: string;
    status: string;
    default_payout_cents: number;
    currency: string;
    assets: CampaignAsset[];
    links: CampaignLink[];
}

interface PartnerOption {
    id: string;
    name: string;
    handle: string;
}

interface CampaignForm {
    name: string;
    slug: string;
    status: string;
    description: string;
    default_payout_cents: number;
    currency: string;
    monthly_cap_cents: number | null;
    hold_days: number;
    starts_at: string;
    ends_at: string;
}

interface AssetForm {
    name: string;
    asset_type: string;
    storage_path: string;
    checksum: string;
    status: string;
    compliance_notes: string;
}

interface LinkForm {
    affiliate_partner_id: string;
    campaign_asset_id: string;
    status: string;
}

interface Props {
    campaigns: Campaign[];
    partners: PartnerOption[];
}

export default function AffiliateCampaigns({ campaigns, partners }: Props) {
    const campaignForm = useForm<CampaignForm>({
        name: '',
        slug: '',
        status: 'draft',
        description: '',
        default_payout_cents: 5000,
        currency: 'USD',
        monthly_cap_cents: null,
        hold_days: 14,
        starts_at: '',
        ends_at: '',
    });

    const assetForm = useForm<AssetForm>({
        name: '',
        asset_type: 'image',
        storage_path: '',
        checksum: '',
        status: 'approved',
        compliance_notes: '',
    });

    const linkForm = useForm<LinkForm>({
        affiliate_partner_id: partners[0]?.id ?? '',
        campaign_asset_id: '',
        status: 'active',
    });

    const submitCampaign = (event: React.FormEvent): void => {
        event.preventDefault();

        campaignForm.post(storeAffiliateCampaign.url(), {
            onSuccess: () => campaignForm.reset('name', 'slug', 'description'),
        });
    };

    const submitAsset = (event: React.FormEvent, campaignId: string): void => {
        event.preventDefault();

        assetForm.post(storeCampaignAsset(campaignId).url, {
            onSuccess: () => assetForm.reset(),
        });
    };

    const submitLink = (event: React.FormEvent, campaignId: string): void => {
        event.preventDefault();

        linkForm.post(storeAffiliateLink(campaignId).url, {
            onSuccess: () => linkForm.reset('campaign_asset_id'),
        });
    };

    return (
        <>
            <Head title="Affiliate Campaigns" />

            <div className="space-y-6">
                <Heading
                    title="Affiliate Campaigns"
                    description="Create campaigns, approve assets, and generate partner tracking links"
                />

                <div className="rounded-lg border border-sidebar-border/50 bg-card p-6">
                    <h3 className="mb-4 text-base font-semibold text-foreground">
                        Create New Campaign
                    </h3>

                    <form onSubmit={submitCampaign} className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={campaignForm.data.name}
                                onChange={(event) =>
                                    campaignForm.setData('name', event.target.value)
                                }
                            />
                            <InputError message={campaignForm.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input
                                id="slug"
                                value={campaignForm.data.slug}
                                onChange={(event) =>
                                    campaignForm.setData('slug', event.target.value)
                                }
                            />
                            <InputError message={campaignForm.errors.slug} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="status">Status</Label>
                            <Select
                                value={campaignForm.data.status}
                                onValueChange={(value) => campaignForm.setData('status', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Draft</SelectItem>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="paused">Paused</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={campaignForm.errors.status} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="payout">Default Payout (cents)</Label>
                            <Input
                                id="payout"
                                type="number"
                                min={0}
                                value={campaignForm.data.default_payout_cents}
                                onChange={(event) =>
                                    campaignForm.setData(
                                        'default_payout_cents',
                                        Number(event.target.value),
                                    )
                                }
                            />
                            <InputError
                                message={campaignForm.errors.default_payout_cents}
                            />
                        </div>

                        <div className="md:col-span-2 pt-2">
                            <Button
                                type="submit"
                                disabled={campaignForm.processing}
                                className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90"
                            >
                                {campaignForm.processing
                                    ? 'Saving...'
                                    : 'Create Campaign'}
                            </Button>
                        </div>
                    </form>
                </div>

                <div className="space-y-6">
                    {campaigns.map((campaign) => (
                        <div
                            key={campaign.id}
                            className="space-y-6 rounded-lg border border-sidebar-border/50 bg-card p-6"
                        >
                            <div className="flex items-center justify-between border-b border-sidebar-border/30 pb-4">
                                <div>
                                    <h3 className="text-lg font-bold text-foreground">
                                        {campaign.name}
                                    </h3>
                                    <p className="text-xs uppercase tracking-wider text-muted-foreground font-mono">
                                        {campaign.slug} — <span className={campaign.status === 'active' ? 'text-emerald-500' : 'text-amber-500'}>{campaign.status}</span>
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-foreground">
                                        {campaign.currency}{' '}
                                        {campaign.default_payout_cents / 100} per lead
                                    </p>
                                    <p className="text-[10px] text-muted-foreground">Default Payout</p>
                                </div>
                            </div>

                            <div className="grid gap-6 lg:grid-cols-2">
                                {/* Assets Section */}
                                <div className="space-y-4">
                                    <h4 className="text-sm font-bold uppercase tracking-tight text-foreground flex items-center gap-2">
                                        <span className="h-1.5 w-1.5 rounded-full bg-[#0E9E8E]" />
                                        Campaign Assets
                                    </h4>
                                    
                                    <div className="space-y-2 max-h-48 overflow-y-auto pr-2">
                                        {campaign.assets.map(asset => (
                                            <div key={asset.id} className="flex items-center justify-between rounded-md border border-sidebar-border/20 bg-black/10 p-2 text-xs">
                                                <div className="flex flex-col">
                                                    <span className="font-medium">{asset.name}</span>
                                                    <span className="text-[10px] opacity-60 truncate max-w-[150px]">{asset.storage_path}</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="px-1.5 py-0.5 rounded bg-sidebar-border/50 text-[10px] font-bold uppercase">{asset.asset_type}</span>
                                                    <span className={`h-1.5 w-1.5 rounded-full ${asset.status === 'approved' ? 'bg-emerald-500' : 'bg-amber-500'}`} title={asset.status} />
                                                </div>
                                            </div>
                                        ))}
                                        {campaign.assets.length === 0 && (
                                            <p className="text-xs text-muted-foreground italic p-4 text-center border border-dashed border-sidebar-border/30 rounded-md">No assets added yet.</p>
                                        )}
                                    </div>

                                    <form
                                        onSubmit={(event) => submitAsset(event, campaign.id)}
                                        className="space-y-3 rounded-md bg-sidebar-border/10 p-3 pt-4 border border-sidebar-border/20"
                                    >
                                        <p className="text-[10px] font-bold uppercase text-muted-foreground mb-1">Add New Material</p>
                                        <div className="grid gap-2">
                                            <Input
                                                placeholder="Asset name (e.g. Story Ad A)"
                                                value={assetForm.data.name}
                                                onChange={(event) =>
                                                    assetForm.setData('name', event.target.value)
                                                }
                                                className="h-8 text-xs"
                                            />
                                            <div className="flex gap-2">
                                                <Select
                                                    value={assetForm.data.asset_type}
                                                    onValueChange={(value) => assetForm.setData('asset_type', value)}
                                                >
                                                    <SelectTrigger className="h-8 text-xs w-[120px]">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="image">Image</SelectItem>
                                                        <SelectItem value="video">Video</SelectItem>
                                                        <SelectItem value="caption">Caption</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <Input
                                                    placeholder={assetForm.data.asset_type === 'caption' ? "Enter caption text..." : "URL or Storage Path"}
                                                    value={assetForm.data.storage_path}
                                                    onChange={(event) =>
                                                        assetForm.setData('storage_path', event.target.value)
                                                    }
                                                    className="h-8 text-xs flex-1"
                                                />
                                            </div>
                                            <Button type="submit" size="sm" disabled={assetForm.processing} className="h-8 w-full">
                                                Save Asset
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                {/* Links Section */}
                                <div className="space-y-4">
                                    <h4 className="text-sm font-bold uppercase tracking-tight text-foreground flex items-center gap-2">
                                        <span className="h-1.5 w-1.5 rounded-full bg-[#C9A84C]" />
                                        Partner Tracking Links
                                    </h4>

                                    <div className="space-y-2 max-h-48 overflow-y-auto pr-2">
                                        {campaign.links.map((link) => (
                                            <div
                                                key={link.id}
                                                className="flex flex-wrap items-center justify-between rounded-md border border-sidebar-border/20 bg-black/10 p-2 text-xs"
                                            >
                                                <div>
                                                    <span className="font-medium text-[#C9A84C]">{link.partner?.name ?? 'Partner'}</span>
                                                    <span className="mx-2 text-muted-foreground">/</span>
                                                    <span className="opacity-70">{link.asset?.name ?? 'General'}</span>
                                                </div>
                                                <div className="text-muted-foreground text-[10px] font-mono">
                                                    {link.click_count} CLICKS | {link.status}
                                                </div>
                                            </div>
                                        ))}
                                        {campaign.links.length === 0 && (
                                            <p className="text-xs text-muted-foreground italic p-4 text-center border border-dashed border-sidebar-border/30 rounded-md">No partner links generated.</p>
                                        )}
                                    </div>

                                    <form
                                        onSubmit={(event) => submitLink(event, campaign.id)}
                                        className="space-y-3 rounded-md bg-amber-950/10 p-3 pt-4 border border-[#C9A84C]/20"
                                    >
                                        <p className="text-[10px] font-bold uppercase text-[#C9A84C]/80 mb-1">Generate Tracking Link</p>
                                        <div className="grid gap-2">
                                            <div className="flex gap-2">
                                                <Select
                                                     value={linkForm.data.affiliate_partner_id}
                                                     onValueChange={(value) => linkForm.setData('affiliate_partner_id', value)}
                                                >
                                                    <SelectTrigger className="h-8 text-xs flex-1">
                                                        <SelectValue placeholder="Select Partner" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {partners.map((partner) => (
                                                            <SelectItem key={partner.id} value={partner.id}>
                                                                {partner.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>

                                                <Select
                                                    value={linkForm.data.campaign_asset_id}
                                                    onValueChange={(value) => linkForm.setData('campaign_asset_id', value)}
                                                >
                                                    <SelectTrigger className="h-8 text-xs flex-1">
                                                        <SelectValue placeholder="Select Asset (Optional)" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">General Link (No Creative)</SelectItem>
                                                        {campaign.assets.map((asset) => (
                                                            <SelectItem key={asset.id} value={asset.id}>
                                                                {asset.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <Button type="submit" size="sm" variant="outline" disabled={linkForm.processing} className="h-8 w-full border-[#C9A84C]/40 hover:bg-[#C9A84C]/10 text-[#C9A84C]">
                                                Create Attribution Link
                                            </Button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    ))}
                    {campaigns.length === 0 && (
                        <div className="text-center py-20 border-2 border-dashed border-sidebar-border/30 rounded-xl">
                            <p className="text-muted-foreground italic">No campaigns found. Create your first campaign above!</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
