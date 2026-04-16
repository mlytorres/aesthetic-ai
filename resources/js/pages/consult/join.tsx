import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface ConsultationProps {
    id: string;
    scheduled_at: string;
    duration_minutes: number;
    status: string;
    daily_room_url: string;
    meeting_token: string;
    clinic_name: string;
    patient_name: string;
}

interface Props {
    consultation: ConsultationProps;
}

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZoneName: 'short',
    }).format(new Date(iso));
}

function isWithin15Min(scheduledAt: string, durationMinutes: number): boolean {
    const start = new Date(scheduledAt).getTime();
    const end = start + durationMinutes * 60 * 1000;
    const now = Date.now();

    return now >= start - 15 * 60 * 1000 && now <= end;
}

export default function ConsultJoin({ consultation }: Props) {
    const [joined, setJoined] = useState(false);

    const canJoin = isWithin15Min(
        consultation.scheduled_at,
        consultation.duration_minutes,
    );

    // Append the Daily meeting token to the room URL so the patient is admitted
    // to the private room without needing a Daily account.
    const roomUrl = `${consultation.daily_room_url}?t=${consultation.meeting_token}`;

    return (
        <>
            <Head title={`Video Consultation — ${consultation.clinic_name}`} />

            <div className="flex min-h-screen flex-col bg-[#0A0A0F] text-[#F5F0E8]">
                {/* Header */}
                <header className="flex items-center justify-between border-b border-white/5 px-6 py-4">
                    <div>
                        <p className="text-sm font-semibold tracking-wide">
                            {consultation.clinic_name}
                        </p>
                        <p className="text-xs text-[#9B9B8E]">
                            Video Consultation
                        </p>
                    </div>
                    <span className="rounded-full bg-[#0E9E8E]/15 px-3 py-1 text-[11px] font-medium text-[#0E9E8E]">
                        Secure · Encrypted
                    </span>
                </header>

                {joined ? (
                    /* ── Daily.co iframe ──────────────────────────────────────── */
                    <div className="flex flex-1">
                        <iframe
                            src={roomUrl}
                            title="Video Consultation"
                            allow="camera; microphone; fullscreen; speaker; display-capture"
                            className="flex-1 border-0"
                            style={{ minHeight: 'calc(100vh - 65px)' }}
                        />
                    </div>
                ) : (
                    /* ── Waiting / Pre-join screen ────────────────────────────── */
                    <main className="flex flex-1 flex-col items-center justify-center px-4 py-12">
                        <div className="w-full max-w-md space-y-6 text-center">
                            {/* Video icon */}
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[#0E9E8E]/10 ring-1 ring-[#0E9E8E]/30">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    className="h-8 w-8 text-[#0E9E8E]"
                                >
                                    <path d="M4.5 4.5a3 3 0 0 0-3 3v9a3 3 0 0 0 3 3h8.25a3 3 0 0 0 3-3v-9a3 3 0 0 0-3-3H4.5ZM19.94 18.75l-2.69-2.69V7.94l2.69-2.69c.944-.945 2.56-.276 2.56 1.06v11.38c0 1.336-1.616 2.005-2.56 1.06Z" />
                                </svg>
                            </div>

                            <div>
                                <h1 className="text-2xl font-light tracking-tight">
                                    Hi, {consultation.patient_name}
                                </h1>
                                <p className="mt-1 text-sm text-[#9B9B8E]">
                                    Your consultation with{' '}
                                    {consultation.clinic_name}
                                </p>
                            </div>

                            {/* Appointment details */}
                            <div className="rounded-xl border border-white/5 bg-white/[0.03] px-6 py-4">
                                <p className="text-xs tracking-wider text-[#9B9B8E] uppercase">
                                    Scheduled for
                                </p>
                                <p className="mt-1 text-sm font-medium text-[#F5F0E8]">
                                    {formatDate(consultation.scheduled_at)}
                                </p>
                                <p className="mt-0.5 text-xs text-[#9B9B8E]">
                                    Duration: {consultation.duration_minutes}{' '}
                                    minutes
                                </p>
                            </div>

                            {canJoin ? (
                                <div className="space-y-3">
                                    <p className="text-xs text-[#9B9B8E]">
                                        Your consultation is ready. Make sure
                                        your camera and microphone are working
                                        before joining.
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => setJoined(true)}
                                        className="w-full rounded-lg bg-[#0E9E8E] px-6 py-3 text-sm font-semibold text-[#0A0A0F] transition-colors hover:bg-[#0E9E8E]/90 active:scale-95"
                                    >
                                        Join Video Call
                                    </button>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <p className="text-xs text-[#9B9B8E]">
                                        The "Join" button will appear 15 minutes
                                        before your scheduled time. Please
                                        return then.
                                    </p>
                                    <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 px-4 py-3">
                                        <p className="text-xs text-amber-400">
                                            Consultation starts{' '}
                                            {formatDate(
                                                consultation.scheduled_at,
                                            )}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <p className="text-[11px] text-[#9B9B8E]">
                                No downloads required. Works in Chrome, Firefox,
                                Safari, and Edge.
                            </p>
                        </div>
                    </main>
                )}

                {/* Footer */}
                <footer className="border-t border-white/5 py-3 text-center">
                    <p className="text-[11px] text-[#9B9B8E]">
                        Your information is encrypted and protected under HIPAA.
                    </p>
                </footer>
            </div>
        </>
    );
}
