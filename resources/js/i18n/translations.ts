/**
 * Intake wizard translations.
 *
 * Keys are used via the `useTranslation` hook:
 *   const { t } = useTranslation(locale);
 *   t('wizard.next')  // → "Next" | "Siguiente"
 *
 * Interpolation: use {variable} placeholders.
 *   t('wizard.greeting', { name: 'Ana' }) // "Hi Ana!" | "¡Hola Ana!"
 */

export type Locale = 'en' | 'es';

export type TranslationKey =
    // ── Navigation
    | 'nav.back'
    | 'nav.next'
    | 'nav.continue'
    | 'nav.skip'
    | 'nav.processing'
    // ── Procedure select
    | 'procedure.title'
    | 'procedure.subtitle'
    | 'procedure.select_cta'
    | 'procedure.category.face'
    | 'procedure.category.body'
    | 'procedure.category.skin'
    // ── Progress bar steps
    | 'progress.procedure'
    | 'progress.quiz'
    | 'progress.photos'
    | 'progress.contact'
    | 'progress.review'
    // ── Quiz
    | 'quiz.title'
    | 'quiz.question_of'
    | 'quiz.select_all'
    | 'quiz.type_answer'
    | 'quiz.yes'
    | 'quiz.no'
    | 'quiz.submit'
    | 'quiz.saving'
    // ── Photos
    | 'photos.title'
    | 'photos.subtitle'
    | 'photos.required_label'
    | 'photos.optional_label'
    | 'photos.front'
    | 'photos.left_profile'
    | 'photos.right_profile'
    | 'photos.additional'
    | 'photos.upload_cta'
    | 'photos.retake'
    | 'photos.quality_good'
    | 'photos.quality_poor'
    | 'photos.uploading'
    | 'photos.continue_cta'
    | 'photos.webcam_title'
    | 'photos.webcam_instructions'
    | 'photos.webcam_capture'
    | 'photos.webcam_retake'
    | 'photos.webcam_use'
    // ── Contact info
    | 'contact.title'
    | 'contact.subtitle'
    | 'contact.name_label'
    | 'contact.name_placeholder'
    | 'contact.email_label'
    | 'contact.email_placeholder'
    | 'contact.phone_label'
    | 'contact.phone_placeholder'
    | 'contact.phone_hint'
    | 'contact.continue_cta'
    // ── Consent & review
    | 'consent.title'
    | 'consent.subtitle'
    | 'consent.summary_heading'
    | 'consent.summary.procedure'
    | 'consent.summary.name'
    | 'consent.summary.email'
    | 'consent.summary.phone'
    | 'consent.summary.photos'
    | 'consent.summary.quiz'
    | 'consent.required_heading'
    | 'consent.hipaa_label'
    | 'consent.hipaa_text'
    | 'consent.terms_label'
    | 'consent.terms_text'
    | 'consent.photo_label'
    | 'consent.photo_text'
    | 'consent.sms_label'
    | 'consent.sms_text'
    | 'consent.optional_badge'
    | 'consent.submit_cta'
    | 'consent.submitting'
    | 'consent.disclaimer'
    // ── Success
    | 'success.title'
    | 'success.subtitle'
    | 'success.next_steps_heading'
    | 'success.step1_title'
    | 'success.step1_body'
    | 'success.step2_title'
    | 'success.step2_body'
    | 'success.step3_title'
    | 'success.step3_body'
    // ── Footer
    | 'footer.hipaa_notice'
    | 'footer.powered_by'
    // ── Errors
    | 'error.generic'
    | 'error.required_field'
    | 'error.invalid_email'
    | 'error.turnstile';

type Translations = Record<Locale, Record<TranslationKey, string>>;

