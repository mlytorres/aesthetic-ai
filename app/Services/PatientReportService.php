<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Generates Beauty Roadmap PDFs for patients.
 *
 * Patient-facing companion to the ClinicalBriefService — uses layman's language,
 * focuses on education and encouragement rather than clinical scoring.
 *
 * PHI: only first name used. No contact info, no clinical scores exposed.
 * The PDF bytes are never persisted to disk in production.
 */
class PatientReportService
{
    /**
     * Generate a Beauty Roadmap PDF and return the raw bytes.
     *
     * @param  Evaluation  $evaluation  Must have 'patient', 'tenant' already loaded.
     */
    public function generateBytes(Evaluation $evaluation): string
    {
        $data = $this->buildReportData($evaluation);

        return Pdf::view('pdf.patient-report', [
            'evaluation' => $evaluation,
            'report' => $data,
        ])
            ->format('A4')
            ->generatePdfContent();
    }

    /**
     * Build the download filename for this evaluation's patient report.
     */
    public function filename(Evaluation $evaluation): string
    {
        return sprintf(
            'your-aesthetic-roadmap-%s.pdf',
            substr($evaluation->secure_token, 0, 8),
        );
    }

    /**
     * Compile all structured data the Blade template needs.
     *
     * @return array<string, mixed>
     */
    public function buildReportData(Evaluation $evaluation): array
    {
        $analysis = $evaluation->analysis_data ?? [];
        $proportions = $analysis['proportions'] ?? [];
        $recommendations = $analysis['recommendations'] ?? [];
        $quizAnswers = $evaluation->quiz_answers ?? [];
        $procedure = $evaluation->procedure_slug;

        $patient = $evaluation->patient;
        $fullName = $patient?->name_encrypted ?? null;
        $firstName = $fullName
            ? explode(' ', trim($fullName))[0]
            : 'there';

        return [
            'first_name' => $firstName,
            'procedure' => $procedure,
            'procedure_label' => $this->procedureLabel($procedure),
            'clinic_name' => $evaluation->tenant?->name ?? config('app.name'),
            'secure_token' => $evaluation->secure_token,
            'generated_at' => now()->format('F j, Y'),
            'harmony_score' => $proportions['overall_harmony'] ?? null,
            'harmony_label' => $this->harmonyLabel($proportions['overall_harmony'] ?? null),
            'harmony_summary' => $this->harmonySummary($proportions['overall_harmony'] ?? null, $procedure),
            'proportion_highlights' => $this->proportionHighlights($proportions, $procedure),
            'primary_finding' => $recommendations['primary_finding'] ?? null,
            'key_insights' => $this->patientFriendlyInsights($recommendations, $quizAnswers, $procedure),
            'faqs' => $this->buildFaqs($procedure, $quizAnswers),
            'next_steps' => $this->nextSteps($procedure),
        ];
    }

    private function procedureLabel(string $slug): string
    {
        return match ($slug) {
            'rhinoplasty' => 'Rhinoplasty (Nose Reshaping)',
            'bbl' => 'Brazilian Butt Lift (BBL)',
            'lipo_360' => 'Liposuction 360°',
            'breast_augmentation' => 'Breast Augmentation',
            'facelift' => 'Facelift',
            default => ucwords(str_replace(['-', '_'], ' ', $slug)),
        };
    }

    private function harmonyLabel(?int $score): string
    {
        if ($score === null) {
            return 'Analysis Complete';
        }

        return match (true) {
            $score >= 80 => 'Excellent',
            $score >= 65 => 'Very Good',
            $score >= 50 => 'Good',
            $score >= 35 => 'Moderate',
            default => 'Needs Attention',
        };
    }

    private function harmonySummary(?int $score, string $procedure): string
    {
        if ($score === null) {
            return 'Our AI has completed its analysis of your submission. A specialist will review your results and reach out to discuss next steps.';
        }

        if (in_array($procedure, ['bbl', 'lipo_360', 'breast_augmentation', 'facelift'], true)) {
            return match (true) {
                $score >= 65 => 'Your photos show balanced proportions and good symmetry. This is a strong starting point for the results you are looking for.',
                $score >= 45 => 'Our AI analysis found some areas of interest that your surgeon will review during consultation. This is very common and helps create a personalised treatment plan.',
                default => 'Our AI identified several areas to discuss in depth during your consultation — this ensures your treatment plan is as precise as possible.',
            };
        }

        // Rhinoplasty / face-focused default
        return match (true) {
            $score >= 65 => 'Your facial proportions are well-balanced overall. This is a great foundation for achieving the refinements you have in mind.',
            $score >= 45 => 'Our AI analysis found your facial structure has some natural variation from the classical ideal ratios — which is completely normal and gives your surgeon clear areas to focus on.',
            default => 'Our AI identified several measurements worth discussing in your consultation. This level of detail helps your surgeon plan with precision.',
        };
    }

