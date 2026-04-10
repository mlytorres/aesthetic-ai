import * as Sentry from '@sentry/react';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';

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

        integrations: [
            Sentry.browserTracingIntegration(),
        ],
    });
}

createInertiaApp({
    title: (title) => `${title} — ${appName}`,

    resolve: (name) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true }) as any;
        return resolvePageComponent(`./pages/${name}.tsx`, pages);
    },

    setup({ el, App, props }) {
        createRoot(el).render(
            <TooltipProvider>
                <App {...props} />
                <Toaster />
            </TooltipProvider>,
        );
    },

    progress: {
        color: '#C9A84C',
    },
});

initializeTheme();
