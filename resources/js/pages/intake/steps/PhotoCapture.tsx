import { useRef, useState } from 'react';
import type {FC, ChangeEvent} from 'react';
import type {PhotoType, UploadedPhoto, WizardState, WizardAction} from '@/types/intake';
import { WebcamCapture } from './WebcamCapture';

interface Props {
    requiredTypes: PhotoType[];
    optionalTypes: PhotoType[];
    instructions?: string;
    state: WizardState;
    dispatch: React.Dispatch<WizardAction>;
    onUpload: (file: File, type: PhotoType) => Promise<void>;
    onNext: () => void;
    onBack: () => void;
}

const TYPE_LABELS: Record<PhotoType, string> = {
    front:         'Front View',
    left_profile:  'Left Profile',
    right_profile: 'Right Profile',
    additional:    'Additional',
};

const TYPE_ICONS: Record<PhotoType, string> = {
    front:         '😐',
    left_profile:  '👤',
    right_profile: '👤',
    additional:    '📷',
};

const TYPE_TIPS: Record<PhotoType, string> = {
    front:         'Face the camera directly. Neutral expression, hair pulled back.',
    left_profile:  'Turn 90° to your left. Keep chin level.',
    right_profile: 'Turn 90° to your right. Keep chin level.',
    additional:    'Any additional angle relevant to your procedure.',
};

const PhotoCapture: FC<Props> = ({
    requiredTypes,
    optionalTypes,
    instructions,
    state,
    dispatch,
    onUpload,
    onNext,
    onBack,
}) => {
    const allRequired = requiredTypes.every((t) =>
        state.photos.some((p) => p.type === t && !p.uploading && !p.error),
    );

    return (
        <div className="py-6">
            <h2 className="text-xl font-bold text-[var(--intake-fg)]">Upload your photos</h2>
            <p className="mt-2 text-sm text-[var(--intake-muted)]">
                {instructions ??
                    'Clear, well-lit photos help our AI provide an accurate evaluation. No makeup or filters.'}
            </p>

            {/* Quality tips */}
            <div className="mt-4 rounded-xl border border-[#0E9E8E]/20 bg-[#0E9E8E]/5 px-4 py-3">
                <p className="text-xs font-semibold text-[#0E9E8E] mb-1">📸 Photo tips</p>
                <ul className="space-y-0.5">
                    {[
                        'Natural lighting — near a window works great',
                        'Plain background, no heavy shadows',
                        'Remove glasses and pull hair back',
                        'Neutral expression, mouth closed',
                    ].map((tip) => (
                        <li key={tip} className="text-xs text-[var(--intake-muted)]">
                            · {tip}
                        </li>
                    ))}
                </ul>
            </div>

            {/* Required photos */}
            <div className="mt-6 space-y-3">
                <p className="text-[11px] font-semibold uppercase tracking-widest text-[#0E9E8E]">
                    Required
                </p>
                {requiredTypes.map((type) => (
                    <PhotoSlot
                        key={type}
                        type={type}
                        required
                        uploaded={state.photos.find((p) => p.type === type)}
                        onUpload={onUpload}
                        dispatch={dispatch}
                    />
                ))}
            </div>

            {/* Optional photos */}
            {optionalTypes.length > 0 && (
                <div className="mt-6 space-y-3">
                    <p className="text-[11px] font-semibold uppercase tracking-widest text-white/30">
                        Optional
                    </p>
                    {optionalTypes.map((type) => (
                        <PhotoSlot
                            key={type}
                            type={type}
                            required={false}
                            uploaded={state.photos.find((p) => p.type === type)}
                            onUpload={onUpload}
                            dispatch={dispatch}
                        />
                    ))}
                </div>
            )}

            {state.error && (
                <p className="mt-4 rounded-lg bg-red-500/10 px-4 py-3 text-sm text-red-400 border border-red-500/20">
                    {state.error}
                </p>
            )}

            <div className="mt-8 flex gap-3">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex-1 rounded-xl border border-[var(--intake-border)] bg-transparent px-6 py-3.5 text-sm font-medium text-[var(--intake-muted)] hover:border-[var(--intake-border-hover)] hover:text-[var(--intake-fg)] transition-colors"
                >
                    ← Back
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
                    {allRequired ? 'Continue →' : `${requiredTypes.filter((t) => !state.photos.some((p) => p.type === t && !p.uploading && !p.error)).length} photo(s) remaining`}
                </button>
            </div>
        </div>
    );
};

