import { Head, router, useForm } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';

// ── Types ─────────────────────────────────────────────────────────────────────

interface AuditEntry {
    id: number;
    tenant_name: string;
    tenant_slug: string | null;
    user_name: string;
    user_role: string | null;
    action: string;
    subject_type: string | null;
    subject_id: string | null;
    ip_address: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEntries {
    data: AuditEntry[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Tenant {
    id: string;
    name: string;
    slug: string;
}

interface Filters {
    tenant_id: string | null;
    action: string | null;
    from: string | null;
    to: string | null;
}

interface Props {
    entries: PaginatedEntries;
    tenants: Tenant[];
    filters: Filters;
}

// ── Action badge colour ───────────────────────────────────────────────────────

function actionColor(action: string): string {
    if (action.includes('export') || action.includes('viewed')) return 'bg-purple-500/15 text-purple-400';
    if (action.includes('delete') || action.includes('failed')) return 'bg-red-500/15 text-red-400';
    if (action.includes('login') || action.includes('impersonat')) return 'bg-amber-500/15 text-amber-400';
    if (action.includes('create') || action.includes('register') || action.includes('submitted')) return 'bg-emerald-500/15 text-emerald-400';
    return 'bg-zinc-500/15 text-zinc-400';
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(iso));
}

// ── Page ─────────────────────────────────────────────────────────────────────

export default function AuditLogPage({ entries, tenants, filters }: Props) {
    const { data, setData, get, processing } = useForm({
        tenant_id: filters.tenant_id ?? '',
        action:    filters.action ?? '',
        from:      filters.from ?? '',
        to:        filters.to ?? '',
    });

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        get('/admin/audit-log', { preserveScroll: true, replace: true });
    };

    const clearFilters = () => {
        router.get('/admin/audit-log', {}, { replace: true });
    };

    const hasActiveFilters = !!(filters.tenant_id || filters.action || filters.from || filters.to);

    return (
        <>
            <Head title="Audit Log — Admin" />

            <div className="space-y-6 p-6">

                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">Audit Log</h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            All platform activity · {entries.total.toLocaleString()} entries
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">Clinic</label>
                        <select
                            value={data.tenant_id}
                            onChange={(e) => setData('tenant_id', e.target.value)}
                            className="rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground focus:border-[#0E9E8E]/50 focus:outline-none"
                        >
                            <option value="">All clinics</option>
                            {tenants.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">Action</label>
                        <input
                            type="text"
                            placeholder="e.g. evaluation.submitted"
                            value={data.action}
                            onChange={(e) => setData('action', e.target.value)}
                            className="w-52 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground placeholder-[#9B9B8E]/50 focus:border-[#0E9E8E]/50 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">From</label>
                        <input
                            type="date"
                            value={data.from}
                            onChange={(e) => setData('from', e.target.value)}
                            className="rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground focus:border-[#0E9E8E]/50 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-muted-foreground">To</label>
                        <input
                            type="date"
                            value={data.to}
                            onChange={(e) => setData('to', e.target.value)}
                            className="rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground focus:border-[#0E9E8E]/50 focus:outline-none"
                        />
                    </div>

                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90 gap-2"
                    >
                        <Search className="h-4 w-4" />
                        Filter
                    </Button>

                    {hasActiveFilters && (
                        <button
                            type="button"
                            onClick={clearFilters}
                            className="flex items-center gap-1.5 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <X className="h-3.5 w-3.5" />
                            Clear
                        </button>
                    )}
                </form>

                {/* Table */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    {entries.data.length === 0 ? (
                        <div className="flex items-center justify-center py-16">
                            <p className="text-sm text-muted-foreground">No audit entries match your filters.</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border">
                                    {['When', 'Clinic', 'User', 'Action', 'Subject', 'IP'].map((h) => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/50">
                                {entries.data.map((e) => (
                                    <tr key={e.id} className="hover:bg-white/[0.02] transition-colors">
                                        <td className="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">
                                            {timeAgo(e.created_at)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="text-sm text-foreground">{e.tenant_name}</span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="text-sm text-foreground">{e.user_name}</p>
                                            {e.user_role && (
                                                <p className="text-xs capitalize text-muted-foreground">{e.user_role}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={['rounded-full px-2 py-0.5 text-xs font-mono font-medium', actionColor(e.action)].join(' ')}>
                                                {e.action}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {e.subject_type ? (
                                                <span>{e.subject_type}</span>
                                            ) : '—'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {e.ip_address ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Pagination */}
                {entries.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            {entries.from}–{entries.to} of {entries.total.toLocaleString()}
                        </span>
                        <div className="flex gap-1">
                            {entries.links
                                .filter((l) => !l.label.includes('...'))
                                .map((link) => (
                                    <button
                                        key={link.label}
                                        disabled={!link.url}
                                        onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                                        className={[
                                            'min-w-[2rem] rounded-md border px-2 py-1 text-xs transition-colors',
                                            link.active
                                                ? 'border-[#0E9E8E] bg-[#0E9E8E]/10 text-[#0E9E8E]'
                                                : 'border-border text-muted-foreground hover:border-[#0E9E8E]/40 hover:text-foreground disabled:opacity-30',
                                        ].join(' ')}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AuditLogPage.layout = {
    breadcrumbs: [
        { title: 'Platform', href: '/admin' },
        { title: 'Audit Log', href: '/admin/audit-log' },
    ],
};
