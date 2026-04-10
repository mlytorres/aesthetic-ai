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
import { useSessionTimeout } from '@/hooks/use-session-timeout';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const { showWarning, remainingSeconds, extendSession, logout } = useSessionTimeout();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>

            {/* HIPAA session inactivity warning — no onOpenChange so user cannot dismiss by clicking outside */}
            <Dialog open={showWarning} onOpenChange={() => {}}>
                <DialogContent
                    className="border-[#2A2A3A] bg-[#13131A] text-[#F5F0E8] [&>button]:hidden"
                    onInteractOutside={(e) => e.preventDefault()}
                    onEscapeKeyDown={(e) => e.preventDefault()}
                >
                    <DialogHeader>
                        <DialogTitle className="text-[#F5F0E8]">
                            Session Expiring Soon
                        </DialogTitle>
                        <DialogDescription className="text-[#9B9B8E]">
                            For HIPAA compliance, your session will automatically end after 30
                            minutes of inactivity. You will be logged out in{' '}
                            <span className="font-semibold text-[#C9A84C]">
                                {remainingSeconds}s
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <button
                            onClick={logout}
                            className="rounded-md border border-[#2A2A3A] bg-transparent px-4 py-2 text-sm text-[#9B9B8E] transition-colors hover:bg-[#1E1E28] hover:text-[#F5F0E8]"
                        >
                            Log Out
                        </button>
                        <button
                            onClick={extendSession}
                            className="rounded-md bg-[#C9A84C] px-4 py-2 text-sm font-medium text-[#0A0A0F] transition-colors hover:bg-[#B8943D]"
                        >
                            Stay Logged In
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppShell>
    );
}
