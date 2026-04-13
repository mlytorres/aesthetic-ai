// ─── Procedure & Quiz ────────────────────────────────────────────────────────

export interface QuizOption {
    value: string;
    label: string;
    /** If present, skip to this question index (0-based) */
    skipTo?: number;
    /** If present, end quiz early */
    skipToEnd?: boolean;
}

export interface QuizQuestion {
    key: string;
    label: string;
    type: 'single' | 'multi' | 'text' | 'boolean';
    options?: QuizOption[];
    required?: boolean;
    placeholder?: string;
    // Branching: pre-resolved array indices from the backend
    skipToOnTrue?: number;   // boolean type: where to jump when answer is true
    skipToOnFalse?: number;  // boolean type: where to jump when answer is false
    skipToAlways?: number;   // text / '*' branch: always jump here after answering
}

export interface QuizDefinition {
    id: number;
    version: string;
    questions: QuizQuestion[];
}

export interface Procedure {
    slug: string;
    label: string;
    category: string;
    photo_protocol: PhotoProtocol;
    quiz: QuizDefinition | null;
}

export interface PhotoProtocol {
    required: PhotoType[];
    optional?: PhotoType[];
    instructions?: string;
}

// ─── Photo types ─────────────────────────────────────────────────────────────

export type PhotoType = 'front' | 'left_profile' | 'right_profile' | 'additional';

export interface UploadedPhoto {
    id: number;
    type: PhotoType;
    quality_score: number;
    /** Presigned URL for preview (short-lived) */
    signed_url?: string;
    /** Local object URL for immediate preview before server response */
    local_url?: string;
    uploading?: boolean;
    error?: string;
}

// ─── Clinic / Tenant ─────────────────────────────────────────────────────────

export interface ClinicConfig {
    name: string;
    logo?: string;
    theme?: string;
}

// ─── Wizard state machine ────────────────────────────────────────────────────

export type WizardStep =
    | 'procedure'
    | 'quiz'
    | 'photos'
    | 'contact'
    | 'consent';

export interface WizardState {
    step: WizardStep;
    /** Active procedure the patient selected */
    selectedProcedure: Procedure | null;
    /** Secure token returned after evaluation created */
    evaluationToken: string | null;
    /** Quiz answers keyed by question key */
    quizAnswers: Record<string, string | string[] | boolean | null>;
    /** Quiz submission status */
    quizSubmitted: boolean;
    /** Photos uploaded so far */
    photos: UploadedPhoto[];
    /** Whether photos step has been acknowledged */
    photosComplete: boolean;
    /** Contact / consent form data (collected together at the end) */
    contact: ContactFormData;
    consent: ConsentFormData;
    /** Security token from Cloudflare Turnstile */
    turnstileToken: string | null;
    /** Global loading state */
    loading: boolean;
    /** Global error message */
    error: string | null;
}

export interface ContactFormData {
    name: string;
    email: string;
    phone: string;
}

export interface ConsentFormData {
    hipaa_acknowledged: boolean;
    terms_accepted: boolean;
    photo_use_consent: boolean;
    consented_at: string;
}

// ─── Wizard actions ───────────────────────────────────────────────────────────

export type WizardAction =
    | { type: 'SELECT_PROCEDURE'; procedure: Procedure }
    | { type: 'EVALUATION_CREATED'; token: string }
    | { type: 'SET_QUIZ_ANSWER'; key: string; value: string | string[] | boolean | null }
    | { type: 'QUIZ_SUBMITTED' }
    | { type: 'PHOTO_UPLOAD_START'; photoType: PhotoType; localUrl: string }
    | { type: 'PHOTO_UPLOAD_SUCCESS'; photoType: PhotoType; photo: UploadedPhoto }
    | { type: 'PHOTO_UPLOAD_ERROR'; photoType: PhotoType; error: string }
    | { type: 'PHOTO_REMOVE'; photoType: PhotoType }
    | { type: 'PHOTOS_COMPLETE' }
    | { type: 'SET_CONTACT'; field: keyof ContactFormData; value: string }
    | { type: 'SET_CONSENT'; field: keyof ConsentFormData; value: boolean }
    | { type: 'SET_TURNSTILE_TOKEN'; token: string | null }
    | { type: 'SET_LOADING'; loading: boolean }
    | { type: 'SET_ERROR'; error: string | null }
    | { type: 'NEXT_STEP' }
    | { type: 'PREV_STEP' };

// ─── API response shapes ──────────────────────────────────────────────────────

export interface CreateEvaluationResponse {
    token: string;
    status: string;
}

export interface UploadPhotoResponse {
    id: number;
    type: PhotoType;
    quality_score: number;
    signed_url: string;
    analysis_status: string;
}

export interface ValidationErrorResponse {
    message: string;
    errors: Record<string, string[]>;
}
