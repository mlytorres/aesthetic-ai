import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { useEffect } from 'react';

// Initialise Echo once and reuse across the app.
// Reverb uses the Pusher protocol, so we configure pusher-js as the transport.
let echo: Echo<'reverb'> | null = null;

function getEcho(): Echo<'reverb'> {
    if (echo) {
        return echo;
    }

    (window as unknown as Record<string, unknown>).Pusher = Pusher;

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY as string,
        wsHost: import.meta.env.VITE_REVERB_HOST as string,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return echo;
}

interface EvaluationReceivedPayload {
    evaluation_id: string;
    procedure_slug: string;
    created_at: string;
}

/**
 * Subscribe to the clinic's private Reverb channel and listen for new evaluations.
 *
 * @param tenantId   The tenant UUID (from shared Inertia props). Null for super-admins.
 * @param onReceived Called with the broadcast payload each time an evaluation arrives.
 */
export function useEvaluationNotifications(
    tenantId: string | null | undefined,
    onReceived: (payload: EvaluationReceivedPayload) => void,
): void {
    useEffect(() => {
        if (!tenantId) {
            return;
        }

        const channel = getEcho().private(`tenant.${tenantId}`);

        channel.listen('.evaluation.received', onReceived);

        return () => {
            channel.stopListening('.evaluation.received');
        };
    }, [tenantId, onReceived]);
}