// ─── PhotoSlot ────────────────────────────────────────────────────────────────

interface PhotoSlotProps {
    type: PhotoType;
    required: boolean;
    uploaded: UploadedPhoto | undefined;
    onUpload: (file: File, type: PhotoType) => Promise<void>;
    dispatch: React.Dispatch<WizardAction>;
}

const PhotoSlot: FC<PhotoSlotProps> = ({ type, required, uploaded, onUpload, dispatch }) => {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isStreaming, setIsStreaming] = useState(false);

    const handleChange = async (e: ChangeEvent<HTMLInputElement>): Promise<void> => {
        const file = e.target.files?.[0];

        if (!file) {
return;
}

        await onUpload(file, type);
        // reset input so same file can be re-selected
        e.target.value = '';
    };

    const handleRemove = (): void => {
        dispatch({ type: 'PHOTO_REMOVE', photoType: type });
    };

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
                        alt={TYPE_LABELS[type]}
                        className="h-full w-full object-cover"
                    />
                ) : uploaded?.uploading ? (
                    <div className="flex h-full w-full items-center justify-center">
                        <svg className="h-5 w-5 animate-spin text-[#0E9E8E]" viewBox="0 0 24 24" fill="none">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                    </div>
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-2xl">
                        {TYPE_ICONS[type]}
                    </div>
                )}

                {/* Quality badge */}
                {uploaded && !uploaded.uploading && uploaded.quality_score > 0 && (
                    <div
                        className={[
                            'absolute bottom-0.5 right-0.5 rounded px-1 py-0.5 text-[9px] font-bold',
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
            <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-[var(--intake-fg)]">
                    {TYPE_LABELS[type]}
                    {required && !uploaded && (
                        <span className="ml-1 text-[#0E9E8E]">*</span>
                    )}
                </p>
                {uploaded?.error ? (
                    <p className="text-xs text-red-400 mt-0.5">{uploaded.error}</p>
                ) : uploaded && !uploaded.uploading ? (
                    <p className="text-xs text-[#0E9E8E] mt-0.5">Uploaded ✓</p>
                ) : (
                    <p className="text-xs text-[var(--intake-muted)] mt-0.5 line-clamp-2">
                        {TYPE_TIPS[type]}
                    </p>
                )}
            </div>

            {/* Action */}
            <div className="shrink-0 flex items-center gap-2">
                {uploaded && !uploaded.uploading ? (
                    <button
                        type="button"
                        onClick={handleRemove}
                        className="rounded-lg border border-[var(--intake-border)] px-3 py-1.5 text-xs font-medium text-[var(--intake-muted)] hover:border-red-500/40 hover:text-red-400 transition-colors"
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
                            className="rounded-lg border border-[var(--intake-border-hover)] px-3 py-1.5 text-xs font-medium text-[var(--intake-fg)] hover:border-[var(--intake-border-hover)] transition-colors disabled:opacity-40"
                        >
                            {uploaded?.uploading ? 'Uploading…' : 'Upload'}
                        </button>
                        {!uploaded?.uploading && (
                            <button
                                type="button"
                                onClick={() => setIsStreaming(true)}
                                className="rounded-lg border border-[#0E9E8E]/50 px-3 py-1.5 text-xs font-medium text-[#0E9E8E] hover:bg-[#0E9E8E]/10 transition-colors hidden sm:block"
                            >
                                Camera
                            </button>
                        )}
                    </>
                )}
            </div>

            {isStreaming && (
                <WebcamCapture
                    type={type}
                    onCapture={async (file) => {
                        setIsStreaming(false);
                        await onUpload(file, type);
                    }}
                    onCancel={() => setIsStreaming(false)}
                />
            )}
        </div>
    );
};

export default PhotoCapture;
