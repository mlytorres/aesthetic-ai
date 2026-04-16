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
    skipToOnTrue?: number; // boolean type: where to jump when answer is true
    skipToOnFalse?: number; // boolean type: where to jump when answer is false
    skipToAlways?: number; // text / '*' branch: always jump here after answering
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
    required: PhotoSlot[];
    optional: PhotoSlot[];
    /** Category of the procedure — drives which tip set to show ('face' | 'body') */
    category?: string;
}

// ─── Photo types ─────────────────────────────────────────────────────────────

/**
 * Semantic photo position identifiers.
 * - Face procedures use: front, left_profile, right_profile, eyes_closed
 * - Body procedures use: front, left_profile, right_profile, back, left_side, right_side, abdomen_front, abdomen_side, chest_front
 * - 'additional' is kept for legacy backward-compatibility only
 */
export type PhotoType =
    | 'front' // Face-forward / full-body front
    | 'left_profile' // 90° left — face or body
    | 'right_profile' // 90° right — face or body
    | 'back' // Full-body rear view
    | 'left_side' // Body left-side view (45° or 90°)
    | 'right_side' // Body right-side view (45° or 90°)
    | 'abdomen_front' // Abdominal area close-up (front)
    | 'abdomen_side' // Abdominal area close-up (side)
    | 'chest_front' // Chest close-up (front)
    | 'eyes_closed' // Eyes-closed (eyelid surgery)
    | 'additional'; // Legacy catch-all

/**
 * A single photo slot as returned by ProcedureResource.
 * Contains all metadata needed to render the slot without any static lookup tables.
 */
export interface PhotoSlot {
    type: PhotoType;
    label: string;
    tip: string;
}

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
    /** Hex color override for accent/CTA color (replaces default teal #0E9E8E). */
    brand_primary?: string | null;
    /** Font stack override for the wizard (e.g. "Inter", sans-serif). */
    brand_font?: string | null;
    /** BCP-47 locale code: 'en' | 'es' */
    locale?: string;
    lead_capture_position?: 'beginning' | 'end';
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
    opt_in_sms: boolean;
    consented_at: string;
}

// ─── Wizard actions ───────────────────────────────────────────────────────────

export type WizardAction =
    | { type: 'SELECT_PROCEDURE'; procedure: Procedure }
    | { type: 'EVALUATION_CREATED'; token: string }
    | {
          type: 'SET_QUIZ_ANSWER';
          key: string;
          value: string | string[] | boolean | null;
      }
    | { type: 'QUIZ_SUBMITTED' }
    | { type: 'PHOTO_UPLOAD_START'; photoType: PhotoType; localUrl: string }
    | {
          type: 'PHOTO_UPLOAD_SUCCESS';
          photoType: PhotoType;
          photo: UploadedPhoto;
      }
    | { type: 'PHOTO_UPLOAD_ERROR'; photoType: PhotoType; error: string }
    | { type: 'PHOTO_REMOVE'; photoType: PhotoType }
    | { type: 'PHOTOS_COMPLETE' }
    | { type: 'SET_CONTACT'; field: keyof ContactFormData; value: string }
    | { type: 'SET_CONSENT'; field: keyof ConsentFormData; value: boolean }
    | { type: 'SET_TURNSTILE_TOKEN'; token: string | null }
    | { type: 'SET_LOADING'; loading: boolean }
    | { type: 'SET_ERROR'; error: string | null }
    | { type: 'NEXT_STEP'; order: WizardStep[] }
    | { type: 'PREV_STEP'; order: WizardStep[] };

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
