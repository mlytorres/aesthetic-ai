import { useCallback, useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * HIPAA-compliant session inactivity timeout hook.
 *
 * Tracks user activity (mouse, keyboard, scroll, touch) and:
 *   - Warns the coordinator at `warnAfterMs` (default 28 min)
 *   - Automatically logs out at `timeoutMs` (default 30 min)
 *
 * The warning dialog allows the user to extend their session by calling
 * the `/keepalive` endpoint, which resets the server-side session clock.
 *
 * Usage:
 *   const { showWarning, extendSession, remainingSeconds } = useSessionTimeout();
 */

const TIMEOUT_MS   = 30 * 60 * 1000; // 30 minutes
const WARN_BEFORE  = 2  * 60 * 1000; // warn 2 minutes before expiry
const WARN_AFTER_MS = TIMEOUT_MS - WARN_BEFORE;
const TICK_MS      = 10_000;          // check every 10 seconds

interface UseSessionTimeoutReturn {
    showWarning: boolean;
    remainingSeconds: number;
    extendSession: () => void;
    logout: () => void;
}

export function useSessionTimeout(): UseSessionTimeoutReturn {
    const lastActivityRef = useRef<number>(Date.now());
    const [showWarning, setShowWarning] = useState(false);
    const [remainingSeconds, setRemainingSeconds] = useState(WARN_BEFORE / 1000);
    const loggedOutRef = useRef(false);

    // Reset the inactivity clock on any meaningful user interaction.
    const handleActivity = useCallback(() => {
        lastActivityRef.current = Date.now();

        // If warning was showing but user became active again, dismiss it.
        if (showWarning) {
            setShowWarning(false);
            setRemainingSeconds(WARN_BEFORE / 1000);
        }
    }, [showWarning]);

    useEffect(() => {
        const events = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart', 'click'];

        events.forEach((event) => window.addEventListener(event, handleActivity, { passive: true }));

        return () => {
            events.forEach((event) => window.removeEventListener(event, handleActivity));
        };
    }, [handleActivity]);

    // Periodic tick to check inactivity duration.
    useEffect(() => {
        const interval = setInterval(() => {
            if (loggedOutRef.current) {
                return;
            }

            const idle = Date.now() - lastActivityRef.current;

            if (idle >= TIMEOUT_MS) {
                loggedOutRef.current = true;
                clearInterval(interval);
                router.post('/logout');
                return;
            }

            if (idle >= WARN_AFTER_MS) {
                setShowWarning(true);
                setRemainingSeconds(Math.ceil((TIMEOUT_MS - idle) / 1000));
            } else {
                setShowWarning(false);
                setRemainingSeconds(WARN_BEFORE / 1000);
            }
        }, TICK_MS);

        return () => clearInterval(interval);
    }, []);

    // Countdown tick while warning is visible.
    useEffect(() => {
        if (!showWarning) {
            return;
        }

        const countdown = setInterval(() => {
            setRemainingSeconds((prev) => {
                if (prev <= 1) {
                    clearInterval(countdown);
                    return 0;
                }

                return prev - 1;
            });
        }, 1_000);

        return () => clearInterval(countdown);
    }, [showWarning]);

    const extendSession = useCallback(() => {
        // Ping keepalive to reset server-side session, then reset client clock.
        fetch('/keepalive', { credentials: 'same-origin' }).catch(() => {});
        lastActivityRef.current = Date.now();
        setShowWarning(false);
        setRemainingSeconds(WARN_BEFORE / 1000);
    }, []);

    const logout = useCallback(() => {
        loggedOutRef.current = true;
        router.post('/logout');
    }, []);

    return { showWarning, remainingSeconds, extendSession, logout };
}
