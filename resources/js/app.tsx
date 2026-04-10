import * as Sentry from '@sentry/react';
import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ── Sentry — client-side error & performance monitoring ──────────────────────
// DSN is baked in at build time via an env variable. If missing, Sentry is
// a no-op so local dev without a DSN works without errors.
if (import.meta.env.VITE_SENTRY_DSN_PUBLIC) {
    Sentry.init({
        dsn: import.meta.env.VITE_SENTRY_DSN_PUBLIC,
        environment: import.meta.env.VITE_APP_ENV ?? 'local',
        release: import.meta.env.VITE_APP_VERSION,

        // Capture 20% of transactions for performance monitoring
        tracesSampleRate: import.meta.env.PROD ? 0.2 : 1.0,

        // Track Inertia page navigations as Sentry transactions
        integrations: [
            Sentry.browserTracingIntegration({
                // Inertia navigation: capture route changes
                beforeStartSpan: (context) => ({
                    ...context,
                    name: window.location.pathname,
                }),
            }),
        ],
    });

    // Attach the Inertia page component name as a Sentry tag on every navigation
    router.on('navigate', () => {
        Sentry.setTag('inertia.page', document.title);
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('intake/'):   // patient-facing — no chrome
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