    /**
     * @param  array<string, mixed>  $proportions
     * @return array<int, array{label: string, value: string, note: string}>
     */
    private function proportionHighlights(array $proportions, string $procedure): array
    {
        if (empty($proportions)) {
            return [];
        }

        $highlights = [];

        // Facial procedures share the same proportion metrics
        if (in_array($procedure, ['rhinoplasty', 'facelift'], true)) {
            if (isset($proportions['overall_harmony'])) {
                $score = (int) $proportions['overall_harmony'];
                $highlights[] = [
                    'label' => 'Overall Harmony',
                    'value' => $score.'/100',
                    'note' => $score >= 65 ? 'Your proportions are within a balanced range.' : 'Your surgeon will focus on restoring balance in key areas.',
                ];
            }

            if (isset($proportions['nasal_symmetry']['symmetry_score'])) {
                $sym = (int) $proportions['nasal_symmetry']['symmetry_score'];
                $highlights[] = [
                    'label' => 'Facial Symmetry',
                    'value' => $sym.'/100',
                    'note' => $sym >= 70 ? 'Good natural symmetry detected.' : 'Minor asymmetry noted — very common and highly correctable.',
                ];
            }

            if (isset($proportions['goodes_ratio'])) {
                $ratio = (float) $proportions['goodes_ratio'];
                $highlights[] = [
                    'label' => 'Goode\'s Ratio',
                    'value' => number_format($ratio, 2),
                    'note' => ($ratio >= 0.55 && $ratio <= 0.60)
                        ? 'Within the classic ideal range (0.55–0.60).'
                        : 'Slightly outside the classic ideal — a common refinement target.',
                ];
            }
        }

        if (isset($proportions['_avg_photo_quality'])) {
            $quality = (int) $proportions['_avg_photo_quality'];
            $highlights[] = [
                'label' => 'Photo Quality',
                'value' => $quality.'/100',
                'note' => $quality >= 70 ? 'Great photo quality — clear analysis achieved.' : 'Photos processed successfully.',
            ];
        }

        return $highlights;
    }

    /**
     * Convert clinical recommendation bullets into patient-friendly language.
     *
     * @param  array<string, mixed>  $recommendations
     * @param  array<string, mixed>  $quizAnswers
     * @return array<int, string>
     */
    private function patientFriendlyInsights(array $recommendations, array $quizAnswers, string $procedure): array
    {
        if (empty($recommendations)) {
            return ['Our team will prepare a personalised summary after reviewing your submission in detail.'];
        }

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $insights = [];

        if (! empty($concerns) && is_array($concerns)) {
            $concernLabels = $this->concernLabels($procedure);
            $named = array_filter(
                array_map(fn ($c) => $concernLabels[$c] ?? null, $concerns)
            );
            if (! empty($named)) {
                $insights[] = 'Your primary areas of interest — '.implode(', ', $named).' — have been noted and will guide your consultation.';
            }
        }

        $flags = $recommendations['flags'] ?? [];
        if (in_array('revision_rhinoplasty', $flags, true) || in_array('prior_surgery', $flags, true)) {
            $insights[] = 'You mentioned prior surgery in the same area. Revision procedures are a specialist area our surgeons handle regularly — your previous history will be reviewed carefully.';
        }

        $harmony = $recommendations['harmony_score'] ?? null;
        if ($harmony !== null) {
            if ((int) $harmony >= 65) {
                $insights[] = 'Your facial proportions are well within a balanced range, which typically correlates with a smoother surgical experience and natural-looking results.';
            } else {
                $insights[] = 'Our AI has flagged specific proportion measurements for your surgeon to review — this is exactly the kind of detail that makes your consultation more productive.';
            }
        }

        if (empty($insights)) {
            $insights[] = 'Your submission has been fully processed. A specialist will walk you through the findings during your consultation.';
        }

        return array_values($insights);
    }

