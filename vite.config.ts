import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        hmr: false,
    },
    build: {
        // Mermaid's shared core (dynamically imported only in the help-center
        // diagram renderer) is a lazy-loaded ~600kB chunk — not part of the main
        // bundle. Raise the limit so Vite stops flagging it as a false positive.
        chunkSizeWarningLimit: 700,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: false,
            detectTls: 'aesthetic-ai.test',
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder(),
    ],
});
