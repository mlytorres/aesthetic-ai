import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Building2, ClipboardList, CreditCard, FolderGit2, LayoutGrid, ScrollText, Settings, ShieldCheck, Users, Webhook, HelpCircle } from 'lucide-react';
import { index as billingIndex } from '@/actions/App/Http/Controllers/Clinic/BillingController';
import { index as webhooksIndex } from '@/actions/App/Http/Controllers/Clinic/WebhookDeliveryController';
import { index as integrationsIndex } from '@/actions/App/Http/Controllers/Clinic/IntegrationController';
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

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const page = usePage<{ auth: { user: { tenant_id: string | null } } }>();
    const isSuperAdmin = page.props.auth.user?.tenant_id === null;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="h-14 hover:bg-transparent">
                            <Link href={isSuperAdmin ? '/admin' : dashboard()} prefetch>
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

// ── Clinic section ────────────────────────────────────────────────────────────

function NavClinic({ items }: { items: NavItem[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Clinic</SidebarGroupLabel>
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
