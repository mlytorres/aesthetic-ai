import { Head, router } from '@inertiajs/react';
import { useReducer, useCallback  } from 'react';
import type {FC} from 'react';
import type {WizardState, WizardAction, WizardStep, Procedure, PhotoType, ClinicConfig, CreateEvaluationResponse, UploadPhotoResponse, ValidationErrorResponse} from '@/types/intake';
import { useTranslation } from '@/i18n/useTranslation';

import WizardShell    from './components/WizardShell';
import ConsentSubmit   from './steps/ConsentSubmit';
import ContactInfo     from './steps/ContactInfo';
import PhotoCapture    from './steps/PhotoCapture';
import ProcedureSelect from './steps/ProcedureSelect';
import QuizStep        from './steps/QuizStep';

// ─── Props from IntakeController@show ────────────────────────────────────────

interface Props {
    clinic: ClinicConfig;
    procedures: Procedure[];
    hideHeader?: boolean;
    turnstileSiteKey: string;
}

// ─── Step order ──────────────────────────────────────────────────────────────

const STEP_ORDER: WizardStep[] = ['procedure', 'quiz', 'photos', 'contact', 'consent'];

function nextStep(current: WizardStep): WizardStep {
    const idx = STEP_ORDER.indexOf(current);

    return STEP_ORDER[Math.min(idx + 1, STEP_ORDER.length - 1)] as WizardStep;
}

function prevStep(current: WizardStep): WizardStep {
    const idx = STEP_ORDER.indexOf(current);

    return STEP_ORDER[Math.max(idx - 1, 0)] as WizardStep;
}

// ─── Reducer ─────────────────────────────────────────────────────────────────

const initialState: WizardState = {
    step:              'procedure',
    selectedProcedure: null,
    evaluationToken:   null,
    quizAnswers:       {},
    quizSubmitted:     false,
    photos:            [],
    photosComplete:    false,
    contact: {
        name:  '',
        email: '',
        phone: '',
    },
    consent: {
        hipaa_acknowledged: false,
        terms_accepted:     false,
        photo_use_consent:  false,
        opt_in_sms:         false,
        consented_at:       '',
    },
    turnstileToken: null,
    loading: false,
    error:   null,
};

