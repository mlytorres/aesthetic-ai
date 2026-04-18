import { Head, router } from '@inertiajs/react';
import { useReducer, useCallback } from 'react';
import type { FC } from 'react';
import { useTranslation } from '@/i18n/useTranslation';
import type {
    WizardState,
    WizardAction,
    WizardStep,
    Procedure,
    PhotoType,
    ClinicConfig,
    CreateEvaluationResponse,
    UploadPhotoResponse,
    ValidationErrorResponse,
} from '@/types/intake';

import WizardShell from './components/WizardShell';
import ConsentSubmit from './steps/ConsentSubmit';
import ContactInfo from './steps/ContactInfo';
import PhotoCapture from './steps/PhotoCapture';
import ProcedureSelect from './steps/ProcedureSelect';
import QuizStep from './steps/QuizStep';

// ─── Props from IntakeController@show ────────────────────────────────────────

interface Props {
    clinic: ClinicConfig;
    procedures: Procedure[];
    hideHeader?: boolean;
    turnstileSiteKey: string;
}

// ─── Step order ──────────────────────────────────────────────────────────────

function getStepOrder(
    position: 'beginning' | 'end' | undefined = 'end',
): WizardStep[] {
    if (position === 'beginning') {
        return ['procedure', 'contact', 'quiz', 'photos', 'consent'];
    }

    return ['procedure', 'quiz', 'photos', 'contact', 'consent'];
}

function getNextStep(current: WizardStep, order: WizardStep[]): WizardStep {
    const idx = order.indexOf(current);

    return order[Math.min(idx + 1, order.length - 1)] as WizardStep;
}

function getPrevStep(current: WizardStep, order: WizardStep[]): WizardStep {
    const idx = order.indexOf(current);

    return order[Math.max(idx - 1, 0)] as WizardStep;
}

// ─── Reducer ─────────────────────────────────────────────────────────────────

const initialState: WizardState = {
    step: 'procedure',
    selectedProcedure: null,
    evaluationToken: null,
    quizAnswers: {},
    quizSubmitted: false,
    photos: [],
    photosComplete: false,
    contact: {
        name: '',
        email: '',
        phone: '',
    },
    consent: {
        hipaa_acknowledged: false,
        terms_accepted: false,
        photo_use_consent: false,
        opt_in_sms: false,
        consented_at: '',
    },
    turnstileToken: null,
    loading: false,
    error: null,
};

function reducer(state: WizardState, action: WizardAction): WizardState {
    switch (action.type) {
        case 'SELECT_PROCEDURE':
            return {
                ...state,
                selectedProcedure: action.procedure,
                error: null,
            };

        case 'EVALUATION_CREATED':
            return { ...state, evaluationToken: action.token };

        case 'SET_QUIZ_ANSWER':
            return {
                ...state,
                quizAnswers: {
                    ...state.quizAnswers,
                    [action.key]: action.value,
                },
                error: null,
            };

        case 'QUIZ_SUBMITTED':
            return { ...state, quizSubmitted: true };

        case 'PHOTO_UPLOAD_START': {
            const existing = state.photos.findIndex(
                (p) => p.type === action.photoType,
            );
            const placeholder = {
                id: 0,
                type: action.photoType,
                quality_score: 0,
                local_url: action.localUrl,
                uploading: true,
            };
            const photos =
                existing >= 0
                    ? state.photos.map((p, i) =>
                          i === existing ? placeholder : p,
                      )
                    : [...state.photos, placeholder];

            return { ...state, photos, error: null };
        }

        case 'PHOTO_UPLOAD_SUCCESS': {
            const photos = state.photos.map((p) =>
                p.type === action.photoType
                    ? {
                          ...action.photo,
                          local_url: p.local_url,
                          uploading: false,
                      }
                    : p,
            );

            return { ...state, photos };
        }

        case 'PHOTO_UPLOAD_ERROR': {
            const photos = state.photos.map((p) =>
                p.type === action.photoType
                    ? { ...p, uploading: false, error: action.error }
                    : p,
            );

            return { ...state, photos };
        }

        case 'PHOTO_REMOVE':
            return {
                ...state,
                photos: state.photos.filter((p) => p.type !== action.photoType),
            };

        case 'PHOTOS_COMPLETE':
            return { ...state, photosComplete: true };

        case 'SET_CONTACT':
            return {
                ...state,
                contact: { ...state.contact, [action.field]: action.value },
                error: null,
            };

        case 'SET_CONSENT':
            return {
                ...state,
                consent: { ...state.consent, [action.field]: action.value },
                error: null,
            };

        case 'SET_TURNSTILE_TOKEN':
            return { ...state, turnstileToken: action.token, error: null };

        case 'SET_LOADING':
            return { ...state, loading: action.loading };

        case 'SET_ERROR':
            return { ...state, loading: false, error: action.error };

        case 'NEXT_STEP': {
            const order = action.order || []; // Fallback but should always be passed

            return {
                ...state,
                step: getNextStep(state.step, order),
                error: null,
            };
        }

        case 'PREV_STEP': {
            const order = action.order || [];

            return {
                ...state,
                step: getPrevStep(state.step, order),
                error: null,
            };
        }

        default:
            return state;
    }
}

