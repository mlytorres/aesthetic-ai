export type ResolvedAppearance = 'light' | 'dark';
export type Appearance = ResolvedAppearance | 'system';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.add('dark');
    document.documentElement.style.colorScheme = 'dark';
}

export function useAppearance(): UseAppearanceReturn {
    // We strictly enforce dark mode. Return a dummy but correct signature stub.
    return { 
        appearance: 'dark', 
        resolvedAppearance: 'dark', 
        updateAppearance: () => {} 
    } as const;
}
