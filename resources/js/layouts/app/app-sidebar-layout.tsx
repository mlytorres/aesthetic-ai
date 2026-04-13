import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useEvaluationNotifications } from '@/hooks/use-echo';
import { useSessionTimeout } from '@/hooks/use-session-timeout';
import type { AppLayoutProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { toast } from 'sonner';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { showWarning, remainingSeconds, extendSession, logout } = useSessionTimeout();
    const { impersonating, tenantId } = usePage().props as unknown as { impersonating: { as: string } | null; tenantId: string | null };

    // ── Real-time evaluation notifications via Reverb ──────────────────────────
    const handleEvaluationReceived = useCallback(
        (payload: { evaluation_id: string; procedure_slug: string; created_at: string }) => {
            const label = payload.procedure_slug
                .replace(/_/g, ' ')
                .replace(/\b\w/g, (c) => c.toUpperCase());

            toast('New evaluation received', {
                description: label,
                action: {
                    label: 'View',
                    onClick: () => router.visit('/evaluations'),
                },
                duration: 8000,
            });
        },
        [],
    );

    useEvaluationNotifications(tenantId, handleEvaluationReceived);

    const stopImpersonating = () => {
        router.delete('/impersonate');
    };

    return (
        <>
        {impersonating && (
            <div className="fixed top-0 left-0 right-0 z-50 flex items-center justify-between bg-amber-400 px-4 py-2 shadow-md">
                <span className="text-sm font-semibold text-black">
                    👤 Impersonating <strong>{impersonating.as}</strong> — changes you make affect this tenant's data.
                </span>
                <button
                    type="button"
                    onClick={stopImpersonating}
                    className="rounded bg-black/10 px-3 py-1 text-xs font-bold text-black hover:bg-black/20 transition-colors"
                >
                    Stop Impersonating
                </button>
            </div>
        )}
        <div className={impersonating ? 'pt-10' : undefined}>
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>

            {/* HIPAA session inactivity warning — no onOpenChange so user cannot dismiss by clicking outside */}
            <Dialog open={showWarning} onOpenChange={() => {}}>
                <DialogContent
                    className="border-border bg-card text-foreground [&>button]:hidden"
                    onInteractOutside={(e) => e.preventDefault()}
                    onEscapeKeyDown={(e) => e.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle className="text-foreground">
                            Session Expiring Soon
                        </DialogTitle>
                        <DialogDescription className="text-muted-foreground">
                            For HIPAA compliance, your session will automatically end after 30
                            minutes of inactivity. You will be logged out in{' '}
                            <span className="font-semibold text-[#0E9E8E]">
                                {remainingSeconds}s
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <button
                            onClick={logout}
                            className="rounded-md border border-border bg-transparent px-4 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            Log Out
                        </button>
                        <button
                            onClick={extendSession}
                            className="rounded-md bg-[#0E9E8E] px-4 py-2 text-sm font-medium text-[#0A0A0F] transition-colors hover:bg-[#B8943D]"
                        >
                            Stay Logged In
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppShell>
        </div>
        </>
    );
}
