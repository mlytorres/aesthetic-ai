import { Deferred, Head, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { brief, index, show, updateNotes, updateStatus } from '@/routes/evaluations';
import { show as simulationShow, store as simulationStore } from '@/routes/evaluations/simulation';

// ── Types ────────────────────────────────────────────────────────────────────

interface Patient {
    id: string;
    name: string | null;
    email: string | null;
    phone: string | null;
}

interface Photo {
    id: string;
    type: string;
    quality_score: number | null;
    analysis_status: string;
    signed_url: string;
}

interface Evaluation {
    id: string;
    procedure_slug: string;
    status: string;
    lead_score: number | null;
    priority: string;
    coordinator_notes: string | null;
    follow_up_at: string | null;
    completed_at: string | null;
    created_at: string;
    quiz_answers: Record<string, unknown> | null;
    analysis_data: Record<string, unknown> | null;
    simulation_status: string | null;
    simulation_data: Record<string, unknown> | null;
    simulation_requested_at: string | null;
    patient: Patient | null;
    photos: Photo[];
}

interface AuditEntry {
    id:          string;
    action:      string;
    user_name:   string;
    user_role:   string | null;
    ip_address:  string | null;
    metadata:    Record<string, unknown> | null;
    created_at:  string;
}

interface Props {
    evaluation:  Evaluation;
    auditEntries?: AuditEntry[];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_COLORS: Record<string, string> = {
    analyzing: 'bg-blue-500/15 text-blue-400 border-blue-500/30',
    complete:  'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
    contacted: 'bg-purple-500/15 text-purple-400 border-purple-500/30',
    booked:    'bg-[#0E9E8E]/15 text-[#0E9E8E] border-[#0E9E8E]/30',
    no_show:   'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
    not_a_fit: 'bg-zinc-500/15 text-zinc-400 border-zinc-500/30',
};

const PHOTO_TYPE_LABELS: Record<string, string> = {
    front:         'Frontal',
    left_profile:  'Left Profile',
    right_profile: 'Right Profile',
    additional:    'Additional',
};

function formatProcedure(slug: string): string {
    return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatDate(iso: string | null | undefined): string {
    if (!iso) {
return '—';
}

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(iso));
}

// ── Sub-components ────────────────────────────────────────────────────────────

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-sidebar-border/50 bg-[#111118]">
            <div className="border-b border-sidebar-border/50 px-5 py-3">
                <h3 className="text-sm font-semibold text-[#F5F0E8]">{title}</h3>
            </div>
            <div className="p-5">{children}</div>
        </div>
    );
}

function PatientCard({ patient }: { patient: Patient | null }) {
    if (!patient) {
        return (
            <SectionCard title="Patient">
                <p className="text-sm text-[#9B9B8E] italic">Patient info not yet collected.</p>
            </SectionCard>
        );
    }

    return (
        <SectionCard title="Patient">
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                {[
                    { label: 'Name',  value: patient.name },
                    { label: 'Email', value: patient.email },
                    { label: 'Phone', value: patient.phone },
                ].map(({ label, value }) => (
                    <div key={label}>
                        <dt className="text-xs text-[#9B9B8E] uppercase tracking-wider">{label}</dt>
                        <dd className="mt-0.5 text-sm text-[#F5F0E8]">{value ?? '—'}</dd>
                    </div>
                ))}
            </dl>
        </SectionCard>
    );
}

function PhotosGallery({ photos }: { photos: Photo[] }) {
    const [lightbox, setLightbox] = useState<string | null>(null);

    if (photos.length === 0) {
        return (
            <SectionCard title="Photos">
                <p className="text-sm text-[#9B9B8E] italic">No photos uploaded yet.</p>
            </SectionCard>
        );
    }

    return (
        <SectionCard title={`Photos (${photos.length})`}>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {photos.map((photo) => (
                    <button
                        key={photo.id}
                        type="button"
                        onClick={() => setLightbox(photo.signed_url)}
                        className="group relative overflow-hidden rounded-lg border border-sidebar-border/50 bg-[#0A0A0F] aspect-square focus:outline-none focus:ring-2 focus:ring-[#0E9E8E]"
                    >
                        <img
                            src={photo.signed_url}
                            alt={PHOTO_TYPE_LABELS[photo.type] ?? photo.type}
                            className="h-full w-full object-cover transition-transform group-hover:scale-105"
                        />
                        <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 px-2 py-1.5">
                            <p className="text-xs font-medium text-white">
                                {PHOTO_TYPE_LABELS[photo.type] ?? photo.type}
                            </p>
                            {photo.quality_score !== null && (
                                <p className="text-[10px] text-white/70">
                                    Q: {photo.quality_score}
                                </p>
                            )}
                        </div>
                        <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity bg-black/30">
                            <span className="text-xs font-medium text-white">View Full</span>
                        </div>
                    </button>
                ))}
            </div>

            {/* Lightbox */}
            {lightbox && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/90"
                    onClick={() => setLightbox(null)}
                >
                    <img
                        src={lightbox}
                        alt="Full size photo"
                        className="max-h-[90vh] max-w-[90vw] rounded-lg object-contain"
                        onClick={(e) => e.stopPropagation()}
                    />
                    <button
                        type="button"
                        onClick={() => setLightbox(null)}
                        className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
                    >
                        ✕
                    </button>
                </div>
            )}
        </SectionCard>
    );
}