// ─── CSRF helper ─────────────────────────────────────────────────────────────

function getCsrfToken(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
            ?.content ?? ''
    );
}

async function apiPost<T>(url: string, body: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    if (!res.ok) {
        const err = (await res
            .json()
            .catch(() => ({}))) as Partial<ValidationErrorResponse>;
        const message = err.errors
            ? Object.values(err.errors).flat().join(' ')
            : (err.message ?? 'Something went wrong.');

        throw new Error(message);
    }

    return res.json() as Promise<T>;
}

// ─── Page component ───────────────────────────────────────────────────────────

const WizardPage: FC<Props> = ({
    clinic,
    procedures,
    hideHeader = false,
    turnstileSiteKey,
}) => {
    const [state, dispatch] = useReducer(reducer, initialState);
    const { t } = useTranslation(clinic.locale ?? 'en');

    // ── Step 1: select procedure → create evaluation ──────────────────────────
    const handleProcedureNext = useCallback(async (): Promise<void> => {
        if (!state.selectedProcedure) {
            return;
        }

        dispatch({ type: 'SET_LOADING', loading: true });
        dispatch({ type: 'SET_ERROR', error: null });

        try {
            const affiliateToken = new URLSearchParams(
                window.location.search,
            ).get('aff');

            const data = await apiPost<CreateEvaluationResponse>(
                '/intake/evaluations',
                {
                    procedure_slug: state.selectedProcedure.slug,
                    aff: affiliateToken,
                },
            );

            dispatch({ type: 'EVALUATION_CREATED', token: data.token });
            dispatch({ type: 'SET_LOADING', loading: false });

            // Skip quiz step if this procedure has no active quiz
            if (!state.selectedProcedure.quiz) {
                // Skip 'quiz' if not present
                const order = getStepOrder(clinic.lead_capture_position);
                const next = getNextStep('procedure', order);

                dispatch({ type: 'NEXT_STEP', order });

                if (next === 'quiz') {
                    dispatch({ type: 'NEXT_STEP', order });
                }
            } else {
                dispatch({
                    type: 'NEXT_STEP',
                    order: getStepOrder(clinic.lead_capture_position),
                });
            }
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [state.selectedProcedure, clinic.lead_capture_position]);

    // ── Step 2: submit quiz answers ───────────────────────────────────────────
    const handleQuizNext = useCallback(async (): Promise<void> => {
        if (!state.evaluationToken) {
            return;
        }

        dispatch({ type: 'SET_LOADING', loading: true });

        try {
            await apiPost(`/intake/evaluations/${state.evaluationToken}/quiz`, {
                answers: state.quizAnswers,
            });

            dispatch({ type: 'QUIZ_SUBMITTED' });
            dispatch({ type: 'SET_LOADING', loading: false });
            dispatch({
                type: 'NEXT_STEP',
                order: getStepOrder(clinic.lead_capture_position),
            });
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [
        state.evaluationToken,
        state.quizAnswers,
        clinic.lead_capture_position,
    ]);

    // ── Photo upload ──────────────────────────────────────────────────────────
    const handlePhotoUpload = useCallback(
        async (file: File, type: PhotoType): Promise<void> => {
            if (!state.evaluationToken) {
                return;
            }

            const localUrl = URL.createObjectURL(file);
            dispatch({ type: 'PHOTO_UPLOAD_START', photoType: type, localUrl });

            try {
                const formData = new FormData();
                formData.append('photo', file);
                formData.append('type', type);
                formData.append('quality_score', '80'); // client-side placeholder; server validates

                const res = await fetch(
                    `/intake/evaluations/${state.evaluationToken}/photos`,
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    },
                );

                if (!res.ok) {
                    const err = (await res
                        .json()
                        .catch(() => ({}))) as Partial<ValidationErrorResponse>;
                    const msg = err.errors
                        ? Object.values(err.errors).flat().join(' ')
                        : (err.message ?? 'Upload failed.');
                    dispatch({
                        type: 'PHOTO_UPLOAD_ERROR',
                        photoType: type,
                        error: msg,
                    });

                    return;
                }

                const data = (await res.json()) as UploadPhotoResponse;
                dispatch({
                    type: 'PHOTO_UPLOAD_SUCCESS',
                    photoType: type,
                    photo: {
                        id: data.id,
                        type: data.type,
                        quality_score: data.quality_score,
                        signed_url: data.signed_url,
                    },
                });
            } catch (e) {
                dispatch({
                    type: 'PHOTO_UPLOAD_ERROR',
                    photoType: type,
                    error: (e as Error).message,
                });
            }
        },
        [state.evaluationToken],
    );

    // ── Step 3 → 4: photos done ───────────────────────────────────────────────
    const handlePhotosNext = useCallback((): void => {
        dispatch({ type: 'PHOTOS_COMPLETE' });
        dispatch({
            type: 'NEXT_STEP',
            order: getStepOrder(clinic.lead_capture_position),
        });
    }, [clinic.lead_capture_position]);

    // ── Step 4 → 5: contact done ──────────────────────────────────────────────
    const handleContactNext = useCallback(async (): Promise<void> => {
        const order = getStepOrder(clinic.lead_capture_position);

        // If capture is at the beginning, we must submit the lead info to the backend now.
        if (
            clinic.lead_capture_position === 'beginning' &&
            state.evaluationToken
        ) {
            dispatch({ type: 'SET_LOADING', loading: true });
            dispatch({ type: 'SET_ERROR', error: null });

            try {
                await apiPost(
                    `/intake/evaluations/${state.evaluationToken}/lead`,
                    {
                        patient: state.contact,
                        turnstile_token: state.turnstileToken,
                    },
                );

                dispatch({ type: 'SET_LOADING', loading: false });
                dispatch({ type: 'SET_TURNSTILE_TOKEN', token: null });
                dispatch({ type: 'NEXT_STEP', order });

                // 📢 Notify parent window if we are in an iframe
                if (window.self !== window.top) {
                    window.parent.postMessage(
                        {
                            type: 'LEAD_CAPTURED',
                            name: state.contact.name,
                            email: state.contact.email,
                        },
                        '*',
                    );
                }
            } catch (e) {
                dispatch({ type: 'SET_ERROR', error: (e as Error).message });
            }
        } else {
            dispatch({ type: 'NEXT_STEP', order });
        }
    }, [
        state.contact,
        state.turnstileToken,
        state.evaluationToken,
        clinic.lead_capture_position,
    ]);

    // ── Final submit ──────────────────────────────────────────────────────────
    const handleSubmit = useCallback(async (): Promise<void> => {
        if (!state.evaluationToken) {
            return;
        }

        // Stamp consent timestamp at submission time (not stored in reducer — used inline)
        const consentedAt = new Date().toISOString();
        dispatch({ type: 'SET_LOADING', loading: true });

        try {
            await apiPost(
                `/intake/evaluations/${state.evaluationToken}/submit`,
                {
                    // Only send patient info if we HAVEN'T sent it yet (lead_capture_position === 'end')
                    patient:
                        clinic.lead_capture_position === 'end'
                            ? {
                                  name: state.contact.name,
                                  email: state.contact.email,
                                  phone: state.contact.phone || undefined,
                              }
                            : undefined,
                    consent: {
                        hipaa_acknowledged: state.consent.hipaa_acknowledged,
                        terms_accepted: state.consent.terms_accepted,
                        photo_use_consent: state.consent.photo_use_consent,
                        opt_in_sms: state.consent.opt_in_sms,
                        consented_at: consentedAt,
                    },
                    // Turnstile required at end only if not verified at beginning
                    turnstile_token: state.turnstileToken,
                },
            );

            // Inertia visit to success page (full page transition)
            router.visit('/intake/success');
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [state, clinic.lead_capture_position]);

    // ─── Render ───────────────────────────────────────────────────────────────

    const procedure = state.selectedProcedure;
    const photoProtocol = procedure?.photo_protocol;

    return (
        <>
            <Head title={`${clinic.name} — AI Evaluation`} />

            <WizardShell
                clinicName={clinic.name}
                clinicLogo={clinic.logo}
                theme={clinic.theme}
                brandPrimary={clinic.brand_primary}
                brandFont={clinic.brand_font}
                currentStep={state.step}
                hideHeader={hideHeader}
                t={t}
            >
                {state.step === 'procedure' && (
                    <ProcedureSelect
                        procedures={procedures}
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        onNext={() => {
                            void handleProcedureNext();
                        }}
                    />
                )}

                {state.step === 'quiz' && procedure?.quiz && (
                    <QuizStep
                        questions={procedure.quiz.questions}
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        onNext={() => {
                            void handleQuizNext();
                        }}
                        onBack={() =>
                            dispatch({
                                type: 'PREV_STEP',
                                order: getStepOrder(
                                    clinic.lead_capture_position,
                                ),
                            })
                        }
                    />
                )}

                {state.step === 'photos' && photoProtocol && (
                    <PhotoCapture
                        requiredSlots={photoProtocol.required}
                        optionalSlots={photoProtocol.optional ?? []}
                        category={photoProtocol.category}
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        onUpload={handlePhotoUpload}
                        onNext={handlePhotosNext}
                        onBack={() =>
                            dispatch({
                                type: 'PREV_STEP',
                                order: getStepOrder(
                                    clinic.lead_capture_position,
                                ),
                            })
                        }
                    />
                )}

                {state.step === 'contact' && (
                    <ContactInfo
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        turnstileSiteKey={turnstileSiteKey}
                        leadCapturePosition={
                            clinic.lead_capture_position ?? 'end'
                        }
                        onNext={handleContactNext}
                        onBack={() =>
                            dispatch({
                                type: 'PREV_STEP',
                                order: getStepOrder(
                                    clinic.lead_capture_position,
                                ),
                            })
                        }
                    />
                )}

                {state.step === 'consent' && (
                    <ConsentSubmit
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        turnstileSiteKey={turnstileSiteKey}
                        leadCapturePosition={
                            clinic.lead_capture_position ?? 'end'
                        }
                        onSubmit={() => {
                            void handleSubmit();
                        }}
                        onBack={() =>
                            dispatch({
                                type: 'PREV_STEP',
                                order: getStepOrder(
                                    clinic.lead_capture_position,
                                ),
                            })
                        }
                    />
                )}
            </WizardShell>
        </>
    );
};

export default WizardPage;
