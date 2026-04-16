import { useRef, useState } from 'react';
import type { FC, ChangeEvent } from 'react';
import type { TranslationKey } from '@/i18n/translations';
import type {
    PhotoSlot,
    PhotoType,
    UploadedPhoto,
    WizardState,
    WizardAction,
} from '@/types/intake';
import { WebcamCapture } from './WebcamCapture';

type TFn = (
    key: TranslationKey,
    vars?: Record<string, string | number>,
) => string;

interface Props {
    requiredSlots: PhotoSlot[];
    optionalSlots: PhotoSlot[];
    /** 'face' | 'body' — drives which photo tips to show */
    category?: string;
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    t: TFn;
    onUpload: (file: File, type: PhotoType) => Promise<void>;
    onNext: () => void;
    onBack: () => void;
}

const FACE_TIPS = [
    'Natural lighting — near a window works great',
    'Plain background, no heavy shadows',
    'Remove glasses and pull hair back',
    'Neutral expression, mouth closed',
];

const BODY_TIPS = [
    'Natural lighting — near a window works great',
    'Wear form-fitting clothing or a swimsuit',
    'Stand straight with arms relaxed at your sides',
    'Full body in frame — head to toe visible',
];

const PhotoCapture: FC<Props> = ({
    requiredSlots,
    optionalSlots,
    category = 'face',
    state,
    dispatch,
    t,
    onUpload,
    onNext,
    onBack,
}) => {
    const allRequired = requiredSlots.every((slot) =>
        state.photos.some(
            (p) => p.type === slot.type && !p.uploading && !p.error,
        ),
    );

    const tips = category === 'body' ? BODY_TIPS : FACE_TIPS;
    const remainingCount = requiredSlots.filter(
        (slot) =>
            !state.photos.some(
                (p) => p.type === slot.type && !p.uploading && !p.error,
            ),
    ).length;

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[var(--intake-fg)]">
                {t('photos.title')}
            </h2>
            <p className="mt-2 text-sm text-[var(--intake-muted)]">
                Clear, well-lit photos help our AI provide an accurate
                evaluation. No filters.
            </p>

            {/* Quality tips — procedure-aware */}
            <div className="mt-4 rounded-xl border border-[#0E9E8E]/20 bg-[#0E9E8E]/5 px-4 py-3">
                <p className="mb-1 text-xs font-semibold text-[#0E9E8E]">
                    📸 Photo tips
                </p>
                <ul className="space-y-0.5">
                    {tips.map((tip) => (
                        <li
                            key={tip}
                            className="text-xs text-[var(--intake-muted)]"
                        >
                            · {tip}
                        </li>
                    ))}
                </ul>
            </div>

            {/* Required photos */}
            <div className="mt-6 space-y-3">
                <p className="text-[11px] font-semibold tracking-widest text-[#0E9E8E] uppercase">
                    Required
                </p>
                {requiredSlots.map((slot) => (
                    <PhotoSlotCard
                        key={slot.type}
                        slot={slot}
                        required
                        uploaded={state.photos.find(
                            (p) => p.type === slot.type,
                        )}
                        onUpload={onUpload}
                        dispatch={dispatch}
                    />
                ))}
            </div>

            {/* Optional photos */}
            {optionalSlots.length > 0 && (
                <div className="mt-6 space-y-3">
                    <p className="text-[11px] font-semibold tracking-widest text-white/30 uppercase">
                        Optional
                    </p>
                    {optionalSlots.map((slot) => (
                        <PhotoSlotCard
                            key={slot.type}
                            slot={slot}
                            required={false}
                            uploaded={state.photos.find(
                                (p) => p.type === slot.type,
                            )}
                            onUpload={onUpload}
                            dispatch={dispatch}
                        />
                    ))}
                </div>
            )}

            {state.error && (
                <p className="mt-4 rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-400">
                    {state.error}
                </p>
            )}

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex-1 rounded-xl border border-[var(--intake-border)] bg-transparent px-6 py-3.5 text-sm font-medium text-[var(--intake-muted)] transition-colors hover:border-[var(--intake-border-hover)] hover:text-[var(--intake-fg)]"
                >
                    {t('nav.back')}
                </button>
                <button
                    type="button"
                    onClick={onNext}
                    disabled={!allRequired}
                    className={[
                        'flex-[2] rounded-xl px-6 py-3.5 text-sm font-semibold transition-all duration-200',
                        allRequired
                            ? 'bg-[#0E9E8E] text-[var(--intake-icon-on-teal)] hover:bg-[#a8883e] active:scale-[0.98]'
                            : 'cursor-not-allowed bg-white/10 text-white/30',
                    ].join(' ')}
                >
                    {allRequired
                        ? t('nav.continue')
                        : t('photos.continue_cta', { count: remainingCount })}
                </button>
            </div>
        </div>
    );
};

// ─── PhotoSlotCard ────────────────────────────────────────────────────────────

interface PhotoSlotCardProps {
    slot: PhotoSlot;
    required: boolean;
    uploaded: UploadedPhoto | undefined;
    onUpload: (file: File, type: PhotoType) => Promise<void>;
    dispatch: React.Dispatch<WizardAction>;
}