function QuizAnswersCard({ answers }: { answers: Record<string, unknown> | null }) {
    if (!answers) {
        return (
            <SectionCard title="Quiz Answers">
                <p className="text-sm text-[#9B9B8E] italic">No quiz answers recorded.</p>
            </SectionCard>
        );
    }

    // Filter out internal metadata key
    const displayAnswers = Object.entries(answers).filter(([k]) => !k.startsWith('_'));

    return (
        <SectionCard title="Quiz Answers">
            <dl className="space-y-2">
                {displayAnswers.length === 0 ? (
                    <p className="text-sm text-[#9B9B8E] italic">No answers recorded.</p>
                ) : (
                    displayAnswers.map(([key, value]) => (
                        <div key={key} className="grid grid-cols-5 gap-2">
                            <dt className="col-span-2 text-xs text-[#9B9B8E] capitalize">
                                {key.replace(/_/g, ' ')}
                            </dt>
                            <dd className="col-span-3 text-sm text-[#F5F0E8]">
                                {Array.isArray(value)
                                    ? value.join(', ')
                                    : String(value ?? '—')}
                            </dd>
                        </div>
                    ))
                )}
            </dl>
        </SectionCard>
    );
}

// ── Audit timeline ────────────────────────────────────────────────────────────

const ACTION_LABELS: Record<string, string> = {
    'evaluation.photos.viewed':        'Viewed evaluation',
    'evaluation.brief.downloaded':     'Downloaded clinical brief',
    'evaluation.status.changed':       'Updated status',
    'evaluation.photo.uploaded':       'Photo uploaded',
    'evaluation.submitted':            'Evaluation submitted',
    'ai.photo_quality.validated':      'AI: Photo quality validated',
    'ai.landmarks.extracted':          'AI: Landmarks extracted',
    'ai.proportions.calculated':       'AI: Proportions calculated',
    'ai.recommendations.generated':    'AI: Recommendations generated',
    'coordinator.magic_link.used':     'Accessed via magic link',
};

function actionLabel(action: string): string {
    return ACTION_LABELS[action] ?? action.replace(/\./g, ' › ');
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60_000);

    if (mins < 1)  {
return 'just now';
}

    if (mins < 60) {
return `${mins}m ago`;
}

    const hrs = Math.floor(mins / 60);

    if (hrs < 24)  {
return `${hrs}h ago`;
}

    return `${Math.floor(hrs / 24)}d ago`;
}

function AuditTimeline({ entries }: { entries: AuditEntry[] }) {
    if (entries.length === 0) {
        return (
            <SectionCard title="Activity Log">
                <p className="text-sm italic text-[#9B9B8E]">No activity recorded yet.</p>
            </SectionCard>
        );
    }

    return (
        <SectionCard title="Activity Log">
            <ol className="relative border-l border-[#2A2A35]">
                {entries.map((entry) => (
                    <li key={entry.id} className="mb-5 ml-4 last:mb-0">
                        {/* Timeline dot */}
                        <div className="absolute -left-1.5 mt-1.5 size-3 rounded-full border border-[#0A0A0F] bg-[#2A2A35]" />

                        <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                            <p className="text-sm font-medium text-[#F5F0E8]">
                                {actionLabel(entry.action)}
                            </p>
                            <time className="shrink-0 text-xs text-[#9B9B8E]" dateTime={entry.created_at}>
                                {timeAgo(entry.created_at)}
                            </time>
                        </div>

                        <p className="mt-0.5 text-xs text-[#9B9B8E]">
                            {entry.user_name}
                            {entry.user_role && (
                                <span className="ml-1 capitalize opacity-60">· {entry.user_role}</span>
                            )}
                            {entry.ip_address && (
                                <span className="ml-1 font-mono opacity-40"> · {entry.ip_address}</span>
                            )}
                        </p>

                        {/* Metadata badge for status changes */}
                        {entry.metadata?.new_status != null && (
                            <span className="mt-1 inline-block rounded bg-[#1E1E28] px-1.5 py-0.5 text-[10px] capitalize text-[#0E9E8E]">
                                → {String(entry.metadata.new_status).replace('_', ' ')}
                            </span>
                        )}
                    </li>
                ))}
            </ol>
        </SectionCard>
    );
}

