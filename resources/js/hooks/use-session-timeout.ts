import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

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
 *   const { showWarning, extendSession, remainingSeconds, logout } = useSessionTimeout();
 */

const TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes
const WARN_BEFORE = 2 * 60 * 1000; // warn 2 minutes before expiry
const WARN_AFTER_MS = TIMEOUT_MS - WARN_BEFORE;
const TICK_MS = 10_000; // check every 10 seconds

interface UseSessionTimeoutReturn {
    showWarning: boolean;
    remainingSeconds: number;
    extendSession: () => void;
    logout: () => void;
}

export function useSessionTimeout(): UseSessionTimeoutReturn {
    const lastActivityRef = useRef<number>(0);

    // Initialize clock on mount safely
    useEffect(() => {
        lastActivityRef.current = Date.now();
    }, []);

    const [showWarning, setShowWarning] = useState(false);
    const [remainingSeconds, setRemainingSeconds] = useState(
        WARN_BEFORE / 1000,
    );
    const loggedOutRef = useRef(false);

    // Provide a stable callback for activity registration
    const handleActivity = useCallback(() => {
        lastActivityRef.current = Date.now();

        setShowWarning((prevWarning) => {
            if (prevWarning) {
                // If warning was active, user interacted, meaning they want to stay
                setRemainingSeconds(WARN_BEFORE / 1000);

                return false;
            }

            return prevWarning;
        });
    }, []);

    // Listen to user events system-wide
    useEffect(() => {
        const events = [
            'mousemove',
            'mousedown',
            'keypress',
            'scroll',
            'touchstart',
            'click',
        ];
        events.forEach((event) =>
            window.addEventListener(event, handleActivity, { passive: true }),
        );

        return () => {
            events.forEach((event) =>
                window.removeEventListener(event, handleActivity),
            );
        };
    }, [handleActivity]);

    // Periodic check for inactivity
    useEffect(() => {
        const interval = setInterval(() => {
            if (loggedOutRef.current || lastActivityRef.current === 0) {
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

    // Live countdown timer while warning is shown
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
        }, 1000);

        return () => clearInterval(countdown);
    }, [showWarning]);

    const extendSession = useCallback(() => {
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