    /**
     * @return array<string, string>
     */
    private function concernLabels(string $procedure): array
    {
        return match ($procedure) {
            'rhinoplasty' => [
                'bridge' => 'bridge / dorsal profile',
                'tip' => 'nasal tip',
                'nostrils' => 'nostril width',
                'asymmetry' => 'asymmetry',
                'projection' => 'projection',
                'width' => 'overall width',
            ],
            'bbl' => [
                'volume' => 'volume and fullness',
                'lift' => 'lift and projection',
                'hourglass' => 'hourglass silhouette',
                'proportions' => 'overall proportions',
                'asymmetry' => 'symmetry',
            ],
            'lipo_360' => [
                'upper_abdomen' => 'upper abdomen',
                'lower_abdomen' => 'lower abdomen',
                'flanks' => 'flanks / love handles',
                'back' => 'back',
                'inner_thighs' => 'inner thighs',
                'outer_thighs' => 'outer thighs',
            ],
            'breast_augmentation' => [
                'size' => 'size increase',
                'shape' => 'shape and contour',
                'restore' => 'volume restoration',
                'asymmetry' => 'size asymmetry',
                'lift' => 'lift / position',
            ],
            'facelift' => [
                'jowls' => 'jowl definition',
                'neck' => 'neck and jawline',
                'nasolabial' => 'nasolabial folds',
                'jaw_definition' => 'jaw definition',
                'overall_aging' => 'overall rejuvenation',
            ],
            default => [],
        };
    }

    /**
     * Build FAQ items relevant to the patient's quiz answers.
     *
     * @param  array<string, mixed>  $quizAnswers
     * @return array<int, array{q: string, a: string}>
     */
    private function buildFaqs(string $procedure, array $quizAnswers): array
    {
        $faqs = [];
        $concerns = is_array($quizAnswers['q_concerns'] ?? null) ? $quizAnswers['q_concerns'] : [];
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? false;
        $smoker = $quizAnswers['q_smoker'] ?? false;
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;

        // ── Procedure-specific FAQs ────────────────────────────────────────────
        $faqs = array_merge($faqs, $this->procedureFaqs($procedure, $concerns));

        // ── Universal concern-based FAQs ──────────────────────────────────────
        if (in_array('asymmetry', $concerns, true)) {
            $faqs[] = [
                'q' => 'Can surgery correct asymmetry?',
                'a' => 'Yes — achieving greater symmetry is one of the most common and achievable goals in aesthetic surgery. Your surgeon will assess the degree of asymmetry and plan accordingly.',
            ];
        }

        // ── History-based FAQs ────────────────────────────────────────────────
        $isTruthy = fn ($v) => $v === true || $v === 'true' || $v === 1 || $v === '1';

        if ($isTruthy($priorSurgery)) {
            $faqs[] = [
                'q' => 'I have had surgery before — how does that affect the process?',
                'a' => 'Prior surgery is very common and our surgeons are experienced with revision cases. Please bring any previous operative notes or records to your consultation for the most comprehensive assessment.',
            ];
        }

        if ($isTruthy($smoker)) {
            $faqs[] = [
                'q' => 'I am a smoker — can I still have surgery?',
                'a' => 'Smoking affects healing and is an important factor to discuss. Most surgeons ask patients to stop smoking at least 4–6 weeks before and after surgery. Your surgeon will advise you on specific requirements.',
            ];
        }

        if ($weightStable === false || $weightStable === 'false' || $weightStable === 0) {
            $faqs[] = [
                'q' => 'Does my weight need to be stable before surgery?',
                'a' => 'For body contouring procedures, weight stability (within 10–15 lbs of your goal weight for at least 3–6 months) is generally recommended for the best and most lasting results. Your surgeon will discuss this during your consultation.',
            ];
        }

        // ── Timeline/budget universal ──────────────────────────────────────────
        $faqs[] = [
            'q' => 'What happens after my consultation?',
            'a' => 'After your consultation, you will receive a personalised treatment plan, cost estimate, and recovery timeline. There is no obligation, and our team is happy to answer any follow-up questions before you make any decisions.',
        ];

        $faqs[] = [
            'q' => 'Is the consultation free?',
            'a' => 'Please contact our clinic directly to confirm our consultation fee policy. Many of our consultations are complimentary — our team will confirm when scheduling your appointment.',
        ];

        return array_slice($faqs, 0, 6); // Cap at 6 FAQ items to keep PDF concise
    }

