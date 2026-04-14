import { useCallback } from 'react';
import { type Locale, type TranslationKey, translations } from './translations';

/**
 * Returns a translation function `t` scoped to the given locale.
 *
 * Usage:
 *   const { t } = useTranslation(clinic.locale ?? 'en');
 *   t('nav.back')                                  // "← Back" | "← Atrás"
 *   t('quiz.question_of', { current: 2, total: 8 }) // "Question 2 of 8" | "Pregunta 2 de 8"
 */
export function useTranslation(locale: string = 'en') {
    const safeLocale: Locale = (locale === 'es' ? 'es' : 'en') as Locale;

    const t = useCallback(
        (key: TranslationKey, vars?: Record<string, string | number>): string => {
            let str = translations[safeLocale][key] ?? translations['en'][key] ?? key;

            if (vars) {
                for (const [k, v] of Object.entries(vars)) {
                    str = str.replace(new RegExp(`\\{${k}\\}`, 'g'), String(v));
                }
            }

            return str;
        },
        [safeLocale],
    );

    return { t, locale: safeLocale };
}
