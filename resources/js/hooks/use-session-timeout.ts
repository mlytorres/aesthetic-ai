import { useCallback } from 'react';

interface UseSessionTimeoutReturn {
    showWarning: boolean;
    remainingSeconds: number;
    extendSession: () => void;
    logout: () => void;
}

export function useSessionTimeout(): UseSessionTimeoutReturn {
    // TEMPORARILY DISABLED to debug auto-refresh issue
    const extendSession = useCallback(() => {}, []);
    const logout = useCallback(() => {}, []);

    return {
        showWarning: false,
        remainingSeconds: 0,
        extendSession,
        logout
    };
}