    /**
     * @param  array<int, string>  $concerns
     * @return array<int, array{q: string, a: string}>
     */
    private function procedureFaqs(string $procedure, array $concerns): array
    {
        return match ($procedure) {
            'rhinoplasty' => $this->rhinoplastyFaqs($concerns),
            'bbl' => $this->bblFaqs($concerns),
            'lipo_360' => $this->lipoFaqs($concerns),
            'breast_augmentation' => $this->breastFaqs($concerns),
            'facelift' => $this->faceliftFaqs($concerns),
            default => [],
        };
    }

    /** @param  array<int, string>  $concerns */
    private function rhinoplastyFaqs(array $concerns): array
    {
        $faqs = [[
            'q' => 'What is the recovery time for rhinoplasty?',
            'a' => 'Most patients are presentable within 10–14 days. Swelling can take 6–12 months to fully resolve, but 70–80% of the final result is visible within the first 3 months.',
        ]];

        if (in_array('bridge', $concerns, true) || in_array('tip', $concerns, true)) {
            $faqs[] = [
                'q' => 'Will my nose look natural after surgery?',
                'a' => 'Natural results are the priority. Our surgeons aim for refinements that enhance your features rather than create an "operated" look — subtle changes often make the biggest difference.',
            ];
        }

        if (in_array('nostrils', $concerns, true)) {
            $faqs[] = [
                'q' => 'Are alar base reductions visible as scarring?',
                'a' => 'Incisions for alar base reduction are placed in the natural crease of the nostril and are typically invisible once healed. Your surgeon will show you scar placement during consultation.',
            ];
        }

        return $faqs;
    }

    /** @param  array<int, string>  $concerns */
    private function bblFaqs(array $concerns): array
    {
        return [
            [
                'q' => 'What is the recovery like for a BBL?',
                'a' => 'You will need to avoid sitting directly on your buttocks for approximately 2–6 weeks post-surgery. Most patients return to light activity within 2–3 weeks. A compression garment is worn for several weeks to optimise results.',
            ],
            [
                'q' => 'How long do BBL results last?',
                'a' => 'Once the transferred fat has stabilised (typically 3–6 months), results are long-lasting. Maintaining a stable weight is the most important factor in preserving your outcome.',
            ],
        ];
    }

    /** @param  array<int, string>  $concerns */
    private function lipoFaqs(array $concerns): array
    {
        return [
            [
                'q' => 'Does liposuction remove fat permanently?',
                'a' => 'The fat cells removed by liposuction are gone permanently. However, remaining fat cells can still enlarge with weight gain — maintaining a stable weight helps preserve your results long-term.',
            ],
            [
                'q' => 'What does 360° liposuction mean?',
                'a' => 'Lipo 360° treats the entire midsection — abdomen, flanks, and back — in one session to create a more cohesive, contoured silhouette rather than addressing just one area at a time.',
            ],
        ];
    }

    /** @param  array<int, string>  $concerns */
    private function breastFaqs(array $concerns): array
    {
        $faqs = [[
            'q' => 'What is the recovery time for breast augmentation?',
            'a' => 'Most patients return to light activity within 1–2 weeks and resume full activity at 4–6 weeks. Some tightness and sensitivity is normal in the first few weeks as the implants settle.',
        ]];

        if (in_array('lift', $concerns, true)) {
            $faqs[] = [
                'q' => 'Do I need a lift as well as implants?',
                'a' => 'If there is significant sagging (ptosis), a lift combined with implants may be recommended for the best result. Your surgeon will assess this during consultation using standardised measurements.',
            ];
        }

        return $faqs;
    }

    /** @param  array<int, string>  $concerns */
    private function faceliftFaqs(array $concerns): array
    {
        return [
            [
                'q' => 'How long does a facelift last?',
                'a' => 'A facelift typically turns the clock back 10–15 years and results generally last 7–10 years. The ageing process continues after surgery, but patients consistently look younger than they would have without the procedure.',
            ],
            [
                'q' => 'Will people be able to tell I had surgery?',
                'a' => 'Modern facelift techniques prioritise a natural, refreshed appearance. The goal is for people to say you look well-rested, not "done". Incisions are placed in natural creases and hairline margins for minimal visibility.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function nextSteps(string $procedure): array
    {
        return [
            'Your evaluation has been reviewed by our AI system and your results are ready.',
            'A member of our team will reach out to you shortly to discuss your personalised consultation.',
            'Please bring this report (or your evaluation reference number) to your consultation appointment.',
            'There is absolutely no obligation to proceed — consultations are an opportunity to ask questions and explore your options.',
        ];
    }
}