const SLOT_ICONS: Partial<Record<PhotoType, string>> = {
    front: '😐',
    left_profile: '👤',
    right_profile: '👤',
    back: '🔄',
    left_side: '👤',
    right_side: '👤',
    abdomen_front: '🩺',
    abdomen_side: '🩺',
    chest_front: '🩺',
    eyes_closed: '👁️',
    additional: '📷',
};

const PhotoSlotCard: FC<PhotoSlotCardProps> = ({
    slot,
    required,
    uploaded,
    onUpload,
    dispatch,
}) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isStreaming, setIsStreaming] = useState(false);

    const handleChange = async (
        e: ChangeEvent<HTMLInputElement>,
    ): Promise<void> => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        await onUpload(file, slot.type);
        e.target.value = '';
    };

    const handleRemove = (): void => {
        dispatch({ type: 'PHOTO_REMOVE', photoType: slot.type });
    };

    const icon = SLOT_ICONS[slot.type] ?? '📷';

    return (
        <div
            className={[
                'flex items-center gap-4 rounded-xl border p-4 transition-colors',
                uploaded?.error
                    ? 'border-red-500/30 bg-red-500/5'
                    : uploaded && !uploaded.uploading
                      ? 'border-[#0E9E8E]/30 bg-[#0E9E8E]/5'
                      : 'border-[var(--intake-border)] bg-[var(--intake-surface)]',
            ].join(' ')}
        >
            {/* Preview / icon */}
            <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-white/5">
                {uploaded?.local_url && !uploaded.uploading ? (
                    <img
                        src={uploaded.local_url}
                        alt={slot.label}
                        className="h-full w-full object-cover"
                    />
                ) : uploaded?.uploading ? (
                    <div className="flex h-full w-full items-center justify-center">
                        <svg
                            className="h-5 w-5 animate-spin text-[#0E9E8E]"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <circle
                                className="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                strokeWidth="4"
                            />
                            <path
                                className="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            />
                        </svg>
                    </div>
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-2xl">
                        {icon}
                    </div>
                )}

                {/* Quality badge */}
                {uploaded &&
                    !uploaded.uploading &&
                    uploaded.quality_score > 0 && (
                        <div
                            className={[
                                'absolute right-0.5 bottom-0.5 rounded px-1 py-0.5 text-[9px] font-bold',
                                uploaded.quality_score >= 70
                                    ? 'bg-emerald-500 text-white'
                                    : uploaded.quality_score >= 40
                                      ? 'bg-amber-500 text-white'
                                      : 'bg-red-500 text-white',
                            ].join(' ')}
                        >
                            {uploaded.quality_score}
                        </div>
                    )}
            </div>

            {/* Info */}
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-[var(--intake-fg)]">
                    {slot.label}
                    {required && !uploaded && (
                        <span className="ml-1 text-[#0E9E8E]">*</span>
                    )}
                </p>
                {uploaded?.error ? (
                    <p className="mt-0.5 text-xs text-red-400">
                        {uploaded.error}
                    </p>
                ) : uploaded && !uploaded.uploading ? (
                    <p className="mt-0.5 text-xs text-[#0E9E8E]">Uploaded ✓</p>
                ) : (
                    <p className="mt-0.5 line-clamp-2 text-xs text-[var(--intake-muted)]">
                        {slot.tip}
                    </p>
                )}
            </div>

            {/* Action */}
            <div className="flex shrink-0 items-center gap-2">
                {uploaded && !uploaded.uploading ? (
                    <button
                        type="button"
                        onClick={handleRemove}
                        className="rounded-lg border border-[var(--intake-border)] px-3 py-1.5 text-xs font-medium text-[var(--intake-muted)] transition-colors hover:border-red-500/40 hover:text-red-400"
                    >
                        Remove
                    </button>
                ) : (
                    <>
                        <input
                            ref={inputRef}
                            type="file"
                            accept="image/jpeg,image/png"
                            capture="environment"
                            className="sr-only"
                            onChange={handleChange}
                            disabled={uploaded?.uploading}
                        />
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            disabled={uploaded?.uploading}
                            className="rounded-lg border border-[var(--intake-border-hover)] px-3 py-1.5 text-xs font-medium text-[var(--intake-fg)] transition-colors hover:border-[var(--intake-border-hover)] disabled:opacity-40"
                        >
                            {uploaded?.uploading ? 'Uploading…' : 'Upload'}
                        </button>
                        {!uploaded?.uploading && (
                            <button
                                type="button"
                                onClick={() => setIsStreaming(true)}
                                className="hidden rounded-lg border border-[#0E9E8E]/50 px-3 py-1.5 text-xs font-medium text-[#0E9E8E] transition-colors hover:bg-[#0E9E8E]/10 sm:block"
                            >
                                Camera
                            </button>
                        )}
                    </>
                )}
            </div>

            {isStreaming && (
                <WebcamCapture
                    type={slot.type}
                    onCapture={async (file) => {
                        setIsStreaming(false);
                        await onUpload(file, slot.type);
                    }}
                    onCancel={() => setIsStreaming(false)}
                />
            )}
        </div>
    );
};

export default PhotoCapture;