function reducer(state: WizardState, action: WizardAction): WizardState {
    switch (action.type) {
        case 'SELECT_PROCEDURE':
            return { ...state, selectedProcedure: action.procedure, error: null };

        case 'EVALUATION_CREATED':
            return { ...state, evaluationToken: action.token };

        case 'SET_QUIZ_ANSWER':
            return {
                ...state,
                quizAnswers: { ...state.quizAnswers, [action.key]: action.value },
                error: null,
            };

        case 'QUIZ_SUBMITTED':
            return { ...state, quizSubmitted: true };

        case 'PHOTO_UPLOAD_START': {
            const existing = state.photos.findIndex((p) => p.type === action.photoType);
            const placeholder = {
                id:            0,
                type:          action.photoType,
                quality_score: 0,
                local_url:     action.localUrl,
                uploading:     true,
            };
            const photos =
                existing >= 0
                    ? state.photos.map((p, i) => (i === existing ? placeholder : p))
                    : [...state.photos, placeholder];

            return { ...state, photos, error: null };
        }

        case 'PHOTO_UPLOAD_SUCCESS': {
            const photos = state.photos.map((p) =>
                p.type === action.photoType
                    ? { ...action.photo, local_url: p.local_url, uploading: false }
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
            return { ...state, photos: state.photos.filter((p) => p.type !== action.photoType) };

        case 'PHOTOS_COMPLETE':
            return { ...state, photosComplete: true };

        case 'SET_CONTACT':
            return { ...state, contact: { ...state.contact, [action.field]: action.value }, error: null };

        case 'SET_CONSENT':
            return { ...state, consent: { ...state.consent, [action.field]: action.value }, error: null };

        case 'SET_TURNSTILE_TOKEN':
            return { ...state, turnstileToken: action.token, error: null };

        case 'SET_LOADING':
            return { ...state, loading: action.loading };

        case 'SET_ERROR':
            return { ...state, loading: false, error: action.error };

        case 'NEXT_STEP':
            return { ...state, step: nextStep(state.step), error: null };

        case 'PREV_STEP':
            return { ...state, step: prevStep(state.step), error: null };

        default:
            return state;
    }
}

// ─── CSRF helper ─────────────────────────────────────────────────────────────

function getCsrfToken(): string {
    return (
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
    );
}

async function apiPost<T>(url: string, body: unknown): Promise<T> {
    const res = await fetch(url, {
        method:  'POST',
        headers: {
            'Content-Type':     'application/json',
            Accept:             'application/json',
            'X-CSRF-TOKEN':     getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    if (!res.ok) {
        const err = (await res.json().catch(() => ({}))) as Partial<ValidationErrorResponse>;
        const message =
            err.errors
                ? Object.values(err.errors).flat().join(' ')
                : err.message ?? 'Something went wrong.';

        throw new Error(message);
    }

    return res.json() as Promise<T>;
}

// ─── Page component ───────────────────────────────────────────────────────────

const WizardPage: FC<Props> = ({ clinic, procedures, hideHeader = false, turnstileSiteKey }) => {
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
            const data = await apiPost<CreateEvaluationResponse>('/intake/evaluations', {
                procedure_slug: state.selectedProcedure.slug,
            });

            dispatch({ type: 'EVALUATION_CREATED', token: data.token });
            dispatch({ type: 'SET_LOADING', loading: false });

            // Skip quiz step if this procedure has no active quiz
            if (!state.selectedProcedure.quiz) {
                dispatch({ type: 'NEXT_STEP' }); // → photos (skip quiz)
                dispatch({ type: 'NEXT_STEP' });
            } else {
                dispatch({ type: 'NEXT_STEP' }); // → quiz
            }
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [state.selectedProcedure]);

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
            dispatch({ type: 'NEXT_STEP' }); // → photos
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [state.evaluationToken, state.quizAnswers]);

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
                        method:  'POST',
                        headers: {
                            Accept:             'application/json',
                            'X-CSRF-TOKEN':     getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    },
                );

                if (!res.ok) {
                    const err = (await res.json().catch(() => ({}))) as Partial<ValidationErrorResponse>;
                    const msg = err.errors
                        ? Object.values(err.errors).flat().join(' ')
                        : err.message ?? 'Upload failed.';
                    dispatch({ type: 'PHOTO_UPLOAD_ERROR', photoType: type, error: msg });

                    return;
                }

                const data = (await res.json()) as UploadPhotoResponse;
                dispatch({
                    type: 'PHOTO_UPLOAD_SUCCESS',
                    photoType: type,
                    photo: {
                        id:            data.id,
                        type:          data.type,
                        quality_score: data.quality_score,
                        signed_url:    data.signed_url,
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
        dispatch({ type: 'NEXT_STEP' }); // → contact
    }, []);

    // ── Step 4 → 5: contact done ──────────────────────────────────────────────
    const handleContactNext = useCallback((): void => {
        dispatch({ type: 'NEXT_STEP' }); // → consent
    }, []);

    // ── Final submit ──────────────────────────────────────────────────────────
    const handleSubmit = useCallback(async (): Promise<void> => {
        if (!state.evaluationToken) {
return;
}

        // Stamp consent timestamp at submission time (not stored in reducer — used inline)
        const consentedAt = new Date().toISOString();
        dispatch({ type: 'SET_LOADING', loading: true });

        try {
            await apiPost(`/intake/evaluations/${state.evaluationToken}/submit`, {
                patient: {
                    name:  state.contact.name,
                    email: state.contact.email,
                    phone: state.contact.phone || undefined,
                },
                consent: {
                    hipaa_acknowledged: state.consent.hipaa_acknowledged,
                    terms_accepted:     state.consent.terms_accepted,
                    photo_use_consent:  state.consent.photo_use_consent,
                    opt_in_sms:         state.consent.opt_in_sms,
                    consented_at:       consentedAt,
                },
                turnstile_token: state.turnstileToken,
            });

            // Inertia visit to success page (full page transition)
            router.visit('/intake/success');
        } catch (e) {
            dispatch({ type: 'SET_ERROR', error: (e as Error).message });
        }
    }, [state]);

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
                        onBack={() => dispatch({ type: 'PREV_STEP' })}
                    />
                )}

                {state.step === 'photos' && photoProtocol && (
                    <PhotoCapture
                        requiredTypes={photoProtocol.required}
                        optionalTypes={photoProtocol.optional ?? []}
                        instructions={photoProtocol.instructions}
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        onUpload={handlePhotoUpload}
                        onNext={handlePhotosNext}
                        onBack={() => dispatch({ type: 'PREV_STEP' })}
                    />
                )}

                {state.step === 'contact' && (
                    <ContactInfo
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        onNext={handleContactNext}
                        onBack={() => dispatch({ type: 'PREV_STEP' })}
                    />
                )}

                {state.step === 'consent' && (
                    <ConsentSubmit
                        state={state}
                        dispatch={dispatch}
                        t={t}
                        turnstileSiteKey={turnstileSiteKey}
                        onSubmit={() => {
 void handleSubmit();
}}
                        onBack={() => dispatch({ type: 'PREV_STEP' })}
                    />
                )}
            </WizardShell>
        </>
    );
};

export default WizardPage;