// ── AI Simulation Viewer ──────────────────────────────────────────────────────

interface SimulationStatus {
    status: string | null;
    simulation_data: Record<string, unknown> | null;
    simulation_url: string | null;
    share_url: string | null;
    requested_at: string | null;
}

function SimulationViewer({ evaluation }: { evaluation: Evaluation }) {
    const [sim, setSim] = useState<SimulationStatus>({
        status: evaluation.simulation_status,
        simulation_data: evaluation.simulation_data,
        simulation_url: null,
        share_url: null,
        requested_at: evaluation.simulation_requested_at,
    });
    const [requesting, setRequesting] = useState(false);
    const [copied, setCopied] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = useCallback(() => {
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }
    }, []);

    const pollStatus = useCallback(async () => {
        try {
            const res = await fetch(simulationShow.url(evaluation.id), {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
return;
}

            const data: SimulationStatus = await res.json();
            setSim(data);

            if (data.status === 'complete' || data.status === 'failed') {
                stopPolling();
            }
        } catch {
            // Network error — keep polling
        }
    }, [evaluation.id, stopPolling]);

    // On mount, if the simulation is already complete, fetch once to hydrate the signed URL.
    // simulation_url is a temporary S3 signed URL — it's never stored in the DB or passed as
    // an Inertia prop, so we must fetch it from the API on every page load.
    useEffect(() => {
        if (evaluation.simulation_status === 'complete') {
            pollStatus();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Start polling when status is pending/processing
    useEffect(() => {
        if (sim.status === 'pending' || sim.status === 'processing') {
            pollRef.current = setInterval(pollStatus, 4000);
        }

        return stopPolling;
    }, [sim.status, pollStatus, stopPolling]);

    const requestSimulation = async () => {
        setRequesting(true);

        try {
            const res = await fetch(simulationStore.url(evaluation.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
            });
            const data = await res.json();
            setSim((prev) => ({ ...prev, status: data.status }));
        } finally {
            setRequesting(false);
        }
    };

    // Ready when analysis is complete — body procedures store 'body_proportions',
    // face procedures store 'proportions'. Either key signals analysis is done.
    const isAnalysisReady =
        evaluation.status === 'complete' ||
        evaluation.analysis_data?.body_proportions != null ||
        evaluation.analysis_data?.proportions != null;

    const copyShareLink = async () => {
        if (!sim.share_url) return;
        await navigator.clipboard.writeText(sim.share_url);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <SectionCard title="AI Simulation">
            {!isAnalysisReady && sim.status === null ? (
                <p className="text-xs text-[#9B9B8E] italic">
                    Simulation will be available once AI analysis is complete.
                </p>

            ) : (sim.status === null) ? (
                <div className="space-y-3">
                    <p className="text-xs text-[#9B9B8E]">
                        Generate an AI before/after simulation for this patient's procedure.
                        The result is for consultation purposes only and is not a medical guarantee.
                    </p>
                    <Button
                        onClick={requestSimulation}
                        disabled={requesting}
                        className="w-full bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90 text-sm"
                    >
                        {requesting ? 'Requesting…' : '✦ Generate Simulation'}
                    </Button>
                </div>
            ) : sim.status === 'pending' || sim.status === 'processing' ? (
                <div className="flex flex-col items-center gap-3 py-4">
                    <div className="size-8 animate-spin rounded-full border-2 border-[#0E9E8E] border-t-transparent" />
                    <p className="text-xs text-[#9B9B8E]">
                        {sim.status === 'pending' ? 'Queued…' : 'Generating simulation…'}
                    </p>
                </div>
            ) : sim.status === 'failed' ? (
                <div className="space-y-3">
                    <p className="text-xs text-red-400">Simulation failed. Please try again.</p>
                    <Button
                        onClick={requestSimulation}
                        disabled={requesting}
                        variant="outline"
                        className="w-full text-sm border-sidebar-border/50 text-[#F5F0E8]"
                    >
                        {requesting ? 'Requesting…' : 'Retry Simulation'}
                    </Button>
                </div>
            ) : sim.status === 'complete' ? (
                <div className="space-y-3">
                    {sim.simulation_url ? (
                        <img
                            src={sim.simulation_url}
                            alt="AI simulation result"
                            className="w-full rounded-lg border border-sidebar-border/50 object-cover"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-[#0E9E8E]/30 bg-[#0A0A0F] py-8 px-4">
                            <span className="text-2xl">✦</span>
                            <p className="text-center text-xs text-[#9B9B8E]">
                                {sim.simulation_data?.placeholder_message as string
                                    ?? 'Simulation complete. Enable AI Vision to view generated images.'}
                            </p>
                        </div>
                    )}

                    <p className="text-[10px] text-[#9B9B8E] italic text-center">
                        ⚠ AI simulation for consultation purposes only. Results are not a medical guarantee.
                    </p>

                    {sim.share_url && (
                        <Button
                            onClick={copyShareLink}
                            variant="outline"
                            className="w-full text-xs border-[#C9A84C]/40 text-[#C9A84C] hover:text-[#C9A84C]/80"
                        >
                            {copied ? '✓ Link copied!' : '⎘ Copy patient share link'}
                        </Button>
                    )}

                    <Button
                        onClick={requestSimulation}
                        disabled={requesting}
                        variant="outline"
                        className="w-full text-xs border-sidebar-border/50 text-[#9B9B8E] hover:text-[#F5F0E8]"
                    >
                        {requesting ? 'Requesting…' : 'Regenerate'}
                    </Button>
                </div>
            ) : null}
        </SectionCard>
    );
}

// ── Status update panel ───────────────────────────────────────────────────────

interface StatusFormFields {
    status: string;
    coordinator_notes: string;
}

interface NotesFormFields {
    coordinator_notes: string;
    follow_up_at: string;
}

function CoordinatorPanel({ evaluation }: { evaluation: Evaluation }) {
    const COORDINATOR_STATUSES = [
        { value: 'contacted',  label: 'Contacted' },
        { value: 'booked',     label: 'Booked' },
        { value: 'no_show',    label: 'No Show' },
        { value: 'not_a_fit',  label: 'Not a Fit' },
    ] as const;

    const statusForm = useForm<StatusFormFields>({
        status: evaluation.status,
        coordinator_notes: evaluation.coordinator_notes ?? '',
    });

    const notesForm = useForm<NotesFormFields>({
        coordinator_notes: evaluation.coordinator_notes ?? '',
        follow_up_at: evaluation.follow_up_at?.substring(0, 10) ?? '',
    });

    const handleStatusUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        statusForm.patch(updateStatus.url(evaluation.id));
    };

    const handleNotesSave = (e: React.FormEvent) => {
        e.preventDefault();
        notesForm.patch(updateNotes.url(evaluation.id));
    };

    return (
        <div className="space-y-4">
            {/* Status update */}
            <SectionCard title="Update Status">
                <form onSubmit={handleStatusUpdate} className="space-y-4">
                    <div className="grid gap-1.5">
                        <Label className="text-[#F5F0E8] text-xs">New Status</Label>
                        <Select
                            value={statusForm.data.status}
                            onValueChange={(v) => statusForm.setData('status', v)}
                        >
                            <SelectTrigger className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="bg-[#111118] text-[#F5F0E8]">
                                {COORDINATOR_STATUSES.map(({ value, label }) => (
                                    <SelectItem key={value} value={value}>{label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button
                        type="submit"
                        disabled={statusForm.processing}
                        className="w-full bg-[#0E9E8E] text-[#0A0A0F] hover:bg-[#0E9E8E]/90 text-sm"
                    >
                        {statusForm.processing ? 'Saving…' : 'Update Status'}
                    </Button>
                </form>
            </SectionCard>

            {/* Clinical Brief */}
            <SectionCard title="Clinical Brief">
                <p className="mb-3 text-xs text-[#9B9B8E]">
                    Download a HIPAA-safe PDF summary of this evaluation for handoff or file storage.
                </p>
                <a
                    href={brief.url(evaluation.id)}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex w-full items-center justify-center gap-2 rounded-md border border-sidebar-border/50 px-3 py-2 text-sm text-[#F5F0E8] transition-colors hover:border-[#0E9E8E]/50 hover:text-[#0E9E8E]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="size-4 shrink-0" aria-hidden="true">
                        <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                        <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                    </svg>
                    Download Clinical Brief
                </a>
            </SectionCard>

            {/* Notes */}
            <SectionCard title="Coordinator Notes">
                <form onSubmit={handleNotesSave} className="space-y-4">
                    <div className="grid gap-1.5">
                        <textarea
                            value={notesForm.data.coordinator_notes}
                            onChange={(e) => notesForm.setData('coordinator_notes', e.target.value)}
                            rows={4}
                            placeholder="Add notes about this patient…"
                            className="w-full rounded-md border border-sidebar-border/50 bg-[#0A0A0F] px-3 py-2 text-sm text-[#F5F0E8] placeholder:text-[#9B9B8E] focus:border-[#0E9E8E]/50 focus:outline-none resize-none"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label className="text-[#F5F0E8] text-xs">Follow-up Date</Label>
                        <Input
                            type="date"
                            value={notesForm.data.follow_up_at}
                            onChange={(e) => notesForm.setData('follow_up_at', e.target.value)}
                            className="bg-[#0A0A0F] text-[#F5F0E8] border-sidebar-border/50"
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={notesForm.processing}
                        variant="outline"
                        className="w-full text-sm border-sidebar-border/50 text-[#F5F0E8] hover:border-[#0E9E8E]/50"
                    >
                        {notesForm.processing ? 'Saving…' : 'Save Notes'}
                    </Button>
                </form>
            </SectionCard>
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function EvaluationShow({ evaluation, auditEntries }: Props) {
    return (
        <>
            <Head title={`Evaluation — ${formatProcedure(evaluation.procedure_slug)}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">

                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-xl font-semibold text-[#F5F0E8]">
                                {formatProcedure(evaluation.procedure_slug)}
                            </h1>
                            <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize ${STATUS_COLORS[evaluation.status] ?? ''}`}>
                                {evaluation.status.replace('_', ' ')}
                            </span>
                        </div>
                        <p className="mt-0.5 text-sm text-[#9B9B8E]">
                            Submitted {formatDate(evaluation.completed_at ?? evaluation.created_at)}
                        </p>
                    </div>

                    {/* Scores + actions */}
                    <div className="flex items-center gap-4">
                        <div className="text-right">
                            <p className="text-xs text-[#9B9B8E]">Lead Score</p>
                            <p className="text-lg font-semibold text-[#0E9E8E]">
                                {evaluation.lead_score ?? '—'}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-[#9B9B8E]">Priority</p>
                            <p className="text-sm font-medium capitalize text-[#F5F0E8]">
                                {evaluation.priority}
                            </p>
                        </div>
                        {/* Clinical Brief PDF — plain <a> so the browser handles the download natively */}
                        <a
                            href={brief.url(evaluation.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-md border border-sidebar-border/50 bg-transparent px-3 py-1.5 text-xs font-medium text-[#F5F0E8] transition-colors hover:border-[#0E9E8E]/50 hover:text-[#0E9E8E]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" className="size-3.5 shrink-0" aria-hidden="true">
                                <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                                <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                            </svg>
                            Clinical Brief
                        </a>
                    </div>
                </div>

                {/* Body — two-column layout */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    {/* Left — main content (2/3) */}
                    <div className="space-y-6 lg:col-span-2">
                        <PatientCard patient={evaluation.patient ?? null} />
                        <PhotosGallery photos={evaluation.photos ?? []} />
                        <QuizAnswersCard answers={evaluation.quiz_answers} />
                        <AuditTimeline entries={auditEntries ?? []} />
                    </div>

                    {/* Right — coordinator actions (1/3) */}
                    <div className="lg:col-span-1 space-y-6">
                        <SimulationViewer evaluation={evaluation} />
                        <CoordinatorPanel evaluation={evaluation} />
                    </div>
                </div>
            </div>
        </>
    );
}

EvaluationShow.layout = {
    breadcrumbs: [
        { title: 'Evaluations', href: index.url() },
        { title: 'Detail',      href: '#' },
    ],
};