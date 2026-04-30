import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Building2,
    ClipboardList,
    CreditCard,
    LayoutGrid,
    ScrollText,
    Settings,
    ShieldCheck,
    Users,
    Webhook,
    HelpCircle,
    Megaphone,
} from 'lucide-react';
import { index as billingIndex } from '@/actions/App/Http/Controllers/Clinic/BillingController';
import { index as integrationsIndex } from '@/actions/App/Http/Controllers/Clinic/IntegrationController';
import clinic from '@/routes/clinic';
import { index as affiliatePayoutsIndex } from '@/actions/App/Http/Controllers/Clinic/AffiliatePayoutController';
import { index as affiliateAnalyticsIndex } from '@/actions/App/Http/Controllers/Clinic/AffiliateAnalyticsController';
import { index as webhooksIndex } from '@/actions/App/Http/Controllers/Clinic/WebhookDeliveryController';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { analytics, dashboard } from '@/routes';
import { index as affiliateCampaignsIndex } from '@/routes/clinic/affiliates/campaigns';
import { index as affiliatePartnersIndex } from '@/routes/clinic/affiliates/partners';
import { edit as clinicSettingsEdit } from '@/routes/clinic/settings';
import { index as clinicTeamIndex } from '@/routes/clinic/team';
import { index as evaluationsIndex } from '@/routes/evaluations';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Evaluations',
        href: evaluationsIndex.url(),
        icon: ClipboardList,
    },
    {
        title: 'Analytics',
        href: analytics.url(),
        icon: BarChart3,
    },
];

const affiliateNavItems: NavItem[] = [
    {
        title: 'Affiliate Partners',
        href: affiliatePartnersIndex.url(),
        icon: Users,
    },
    {
        title: 'Affiliate Campaigns',
        href: affiliateCampaignsIndex.url(),
        icon: Megaphone,
    },
    {
        title: 'Affiliate Payouts',
        href: affiliatePayoutsIndex.url(),
        icon: CreditCard,
    },
    {
        title: 'Affiliate Analytics',
        href: affiliateAnalyticsIndex.url(),
        icon: BarChart3,
    },
];

const clinicNavItems: NavItem[] = [
    {
        title: 'Clinic Settings',
        href: clinicSettingsEdit.url(),
        icon: Settings,
    },
    {
        title: 'Team',
        href: clinicTeamIndex.url(),
        icon: Users,
    },
    {
        title: 'Integrations',
        href: integrationsIndex.url(),
        icon: Webhook,
    },
    {
        title: 'CRM API docs',
        href: clinic.apiDocs.url(),
        icon: BookOpen,
    },
    {
        title: 'Webhooks Hub',
        href: webhooksIndex.url(),
        icon: Webhook,
    },
    {
        title: 'Billing',
        href: billingIndex.url(),
        icon: CreditCard,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const page = usePage<{
        auth: { user: { tenant_id: string | null } };
        features: { affiliateProgram: boolean };
    }>();
    const isSuperAdmin = page.props.auth.user?.tenant_id === null;
    const hasAffiliateProgram = page.props.features?.affiliateProgram ?? false;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-14 hover:bg-transparent"
                        >
                            <Link
                                href={isSuperAdmin ? '/admin' : dashboard()}
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {isSuperAdmin ? (
                    <NavAdmin />
                ) : (
                    <>
                        <NavMain items={mainNavItems} />
                        {hasAffiliateProgram && <NavAffiliates items={affiliateNavItems} />}
                        <NavClinic items={clinicNavItems} />
                    </>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

// ── Super-admin section ───────────────────────────────────────────────────────

function NavAdmin() {
    const { isCurrentUrl } = useCurrentUrl();

    const items: NavItem[] = [
        { title: 'Overview', href: '/admin', icon: LayoutGrid },
        { title: 'Tenants', href: '/admin/tenants', icon: Building2 },
        { title: 'Quiz Editor', href: '/admin/quizzes', icon: HelpCircle },
        { title: 'Audit Log', href: '/admin/audit-log', icon: ScrollText },
    ];

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel className="flex items-center gap-1.5">
                    <ShieldCheck className="h-3 w-3 text-[#0E9E8E]" />
                    Platform Admin
                </SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroup>
        </>
    );
}

// ── Affiliates section ────────────────────────────────────────────────────────

function NavAffiliates({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="flex items-center gap-1.5">
                <Megaphone className="h-3 w-3 text-[#C9A84C]" />
                Top Affiliate
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}

// ── Clinic section ────────────────────────────────────────────────────────────

function NavClinic({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel className="flex items-center gap-1.5">
                <Building2 className="h-3 w-3 text-muted-foreground/50" />
                Clinic
            </SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
