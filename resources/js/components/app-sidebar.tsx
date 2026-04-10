import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Building2, ClipboardList, FolderGit2, LayoutGrid, Settings, ShieldCheck, Users, Webhook } from 'lucide-react';
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
import { analytics, dashboard } from '@/routes';
import { index as evaluationsIndex } from '@/routes/evaluations';
import { edit as clinicSettingsEdit } from '@/routes/clinic/settings';
import { index as clinicTeamIndex } from '@/routes/clinic/team';
import { index as webhooksIndex } from '@/actions/App/Http/Controllers/Clinic/WebhookDeliveryController';
import { useCurrentUrl } from '@/hooks/use-current-url';
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
        title: 'Webhooks',
        href: webhooksIndex.url(),
        icon: Webhook,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
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
                        <SidebarMenuButton size="lg" asChild>
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
        { title: 'Tenants', href: '/admin/tenants', icon: Building2 },
    ];

    return (
        <>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel className="flex items-center gap-1.5">
                    <ShieldCheck className="h-3 w-3 text-[#C9A84C]" />
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