export const translations: Translations = {
    en: {
        // Navigation
        'nav.back': '← Back',
        'nav.next': 'Next →',
        'nav.continue': 'Continue',
        'nav.skip': 'Skip',
        'nav.processing': 'Processing…',

        // Procedure select
        'procedure.title': 'What brings you in today?',
        'procedure.subtitle':
            "Select the procedure you're considering to begin your personalized AI evaluation.",
        'procedure.select_cta': 'Start My Evaluation',
        'procedure.category.face': 'Face',
        'procedure.category.body': 'Body',
        'procedure.category.skin': 'Skin',

        // Progress bar steps
        'progress.procedure': 'Procedure',
        'progress.quiz': 'Questions',
        'progress.photos': 'Photos',
        'progress.contact': 'Contact',
        'progress.review': 'Review',

        // Quiz
        'quiz.title': 'A few questions',
        'quiz.question_of': 'Question {current} of {total}',
        'quiz.select_all': 'Select all that apply',
        'quiz.type_answer': 'Type your answer…',
        'quiz.yes': 'Yes',
        'quiz.no': 'No',
        'quiz.submit': 'Save & Continue',
        'quiz.saving': 'Saving…',

        // Photos
        'photos.title': 'Upload your photos',
        'photos.subtitle':
            'Clear, well-lit photos help our AI deliver the most accurate evaluation. All photos are encrypted and HIPAA-protected.',
        'photos.required_label': 'Required',
        'photos.optional_label': 'Optional',
        'photos.front': 'Front',
        'photos.left_profile': 'Left Profile',
        'photos.right_profile': 'Right Profile',
        'photos.additional': 'Additional',
        'photos.upload_cta': 'Upload Photo',
        'photos.retake': 'Retake',
        'photos.quality_good': 'Good quality',
        'photos.quality_poor': 'Poor quality — try better lighting',
        'photos.uploading': 'Uploading…',
        'photos.continue_cta': 'Continue with {count} photo(s)',
        'photos.webcam_title': 'Take a photo',
        'photos.webcam_instructions':
            'Position your face in the center of the frame and hold still.',
        'photos.webcam_capture': 'Take Photo',
        'photos.webcam_retake': 'Retake',
        'photos.webcam_use': 'Use This Photo',

        // Contact info
        'contact.title': 'Your contact information',
        'contact.subtitle':
            "We'll use this to send your evaluation results and for your coordinator to reach you.",
        'contact.name_label': 'Full Name',
        'contact.name_placeholder': 'Jane Smith',
        'contact.email_label': 'Email Address',
        'contact.email_placeholder': 'jane@example.com',
        'contact.phone_label': 'Phone Number',
        'contact.phone_placeholder': '+1 (555) 000-0000',
        'contact.phone_hint':
            'Optional. Used only to contact you about your evaluation.',
        'contact.continue_cta': 'Continue',

        // Consent & review
        'consent.title': 'Review & confirm',
        'consent.subtitle':
            'Please review your details and grant the required consents to complete your evaluation submission.',
        'consent.summary_heading': 'Your submission',
        'consent.summary.procedure': 'Procedure',
        'consent.summary.name': 'Name',
        'consent.summary.email': 'Email',
        'consent.summary.phone': 'Phone',
        'consent.summary.photos': 'Photos uploaded',
        'consent.summary.quiz': 'Quiz answers',
        'consent.required_heading': 'Required consents',
        'consent.hipaa_label': 'HIPAA Privacy Notice',
        'consent.hipaa_text':
            'I acknowledge that I have been informed of my rights under the Health Insurance Portability and Accountability Act (HIPAA). My protected health information may be used for treatment and evaluation purposes.',
        'consent.terms_label': 'Terms of Service',
        'consent.terms_text':
            'I agree to the Terms of Service. I understand that this evaluation is informational and does not constitute medical advice. A licensed physician will review my case.',
        'consent.photo_label': 'AI Photo Analysis Consent',
        'consent.photo_text':
            'I consent to my photos being analysed by artificial intelligence to assist in generating my evaluation report. Photos are encrypted and accessible only to clinic staff.',
        'consent.sms_label': 'SMS Notifications',
        'consent.sms_text':
            'I consent to receive text messages regarding my evaluation and consultation updates. Message and data rates may apply.',
        'consent.optional_badge': 'optional',
        'consent.submit_cta': 'Submit My Evaluation ✓',
        'consent.submitting': 'Submitting…',
        'consent.disclaimer':
            'By submitting, you confirm that all information provided is accurate and truthful.',

        // Success
        'success.title': 'Evaluation received!',
        'success.subtitle':
            'Thank you. Your AI-powered evaluation is underway.',
        'success.next_steps_heading': 'What happens next',
        'success.step1_title': 'AI Analysis',
        'success.step1_body':
            'Our AI is processing your photos and quiz answers to generate your personalized evaluation report.',
        'success.step2_title': 'Coordinator Review',
        'success.step2_body':
            'A clinic coordinator will review your evaluation and reach out within 1 business day.',
        'success.step3_title': 'Your Results',
        'success.step3_body':
            "You'll receive your Beauty Roadmap report by email once your evaluation is complete.",

        // Footer
        'footer.hipaa_notice':
            'Your information is encrypted and protected under HIPAA.',
        'footer.powered_by': 'AI Evaluation',

        // Errors
        'error.generic': 'Something went wrong. Please try again.',
        'error.required_field': 'This field is required.',
        'error.invalid_email': 'Please enter a valid email address.',
        'error.turnstile': 'Please complete the security check to continue.',
    },

    es: {
        // Navigation
        'nav.back': '← Atrás',
        'nav.next': 'Siguiente →',
        'nav.continue': 'Continuar',
        'nav.skip': 'Omitir',
        'nav.processing': 'Procesando…',

        // Procedure select
        'procedure.title': '¿Qué te trae hoy?',
        'procedure.subtitle':
            'Selecciona el procedimiento que estás considerando para comenzar tu evaluación de IA personalizada.',
        'procedure.select_cta': 'Iniciar Mi Evaluación',
        'procedure.category.face': 'Cara',
        'procedure.category.body': 'Cuerpo',
        'procedure.category.skin': 'Piel',

        // Progress bar steps
        'progress.procedure': 'Procedimiento',
        'progress.quiz': 'Preguntas',
        'progress.photos': 'Fotos',
        'progress.contact': 'Contacto',
        'progress.review': 'Revisión',

        // Quiz
        'quiz.title': 'Algunas preguntas',
        'quiz.question_of': 'Pregunta {current} de {total}',
        'quiz.select_all': 'Selecciona todas las que apliquen',
        'quiz.type_answer': 'Escribe tu respuesta…',
        'quiz.yes': 'Sí',
        'quiz.no': 'No',
        'quiz.submit': 'Guardar y Continuar',
        'quiz.saving': 'Guardando…',

        // Photos
        'photos.title': 'Sube tus fotos',
        'photos.subtitle':
            'Las fotos claras y bien iluminadas ayudan a nuestra IA a proporcionar la evaluación más precisa. Todas las fotos están encriptadas y protegidas por HIPAA.',
        'photos.required_label': 'Requerida',
        'photos.optional_label': 'Opcional',
        'photos.front': 'Frente',
        'photos.left_profile': 'Perfil Izquierdo',
        'photos.right_profile': 'Perfil Derecho',
        'photos.additional': 'Adicional',
        'photos.upload_cta': 'Subir Foto',
        'photos.retake': 'Retomar',
        'photos.quality_good': 'Buena calidad',
        'photos.quality_poor':
            'Calidad deficiente — intenta con mejor iluminación',
        'photos.uploading': 'Subiendo…',
        'photos.continue_cta': 'Continuar con {count} foto(s)',
        'photos.webcam_title': 'Tomar una foto',
        'photos.webcam_instructions':
            'Coloca tu cara en el centro del encuadre y mantente quieto.',
        'photos.webcam_capture': 'Tomar Foto',
        'photos.webcam_retake': 'Retomar',
        'photos.webcam_use': 'Usar Esta Foto',

        // Contact info
        'contact.title': 'Tu información de contacto',
        'contact.subtitle':
            'Usaremos esto para enviar tus resultados de evaluación y para que tu coordinador pueda contactarte.',
        'contact.name_label': 'Nombre Completo',
        'contact.name_placeholder': 'Ana García',
        'contact.email_label': 'Correo Electrónico',
        'contact.email_placeholder': 'ana@ejemplo.com',
        'contact.phone_label': 'Número de Teléfono',
        'contact.phone_placeholder': '+1 (555) 000-0000',
        'contact.phone_hint':
            'Opcional. Solo se usa para contactarte sobre tu evaluación.',
        'contact.continue_cta': 'Continuar',

        // Consent & review
        'consent.title': 'Revisión y confirmación',
        'consent.subtitle':
            'Por favor revisa tus datos y otorga los consentimientos requeridos para completar el envío de tu evaluación.',
        'consent.summary_heading': 'Tu envío',
        'consent.summary.procedure': 'Procedimiento',
        'consent.summary.name': 'Nombre',
        'consent.summary.email': 'Correo',
        'consent.summary.phone': 'Teléfono',
        'consent.summary.photos': 'Fotos subidas',
        'consent.summary.quiz': 'Respuestas del cuestionario',
        'consent.required_heading': 'Consentimientos requeridos',
        'consent.hipaa_label': 'Aviso de Privacidad HIPAA',
        'consent.hipaa_text':
            'Reconozco que he sido informado de mis derechos bajo la Ley de Portabilidad y Responsabilidad de Seguros de Salud (HIPAA). Mi información de salud protegida puede ser utilizada para fines de tratamiento y evaluación.',
        'consent.terms_label': 'Términos de Servicio',
        'consent.terms_text':
            'Acepto los Términos de Servicio. Entiendo que esta evaluación es informativa y no constituye consejo médico. Un médico con licencia revisará mi caso.',
        'consent.photo_label': 'Consentimiento de Análisis de Fotos por IA',
        'consent.photo_text':
            'Doy mi consentimiento para que mis fotos sean analizadas por inteligencia artificial para ayudar a generar mi informe de evaluación. Las fotos están encriptadas y son accesibles solo para el personal de la clínica.',
        'consent.sms_label': 'Notificaciones por SMS',
        'consent.sms_text':
            'Doy mi consentimiento para recibir mensajes de texto sobre mi evaluación y actualizaciones de consulta. Pueden aplicar tarifas de mensajes y datos.',
        'consent.optional_badge': 'opcional',
        'consent.submit_cta': 'Enviar Mi Evaluación ✓',
        'consent.submitting': 'Enviando…',
        'consent.disclaimer':
            'Al enviar, confirmas que toda la información proporcionada es precisa y verdadera.',

        // Success
        'success.title': '¡Evaluación recibida!',
        'success.subtitle':
            'Gracias. Tu evaluación impulsada por IA está en progreso.',
        'success.next_steps_heading': 'Qué pasa ahora',
        'success.step1_title': 'Análisis de IA',
        'success.step1_body':
            'Nuestra IA está procesando tus fotos y respuestas del cuestionario para generar tu informe de evaluación personalizado.',
        'success.step2_title': 'Revisión del Coordinador',
        'success.step2_body':
            'Un coordinador de la clínica revisará tu evaluación y se comunicará contigo dentro de 1 día hábil.',
        'success.step3_title': 'Tus Resultados',
        'success.step3_body':
            'Recibirás tu informe de Hoja de Ruta de Belleza por correo electrónico una vez que tu evaluación esté completa.',

        // Footer
        'footer.hipaa_notice':
            'Tu información está encriptada y protegida bajo HIPAA.',
        'footer.powered_by': 'Evaluación IA',

        // Errors
        'error.generic': 'Algo salió mal. Por favor intenta de nuevo.',
        'error.required_field': 'Este campo es obligatorio.',
        'error.invalid_email':
            'Por favor ingresa una dirección de correo electrónico válida.',
        'error.turnstile':
            'Por favor completa la verificación de seguridad para continuar.',
    },
};
