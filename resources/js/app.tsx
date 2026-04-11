import { createInertiaApp, router } from '@inertiajs/react';
// import * as Sentry from '@sentry/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { hydrateRoot } from 'react-dom/client';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// ── Sentry — client-side error & performance monitoring ──────────────────────
// DSN is baked in at build time via an env variable. If missing, Sentry is
// a no-op so local dev without a DSN works without errors.
// if (import.meta.env.VITE_SENTRY_DSN_PUBLIC) {
//     Sentry.init({
//         dsn: import.meta.env.VITE_SENTRY_DSN_PUBLIC,
//         environment: import.meta.env.VITE_APP_ENV ?? 'local',
//         release: import.meta.env.VITE_APP_VERSION,
//
//         // Capture 20% of transactions for performance monitoring
//         tracesSampleRate: import.meta.env.PROD ? 0.2 : 1.0,
//
//         integrations: [
//             Sentry.browserTracingIntegration(),
//         ],
//     });
// }

// ── Layout resolver ───────────────────────────────────────────────────────────
// Inertia v3's Page.layout = { ... } (props object) pattern requires a
// defaultLayout function here. Without it, pages render with no wrapping layout
// (no sidebar, no header). The resolver returns the correct layout per page group:
//   auth/*     → AuthLayout   (centred auth shell)
//   intake/*   → null         (patient-facing wizard — manages its own layout)
//   welcome    → null         (public landing page)
//   *          → AppLayout    (authenticated app shell with sidebar)
function resolveLayout(component: string) {
    if (component.startsWith('auth/')) {
        return AuthLayout;
    }

    if (component.startsWith('intake/') || component === 'welcome') {
        return null;
    }

    return AppLayout;
}

createInertiaApp({
    title: (title) => `${title} — ${appName}`,

    layout: resolveLayout,

    resolve: (name) => {

        const pages = import.meta.glob('./pages/**/*.tsx') as any;

        return resolvePageComponent(`./pages/${name}.tsx`, pages);
    },

    setup({ el, App, props }) {
        if (import.meta.env.SSR) {
            return (
                <TooltipProvider>
                    <App {...props} />
                    <Toaster />
                </TooltipProvider>
            );
        }

        const appElement = (
            <TooltipProvider>
                <App {...props} />
                <Toaster />
            </TooltipProvider>
        );

        if (el?.hasChildNodes()) {
            hydrateRoot(el, appElement);
        } else {
            import('react-dom/client').then(({ createRoot }) => {
                createRoot(el!).render(appElement);
            });
        }

        if (import.meta.env.DEV) {
            router.on('invalid', (event) => {
                console.log('Inertia invalid event:', event);
                // event.preventDefault();
            });
            router.on('exception', (event) => {
                console.log('Inertia exception event:', event);
            });
            router.on('finish', (event) => {
                console.log('Inertia finish event:', event);
            });
            router.on('navigate', (event) => {
                console.log('Inertia navigate event:', event);
            });
        }
    },

    progress: {
        color: '#C9A84C',
    },
});

initializeTheme();
