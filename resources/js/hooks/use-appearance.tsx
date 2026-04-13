import { useCallback, useEffect, useState } from 'react';

export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

const STORAGE_KEY = 'appearance';
const DEFAULT: Appearance = 'dark';

function getSystemTheme(): ResolvedAppearance {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(resolved: ResolvedAppearance): void {
    const root = document.documentElement;
    root.classList.toggle('dark', resolved === 'dark');
    root.style.colorScheme = resolved;
}

function resolve(mode: Appearance): ResolvedAppearance {
    return mode === 'system' ? getSystemTheme() : mode;
}

/**
 * Called once in app.tsx before React mounts, so the correct theme class is
 * set synchronously — avoids a flash of wrong theme on first paint.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const stored = (localStorage.getItem(STORAGE_KEY) as Appearance | null) ?? DEFAULT;
    applyTheme(resolve(stored));
}

export function useAppearance(): UseAppearanceReturn {
    const [appearance, setAppearance] = useState<Appearance>(
        () => (localStorage.getItem(STORAGE_KEY) as Appearance | null) ?? DEFAULT,
    );

    const resolvedAppearance = resolve(appearance);

    const updateAppearance = useCallback((mode: Appearance): void => {
        localStorage.setItem(STORAGE_KEY, mode);
        setAppearance(mode);
        applyTheme(resolve(mode));
    }, []);

    // Keep "system" mode in sync if the OS theme changes while the page is open.
    useEffect(() => {
        if (appearance !== 'system') {
            return;
        }

        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyTheme(getSystemTheme());

        mq.addEventListener('change', handler);

        return () => mq.removeEventListener('change', handler);
    }, [appearance]);

    return { appearance, resolvedAppearance, updateAppearance } as const;
}
