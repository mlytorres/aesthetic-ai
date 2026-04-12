<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Models\Evaluation;
use App\Services\AuditLog;
use App\Services\LeadScoringService;
use App\Services\WebhookService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job 4/4 in the AI pipeline.
 *
 * Generates rule-based procedure recommendations combining:
 *   - Facial proportion scores from CalculateProportionsJob
 *   - Face attributes (age estimate, photo quality) from ExtractFacialLandmarksJob
 *   - Quiz answers (concerns, prior surgery, skin type, budget, timeline)
 *
 * All 5 procedures have dedicated recommendation methods:
 *   rhinoplasty, bbl, lipo_360, breast_augmentation, facelift
 *
 * After generating recommendations, it:
 *   1. Calls LeadScoringService to compute lead score + priority
 *   2. Updates evaluation status to 'complete'
 *   3. Dispatches NotifyClinicNewEvaluationJob + SendPatientReportJob
 *   4. Fires evaluation.analysis_complete webhook
 */
class GenerateBasicRecommendationsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(LeadScoringService $scorer, AuditLog $auditLog, WebhookService $webhooks): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::findOrFail($this->evaluationId);
        $analysisData = $evaluation->analysis_data ?? [];
        $proportions = $analysisData['proportions'] ?? [];
        $quizAnswers = $evaluation->quiz_answers ?? [];
        $procedure = $evaluation->procedure_slug;
        $faceAttrs = $proportions['_face_attributes'] ?? [];

        // ── Generate procedure-specific recommendations ───────────────────────
        $recommendations = match ($procedure) {
            // ── Original 5 MVP procedures ──────────────────────────────────────
            'rhinoplasty' => $this->rhinoplastyRecommendations($proportions, $quizAnswers),
            'bbl' => $this->bblRecommendations($proportions, $quizAnswers),
            'lipo_360' => $this->lipo360Recommendations($proportions, $quizAnswers),
            'breast_augmentation' => $this->breastAugRecommendations($proportions, $quizAnswers),
            'facelift' => $this->faceliftRecommendations($proportions, $quizAnswers, $faceAttrs),

            // ── New body procedures ────────────────────────────────────────────
            'tummy_tuck' => $this->tummyTuckRecommendations($proportions, $quizAnswers),
            'mommy_makeover' => $this->mommyMakeoverRecommendations($proportions, $quizAnswers),
            'breast_lift' => $this->breastLiftRecommendations($proportions, $quizAnswers),
            'breast_reduction' => $this->breastReductionRecommendations($proportions, $quizAnswers),
            'skinny_bbl' => $this->skinnyBblRecommendations($proportions, $quizAnswers),
            'gynecomastia' => $this->gynecomastiaRecommendations($proportions, $quizAnswers),

            // ── New face procedures ────────────────────────────────────────────
            'face_and_neck_lift' => $this->faceNeckLiftRecommendations($proportions, $quizAnswers, $faceAttrs),
            'eyelid_surgery' => $this->eyelidSurgeryRecommendations($proportions, $quizAnswers, $faceAttrs),

            default => $this->genericRecommendations($proportions, $quizAnswers),
        };

        // ── Score the lead ────────────────────────────────────────────────────
        [$leadScore, $priority] = $scorer->score($evaluation, $proportions, $quizAnswers);

        // ── Persist everything and mark complete ──────────────────────────────
        $evaluation->update([
            'status' => Evaluation::STATUS_COMPLETE,
            'lead_score' => $leadScore,
            'priority' => $priority,
            'analysis_data' => array_merge($analysisData, [
                'recommendations' => $recommendations,
                'recommendations_generated_at' => now()->toIso8601String(),
            ]),
        ]);

        $auditLog->recordSystem('evaluation.analysis.complete', $evaluation, [
            'lead_score' => $leadScore,
            'priority' => $priority,
        ]);

        // ── Notify clinic + send patient report ───────────────────────────────
        if (config('features.notifications', true)) {
            NotifyClinicNewEvaluationJob::dispatch($this->evaluationId)->onQueue('notifications');
            SendPatientReportJob::dispatch($this->evaluationId)->onQueue('notifications');
        }

        // ── Fire webhook ──────────────────────────────────────────────────────
        $webhooks->dispatch($evaluation, 'evaluation.analysis_complete', [
            'ai_summary' => $recommendations['primary_finding'] ?? null,
        ]);
    }

    // ─── Rhinoplasty ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function rhinoplastyRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $skinType = $quizAnswers['q_skin_thickness'] ?? null;
        match ($skinType) {
            'thin' => $bullets[] = 'Thin skin provides excellent definition and shows fine detail work well, but requires meticulous refinement to avoid visible scarring.',
            'thick' => $bullets[] = 'Thick/sebaceous skin may obscure tip refinement. Skin-thinning techniques (defatting) may be discussed during consultation.',
            default => null,
        };

        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_rhinoplasty';
            $bullets[] = 'Revision rhinoplasty is technically more complex due to scar tissue and altered anatomy. Surgeon should review prior operative notes if available.';
            $techniques[] = 'Revision approach likely required';
        }

        $breathing = $quizAnswers['q_breathing'] ?? null;
        if ($this->isTruthy($breathing)) {
            $flags[] = 'functional_component';
            $bullets[] = 'Patient reports nasal breathing difficulties. Functional evaluation (septal deviation, turbinate hypertrophy) recommended alongside cosmetic assessment.';
            $techniques[] = 'Functional rhinoplasty component likely';
        }

        $concerns = $quizAnswers['q_concerns'] ?? [];
        if (is_array($concerns)) {
            if (in_array('bridge', $concerns, true)) {
                $bullets[] = 'Dorsal hump reduction requested. Osteotomies may be required to close the open roof after hump removal.';
                $techniques[] = 'Hump reduction + osteotomies';
            }
            if (in_array('tip', $concerns, true)) {
                $bullets[] = 'Tip refinement requested. Structural grafting (tip graft, columellar strut) may enhance definition and maintain support.';
                $techniques[] = 'Tip refinement ± structural grafting';
            }
            if (in_array('nostrils', $concerns, true)) {
                $bullets[] = 'Alar base modification requested. Weir excisions may be considered for nostril width/flare reduction.';
                $techniques[] = 'Alar base reduction (Weir excisions)';
            }
            if (in_array('asymmetry', $concerns, true)) {
                $flags[] = 'asymmetry_noted';
                $bullets[] = 'Asymmetry is a primary concern. AI analysis supports this — surgical plan should prioritise bilateral symmetry.';
            }
        }

        $harmonyScore = $proportions['overall_harmony'] ?? 50;
        if ($harmonyScore >= 75) {
            $bullets[] = 'Facial proportions are within normal range. Targeted refinements should achieve an excellent aesthetic result.';
        } elseif ($harmonyScore >= 50) {
            $bullets[] = 'Facial proportions show moderate variation from ideal ratios. Surgical planning should account for these findings.';
        } else {
            $flags[] = 'significant_proportion_deviation';
            $bullets[] = 'Proportion analysis indicates notable deviation from ideal facial thirds/fifths. Surgeon should review AI measurements during consultation.';
        }

        $nasalSym = $proportions['nasal_symmetry']['score'] ?? 50;
        if ($nasalSym < 70) {
            $flags[] = 'nasal_asymmetry_detected';
            $bullets[] = sprintf('Nasal symmetry score: %d/100. Asymmetry visible in AI analysis — surgeon should confirm clinically.', $nasalSym);
        }

        $nasalWidth = $proportions['nasal_width_ratio'] ?? [];
        if (isset($nasalWidth['ratio'])) {
            if ($nasalWidth['ratio'] > 1.2) {
                $bullets[] = sprintf('Alar width ratio %.2f exceeds ideal (1.0), suggesting wider-than-ideal nostrils. Alar base reduction may be beneficial.', $nasalWidth['ratio']);
            } elseif ($nasalWidth['ratio'] < 0.8) {
                $bullets[] = sprintf('Alar width ratio %.2f is below ideal (1.0). This narrow base may influence tip projection planning.', $nasalWidth['ratio']);
            }
        }

        return [
            'procedure' => 'rhinoplasty',
            'confidence' => $this->confidenceFromHarmony($harmonyScore),
            'primary_finding' => $this->rhinoplastyPrimaryFinding($concerns, $priorSurgery),
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── BBL ──────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function bblRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $donorAreas = $quizAnswers['q_donor_areas'] ?? [];
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;

        // ── Weight stability ──────────────────────────────────────────────────
        if ($this->isFalsy($weightStable)) {
            $flags[] = 'weight_unstable';
            $bullets[] = 'Patient indicated weight is not currently stable. BBL results are optimised when weight is stable for at least 3–6 months. Surgeon should discuss timing.';
        } else {
            $bullets[] = 'Stable weight reported — this is a positive indicator for long-lasting BBL results.';
        }

        // ── Donor area planning ───────────────────────────────────────────────
        if (is_array($donorAreas) && ! empty($donorAreas)) {
            $donorLabels = [
                'abdomen' => 'abdomen',
                'flanks' => 'flanks / love handles',
                'back' => 'back',
                'thighs' => 'inner/outer thighs',
                'not_sure' => 'undecided (surgeon to assess)',
            ];
            $named = array_filter(array_map(fn ($a) => $donorLabels[$a] ?? null, $donorAreas));
            if (! empty($named)) {
                $bullets[] = 'Patient-indicated donor areas: '.implode(', ', $named).'. Surgeon should assess available fat volume and harvest feasibility during examination.';
                $techniques[] = 'Liposuction harvest from: '.implode(', ', $named);
            }
        } else {
            $flags[] = 'donor_areas_unspecified';
            $bullets[] = 'No donor areas specified. Surgeon should assess available fat volume at consultation and discuss realistic volume transfer expectations.';
        }

        // ── Desired result shaping ────────────────────────────────────────────
        if (is_array($concerns)) {
            if (in_array('hourglass', $concerns, true)) {
                $bullets[] = 'Patient seeks hourglass silhouette. Combined liposuction of waist and flanks alongside fat transfer optimises this outcome.';
                $techniques[] = 'Waist + flank liposuction combined with gluteal fat transfer';
            }
            if (in_array('lift', $concerns, true)) {
                $bullets[] = 'Gluteal lift and projection is a primary goal. Upper gluteal fat placement enhances projection.';
                $techniques[] = 'Upper quadrant gluteal fat placement for lift effect';
            }
            if (in_array('asymmetry', $concerns, true)) {
                $flags[] = 'asymmetry_noted';
                $bullets[] = 'Patient reports gluteal asymmetry. Asymmetric fat placement will be required — pre-operative photos and measurements are essential.';
            }
            if (in_array('volume', $concerns, true)) {
                $bullets[] = 'Volume increase is the primary goal. Fat survival (typically 60–70% at 6 months) should be discussed when setting volume expectations.';
            }
        }

        // ── Safety note (BBL-specific) ────────────────────────────────────────
        $bullets[] = 'BBL safety protocol: fat must be injected into the subcutaneous layer only. Surgeon should confirm intragluteal injection avoidance per ASAPS/ISAPS guidelines.';
        $flags[] = 'bbl_safety_protocol_required';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'bbl',
            'confidence' => 'medium',
            'primary_finding' => $this->bblPrimaryFinding($concerns, $donorAreas),
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Lipo 360 ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function lipo360Recommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $skinLaxity = $quizAnswers['q_skin_laxity'] ?? null;
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;

        // ── Weight stability ──────────────────────────────────────────────────
        if ($this->isFalsy($weightStable)) {
            $flags[] = 'weight_unstable';
            $bullets[] = 'Weight not currently stable. Liposuction results are best maintained when weight is stable for 3–6 months pre-operatively.';
        }

        // ── Skin laxity assessment ────────────────────────────────────────────
        match ($skinLaxity) {
            'excellent' => $bullets[] = 'Patient reports excellent skin elasticity. Good skin retraction is expected post-liposuction — results should be smooth and well-contoured.',
            'mild' => $bullets[] = 'Mild skin laxity noted. Skin retraction should be adequate for most patients. Surgeon should assess in-person.',
            'moderate' => ($flags[] = 'skin_laxity_concern') && ($bullets[] = 'Moderate skin laxity reported. Liposuction alone may result in skin redundancy in treated areas. Surgeon should discuss whether skin excision (tummy tuck / body lift) may be needed alongside liposuction.'),
            default => $bullets[] = 'Skin laxity not specified — surgeon should assess skin quality and elasticity during consultation.',
        };

        // ── Concern areas ─────────────────────────────────────────────────────
        if (is_array($concerns) && ! empty($concerns)) {
            $areaLabels = [
                'upper_abdomen' => 'upper abdomen',
                'lower_abdomen' => 'lower abdomen',
                'flanks' => 'flanks',
                'back' => 'back rolls',
                'inner_thighs' => 'inner thighs',
                'outer_thighs' => 'outer thighs / saddlebags',
            ];
            $named = array_filter(array_map(fn ($c) => $areaLabels[$c] ?? null, $concerns));
            if (! empty($named)) {
                $bullets[] = 'Target areas: '.implode(', ', $named).'. 360° approach treats anterior and posterior midsection for circumferential contour improvement.';
                $techniques[] = 'Tumescent liposuction: '.implode(', ', $named);
            }

            if (in_array('back', $concerns, true)) {
                $bullets[] = 'Back liposuction requires prone positioning. Surgeon should plan for repositioning during the procedure.';
                $techniques[] = 'Prone repositioning for posterior treatment';
            }
        }

        $bullets[] = 'Post-operative compression garment is essential for 4–6 weeks to optimise skin retraction and reduce swelling.';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'lipo_360',
            'confidence' => 'medium',
            'primary_finding' => $this->lipoPrimaryFinding($concerns, $skinLaxity),
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Breast Augmentation ──────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function breastAugRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $resultPreference = $quizAnswers['q_result_preference'] ?? null;
        $sizeGoal = $quizAnswers['q_size_goal'] ?? null;
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;

        // ── Prior surgery (revision case) ─────────────────────────────────────
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_breast_surgery';
            $bullets[] = 'Prior breast surgery documented. Revision augmentation requires assessment of existing implant position, capsule status, and pocket integrity. Previous operative notes are essential.';
            $techniques[] = 'Revision approach — capsulectomy or capsulotomy may be required';
        }

        // ── Result preference ─────────────────────────────────────────────────
        match ($resultPreference) {
            'natural' => $bullets[] = 'Patient prefers a natural result. This typically favours anatomical (teardrop) implants or moderate-profile round implants with conservative volume.',
            'moderate' => $bullets[] = 'Moderate enhancement desired. Round implants with moderate-plus or high profile may be appropriate depending on chest wall dimensions.',
            'significant' => ($bullets[] = 'Significant enhancement requested. High-profile implants or larger volumes will be discussed. Surgeon should assess skin/tissue quality and IMF position.') && ($flags[] = 'high_volume_request'),
            default => null,
        };

        // ── Size goal ─────────────────────────────────────────────────────────
        match ($sizeGoal) {
            '1_cup' => $bullets[] = 'Goal: approximately 1 cup size increase. This is achievable with a modest implant volume (typically 150–250cc) and lower surgical risk.',
            '2_cups' => $bullets[] = 'Goal: approximately 2 cup size increase. Implant volume and profile selection should account for existing tissue coverage.',
            '3_plus' => ($bullets[] = 'Goal: 3+ cup size increase. Larger implants (400cc+) carry higher risks of implant visibility, rippling, and long-term sagging. Surgeon should discuss realistic outcomes.') && ($flags[] = 'large_volume_request'),
            'unsure' => $bullets[] = 'Patient unsure of size goal. 3D imaging / sizer trials at consultation may help establish realistic expectations.',
            default => null,
        };

        // ── Lift consideration ────────────────────────────────────────────────
        if (is_array($concerns) && in_array('lift', $concerns, true)) {
            $flags[] = 'lift_consideration';
            $bullets[] = 'Patient has indicated ptosis (sagging) as a concern. Clinical assessment of ptosis grade (Regnault classification) will determine whether a mastopexy is required alongside augmentation.';
            $techniques[] = 'Mastopexy evaluation required';
        }

        if (is_array($concerns) && in_array('asymmetry', $concerns, true)) {
            $flags[] = 'breast_asymmetry';
            $bullets[] = 'Breast asymmetry noted as a concern. Differential implant sizing may be required. Pre-operative measurements and photography are essential for planning.';
        }

        if (is_array($concerns) && in_array('restore', $concerns, true)) {
            $bullets[] = 'Volume restoration goal (post-pregnancy/weight loss). Fat transfer or implants — or combination — should be discussed based on available native tissue.';
        }

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'breast_augmentation',
            'confidence' => 'medium',
            'primary_finding' => $this->breastAugPrimaryFinding($concerns, $priorSurgery, $sizeGoal),
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Facelift ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @param  array<string, mixed>  $faceAttrs  From Rekognition AgeRange + Quality
     * @return array<string, mixed>
     */
    private function faceliftRecommendations(array $proportions, array $quizAnswers, array $faceAttrs): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $resultPreference = $quizAnswers['q_result_preference'] ?? null;
        $smoker = $quizAnswers['q_smoker'] ?? null;
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;

        // ── Age estimate from Rekognition ─────────────────────────────────────
        $estimatedAge = $faceAttrs['age_range']['midpoint'] ?? null;
        if ($estimatedAge !== null) {
            if ($estimatedAge < 40) {
                $flags[] = 'young_facelift_candidate';
                $bullets[] = sprintf('AI estimated age: ~%d years. Younger facelift candidates may benefit from a mini-lift or SMAS plication rather than full rhytidectomy — surgeon should assess.', $estimatedAge);
            } elseif ($estimatedAge >= 60) {
                $flags[] = 'mature_facelift_candidate';
                $bullets[] = sprintf('AI estimated age: ~%d years. More advanced facial aging may require comprehensive SMAS rhytidectomy with fat repositioning and possible fat grafting for volume restoration.', $estimatedAge);
                $techniques[] = 'Deep-plane or composite facelift consideration';
            } else {
                $bullets[] = sprintf('AI estimated age: ~%d years. Standard SMAS facelift techniques are appropriate for this age range.', $estimatedAge);
            }
        }

        // ── Smoker risk ───────────────────────────────────────────────────────
        if ($this->isTruthy($smoker)) {
            $flags[] = 'smoker_high_risk';
            $bullets[] = 'Active smoker documented. Smoking significantly increases facelift complication risk (skin necrosis, poor wound healing). Surgeon should require smoking cessation 4–6 weeks pre- and post-operatively. Surgery may need to be postponed.';
        }

        // ── Prior surgery ─────────────────────────────────────────────────────
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_facelift';
            $bullets[] = 'Prior facial surgery documented. Revision facelift requires careful assessment of existing scar placement, SMAS mobility, and hairline position.';
            $techniques[] = 'Revision facelift — scar management required';
        }

        // ── Concern areas ─────────────────────────────────────────────────────
        if (is_array($concerns)) {
            if (in_array('jowls', $concerns, true)) {
                $bullets[] = 'Jowl laxity is a primary concern. SMAS elevation and suspension addresses the anatomical cause of jowling.';
                $techniques[] = 'SMAS plication / SMASectomy for jowl correction';
            }
            if (in_array('neck', $concerns, true)) {
                $bullets[] = 'Neck and platysmal bands noted as a concern. Platysmaplasty (neck lift) combined with the facelift addresses anterior neck laxity and banding.';
                $techniques[] = 'Platysmaplasty + cervicoplasty';
            }
            if (in_array('nasolabial', $concerns, true)) {
                $bullets[] = 'Deep nasolabial folds are a concern. These are best addressed through deep-plane or extended-SMAS techniques, or supplemented with fat grafting.';
                $techniques[] = 'Deep-plane technique or fat grafting for NLF';
            }
            if (in_array('overall_aging', $concerns, true)) {
                $bullets[] = 'Comprehensive facial rejuvenation requested. A combined approach (facelift + eyelid surgery + skin resurfacing) may be discussed for a more complete result.';
                $flags[] = 'comprehensive_rejuvenation_candidate';
            }
        }

        // ── Result preference ─────────────────────────────────────────────────
        match ($resultPreference) {
            'subtle' => $bullets[] = 'Patient prefers a subtle, natural-looking result. Conservative vector of pull and conservative SMAS tension are appropriate.',
            'significant' => $bullets[] = 'Significant rejuvenation desired. A more comprehensive technique with greater tissue repositioning may be planned.',
            default => null,
        };

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'facelift',
            'confidence' => $estimatedAge !== null ? 'medium' : 'low',
            'primary_finding' => $this->faceliftPrimaryFinding($concerns, $estimatedAge),
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
            'estimated_age' => $estimatedAge,
        ];
    }

    // ─── Tummy Tuck ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function tummyTuckRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $postPregnancy = $quizAnswers['q_post_pregnancy'] ?? null;
        $diastasis = $quizAnswers['q_diastasis'] ?? null;
        $priorAbdominal = $quizAnswers['q_prior_surgery'] ?? null;
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;
        $futurePregancy = $quizAnswers['q_future_pregnancy'] ?? null;

        // ── Future pregnancy warning ───────────────────────────────────────────
        if ($this->isTruthy($futurePregancy)) {
            $flags[] = 'future_pregnancy_planned';
            $bullets[] = 'Patient has indicated they may be planning future pregnancies. '
                .'Tummy tuck is generally recommended after completing childbearing — '
                .'subsequent pregnancy can compromise the surgical result.';
        }

        // ── Post-pregnancy context ─────────────────────────────────────────────
        if ($this->isTruthy($postPregnancy)) {
            $bullets[] = 'Post-pregnancy abdominoplasty patient. '
                .'Skin redundancy, stretch marks, and diastasis recti are common after pregnancy — '
                .'all are addressable with a full tummy tuck.';
        }

        // ── Diastasis recti ───────────────────────────────────────────────────
        if ($this->isTruthy($diastasis)) {
            $flags[] = 'diastasis_recti_suspected';
            $bullets[] = 'Patient suspects diastasis recti. '
                .'Fascial plication should be included in the surgical plan. '
                .'Pre-operative clinical or ultrasound assessment recommended.';
            $techniques[] = 'Rectus fascia plication for diastasis repair';
        }

        // ── Prior abdominal surgery ────────────────────────────────────────────
        if ($this->isTruthy($priorAbdominal)) {
            $flags[] = 'prior_abdominal_surgery';
            $bullets[] = 'Prior abdominal surgery documented (C-section, laparoscopy, etc.). '
                .'Surgeon should review scar position and assess impact on flap vascularity. '
                .'Mini-TT or modified approach may be required depending on incision location.';
            $techniques[] = 'Vascular assessment of abdominal flap';
        }

        // ── Weight stability ──────────────────────────────────────────────────
        if ($this->isFalsy($weightStable)) {
            $flags[] = 'weight_unstable';
            $bullets[] = 'Weight not currently stable. Tummy tuck results are optimised '
                .'when BMI and weight have been stable for at least 6 months. '
                .'Surgeon should discuss timing.';
        }

        $bullets[] = 'Full abdominoplasty includes excision of excess lower abdominal skin, '
            .'umbilicoplasty (navel repositioning), and waistline definition. '
            .'Mini-tuck may be appropriate if laxity is confined to the lower abdomen.';

        $bullets[] = 'Post-operative abdominal binder/compression garment is required for 6–8 weeks. '
            .'Heavy lifting restrictions for 6 weeks.';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'tummy_tuck',
            'confidence' => 'medium',
            'primary_finding' => $this->isTruthy($diastasis)
                ? 'Tummy tuck with diastasis repair indicated.'
                : 'Abdominoplasty consultation — excess skin and contour improvement goal.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Mommy Makeover ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function mommyMakeoverRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $components = $quizAnswers['q_concerns'] ?? [];
        $futurePregancy = $quizAnswers['q_future_pregnancy'] ?? null;
        $breastfeeding = $quizAnswers['q_breastfeeding'] ?? null;
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;

        // ── Critical: future pregnancy / breastfeeding ─────────────────────────
        if ($this->isTruthy($futurePregancy)) {
            $flags[] = 'future_pregnancy_planned';
            $bullets[] = 'IMPORTANT: Patient indicated they may plan future pregnancies. '
                .'Mommy Makeover should be deferred until after completing childbearing — '
                .'pregnancy reverses the surgical results.';
        }

        if ($this->isTruthy($breastfeeding)) {
            $flags[] = 'currently_breastfeeding';
            $bullets[] = 'Patient is currently breastfeeding or recently stopped. '
                .'Breast surgery should be deferred until at least 6 months after cessation of breastfeeding '
                .'to allow breast tissue to fully stabilise.';
        }

        // ── Weight stability ──────────────────────────────────────────────────
        if ($this->isFalsy($weightStable)) {
            $flags[] = 'weight_unstable';
            $bullets[] = 'Weight not currently stable. Mommy Makeover results — '
                .'especially liposuction and tummy tuck components — are best when '
                .'weight has been stable for 6+ months.';
        }

        // ── Procedure components ──────────────────────────────────────────────
        if (is_array($components)) {
            $componentLabels = [
                'breast' => 'breast enhancement (augmentation or lift)',
                'abdomen' => 'tummy tuck / abdominoplasty',
                'lipo' => 'liposuction contouring',
                'bbl' => 'Brazilian Butt Lift',
                'labia' => 'labiaplasty',
            ];
            $named = array_filter(array_map(fn ($c) => $componentLabels[$c] ?? null, $components));

            if (! empty($named)) {
                $bullets[] = 'Patient-indicated Mommy Makeover components: '.implode(', ', $named).'. '
                    .'All components can typically be combined in a single surgical session when safe to do so.';
                $techniques[] = 'Combined procedure: '.implode(' + ', $named);
            }
        }

        $bullets[] = 'Mommy Makeover is a high-revenue, high-complexity combined procedure. '
            .'Surgical time and anaesthesia risk increase with each added component. '
            .'Surgeon should assess candidacy and ASA status.';

        $flags[] = 'combined_procedure_complexity';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'mommy_makeover',
            'confidence' => 'medium',
            'primary_finding' => 'Mommy Makeover — combined post-pregnancy body restoration consultation.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Breast Lift ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function breastLiftRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        $breastfeeding = $quizAnswers['q_breastfeeding'] ?? null;
        $addVolume = $quizAnswers['q_add_volume'] ?? null;
        $concerns = $quizAnswers['q_concerns'] ?? [];

        // ── Breastfeeding timing ──────────────────────────────────────────────
        if ($this->isTruthy($breastfeeding)) {
            $flags[] = 'breastfeeding_timing';
            $bullets[] = 'Patient recently breastfed or is currently breastfeeding. '
                .'Breast lift should be deferred for at least 6 months post-breastfeeding '
                .'to allow tissue to stabilise and final ptosis to be assessed.';
        }

        // ── Implant augmentation combined ─────────────────────────────────────
        if ($this->isTruthy($addVolume)) {
            $flags[] = 'augmentation_mastopexy_combined';
            $bullets[] = 'Patient wishes to add volume alongside the lift. '
                .'Augmentation-mastopexy is technically more complex than either procedure alone — '
                .'tension and blood supply must be carefully balanced. '
                .'Surgeon should discuss staging (one vs. two operations) based on degree of ptosis.';
            $techniques[] = 'Augmentation-mastopexy — assess for staged approach';
        }

        // ── Prior surgery ─────────────────────────────────────────────────────
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_breast_surgery';
            $bullets[] = 'Prior breast surgery documented. Revision mastopexy requires assessment '
                .'of existing scars, nipple-areola complex vascularity, and residual ptosis grade.';
            $techniques[] = 'Revision mastopexy planning';
        }

        // ── Ptosis severity ───────────────────────────────────────────────────
        if (is_array($concerns) && in_array('severe_droop', $concerns, true)) {
            $flags[] = 'significant_ptosis';
            $bullets[] = 'Severe sagging reported. Regnault grade 2–3 ptosis typically requires '
                .'a full (inverted-T / Wise pattern) mastopexy for adequate tissue repositioning.';
            $techniques[] = 'Wise pattern / anchor mastopexy';
        }

        $bullets[] = 'Breast lift improves position and shape without adding volume. '
            .'Skin tightening and nipple elevation are the primary goals.';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'breast_lift',
            'confidence' => 'medium',
            'primary_finding' => $this->isTruthy($addVolume)
                ? 'Augmentation-mastopexy consultation — lift with volume enhancement.'
                : 'Breast lift (mastopexy) consultation — ptosis correction.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Breast Reduction ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function breastReductionRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        // q_concerns is the universal quiz key — breast_reduction quiz stores symptoms there.
        $symptoms = $quizAnswers['q_concerns'] ?? [];
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        $breastfeeding = $quizAnswers['q_breastfeeding'] ?? null;

        // ── Breastfeeding timing ──────────────────────────────────────────────
        if ($this->isTruthy($breastfeeding)) {
            $flags[] = 'breastfeeding_timing';
            $bullets[] = 'Patient recently breastfed. Breast reduction should be deferred '
                .'at least 6 months post-breastfeeding. Note that reduction may affect '
                .'future breastfeeding ability — this should be disclosed pre-operatively.';
        }

        // ── Functional symptoms ───────────────────────────────────────────────
        if (is_array($symptoms)) {
            if (in_array('back_pain', $symptoms, true)) {
                $flags[] = 'functional_back_pain';
                $bullets[] = 'Chronic back pain reported. This is a common functional indication for '
                    .'breast reduction and may support insurance coverage in applicable plans.';
            }
            if (in_array('skin_rash', $symptoms, true)) {
                $flags[] = 'inframammary_skin_issues';
                $bullets[] = 'Skin rash or intertrigo under the breasts reported. '
                    .'This is a functional symptom that further supports reduction candidacy.';
            }
            if (in_array('shoulder_grooving', $symptoms, true)) {
                $bullets[] = 'Bra strap shoulder grooving noted — a classic functional sign of macromastia.';
            }
        }

        // ── Prior surgery ─────────────────────────────────────────────────────
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_breast_surgery';
            $bullets[] = 'Prior breast surgery documented. Revision reduction requires '
                .'careful assessment of existing pedicle and NAC vascularity.';
            $techniques[] = 'Revision reduction — pedicle assessment critical';
        }

        $bullets[] = 'Breast reduction relieves functional symptoms and improves aesthetic proportions. '
            .'Vertical scar (LeJour) or inferior pedicle (Wise pattern) technique selected based on volume.';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'breast_reduction',
            'confidence' => 'medium',
            'primary_finding' => ! empty($flags) && in_array('functional_back_pain', $flags, true)
                ? 'Breast reduction — functional macromastia with back pain and symptomatic presentation.'
                : 'Breast reduction consultation — volume and proportion correction goal.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Skinny BBL ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function skinnyBblRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $donorAreas = $quizAnswers['q_donor_areas'] ?? [];
        $weightStable = $quizAnswers['q_weight_stable'] ?? null;
        $bmi = $quizAnswers['q_bmi_range'] ?? null;

        // ── Low donor fat concern (key Skinny BBL risk) ───────────────────────
        if ($bmi === 'low' || in_array('not_sure', (array) $donorAreas, true)) {
            $flags[] = 'low_donor_fat_concern';
            $bullets[] = 'Skinny BBL: Low body fat may limit donor fat volume. '
                .'Surgeon must assess available harvest sites carefully — '
                .'insufficient donor fat may result in a conservative enhancement only. '
                .'Patient expectations should be calibrated pre-operatively.';
            $techniques[] = 'Micro-fat / nano-fat grafting may extend available volume';
        }

        // ── Safety: same as standard BBL ─────────────────────────────────────
        $bullets[] = 'BBL safety protocol applies: subcutaneous-only injection mandatory. '
            .'Extra caution warranted in slender patients due to reduced tissue buffer.';
        $flags[] = 'bbl_safety_protocol_required';

        // ── Weight stability ──────────────────────────────────────────────────
        if ($this->isFalsy($weightStable)) {
            $flags[] = 'weight_unstable';
            $bullets[] = 'Weight not currently stable. Even small weight changes significantly '
                .'affect results in lean patients — stability is especially important for Skinny BBL.';
        }

        $bullets[] = 'Skinny BBL targets subtle, athletic gluteal enhancement in lean-frame patients. '
            .'Results may be more conservative than standard BBL given available fat volume.';

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'skinny_bbl',
            'confidence' => 'medium',
            'primary_finding' => 'Skinny BBL consultation — subtle gluteal enhancement for lean frame.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Gynecomastia ─────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function gynecomastiaRecommendations(array $proportions, array $quizAnswers): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $duration = $quizAnswers['q_duration'] ?? null;
        $medications = $quizAnswers['q_medications'] ?? null;
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        $gradeEstimate = $quizAnswers['q_grade'] ?? null;

        // ── Medication-induced gynecomastia ───────────────────────────────────
        if ($this->isTruthy($medications)) {
            $flags[] = 'medication_induced_gynecomastia';
            $bullets[] = 'Patient taking medications that may cause or contribute to gynecomastia '
                .'(anabolic steroids, anti-androgens, some antihypertensives). '
                .'Surgeon should assess whether discontinuing the causative medication '
                .'is possible before scheduling surgery.';
        }

        // ── Grade-based technique guidance ────────────────────────────────────
        match ($gradeEstimate) {
            'mild' => ($bullets[] = 'Mild gynecomastia — liposuction alone may achieve adequate contour.')
                   && ($techniques[] = 'Liposuction-only approach'),
            'moderate' => ($bullets[] = 'Moderate gynecomastia — combination of liposuction and glandular excision typically required.')
                       && ($techniques[] = 'Liposuction + glandular excision'),
            'severe' => ($flags[] = 'skin_excision_likely')
                    && ($bullets[] = 'Significant gynecomastia — skin excision (chest lift component) may be required for adequate correction.')
                    && ($techniques[] = 'Liposuction + glandular excision + skin excision'),
            default => $bullets[] = 'Grade of gynecomastia to be confirmed clinically. Technique selection depends on glandular vs. adipose component and skin laxity.',
        };

        // ── Duration ──────────────────────────────────────────────────────────
        if ($duration === 'pubescent' || $duration === 'adolescent') {
            $flags[] = 'pubertal_onset';
            $bullets[] = 'Gynecomastia since puberty noted. Long-standing glandular tissue '
                .'may be more fibrous — pure liposuction less effective. '
                .'Direct excision likely required.';
        }

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'gynecomastia',
            'confidence' => 'medium',
            'primary_finding' => 'Gynecomastia correction consultation — male chest contouring.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Face & Neck Lift ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @param  array<string, mixed>  $faceAttrs
     * @return array<string, mixed>
     */
    private function faceNeckLiftRecommendations(array $proportions, array $quizAnswers, array $faceAttrs): array
    {
        // Face and neck lift shares most logic with facelift — delegate and override procedure key.
        $result = $this->faceliftRecommendations($proportions, $quizAnswers, $faceAttrs);
        $result['procedure'] = 'face_and_neck_lift';

        // Additional neck-specific note
        $result['key_points'][] = 'Face and neck lift combines facial rejuvenation with cervicoplasty. '
            .'The cervicomental angle and submental fat are addressed alongside the lower face.';
        $result['technique_notes'][] = 'Combined SMAS rhytidectomy + platysmaplasty';

        return $result;
    }

    // ─── Eyelid Surgery ───────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @param  array<string, mixed>  $faceAttrs
     * @return array<string, mixed>
     */
    private function eyelidSurgeryRecommendations(array $proportions, array $quizAnswers, array $faceAttrs): array
    {
        $flags = [];
        $bullets = [];
        $techniques = [];

        $concerns = $quizAnswers['q_concerns'] ?? [];
        $priorSurgery = $quizAnswers['q_prior_surgery'] ?? null;
        $dryEyes = $quizAnswers['q_dry_eyes'] ?? null;

        // ── Dry eye risk ──────────────────────────────────────────────────────
        if ($this->isTruthy($dryEyes)) {
            $flags[] = 'dry_eye_risk';
            $bullets[] = 'Patient reports dry eyes. Upper blepharoplasty can worsen dry eye symptoms '
                .'if too much skin is removed. Ophthalmology evaluation recommended pre-operatively. '
                .'Conservative skin excision planned.';
        }

        // ── Upper vs lower ────────────────────────────────────────────────────
        if (is_array($concerns)) {
            if (in_array('upper', $concerns, true)) {
                $bullets[] = 'Upper blepharoplasty: remove excess upper eyelid skin and/or herniated fat. '
                    .'Functional improvement (visual field) may qualify for insurance coverage — '
                    .'visual field testing recommended if functional complaint exists.';
                $techniques[] = 'Upper blepharoplasty';

                if (in_array('ptosis', $concerns, true)) {
                    $flags[] = 'ptosis_correction_needed';
                    $bullets[] = 'Eyelid ptosis reported. True ptosis requires levator aponeurosis repair — '
                        .'this is distinct from excess skin and changes the surgical approach.';
                    $techniques[] = 'Ptosis repair (levator advancement)';
                }
            }

            if (in_array('lower', $concerns, true)) {
                $bullets[] = 'Lower blepharoplasty: address under-eye bags (fat repositioning) '
                    .'and/or excess lower eyelid skin. Transconjunctival approach preferred '
                    .'to avoid visible external scar when skin removal is not required.';
                $techniques[] = 'Lower blepharoplasty (transconjunctival or transcutaneous)';
            }
        }

        // ── Prior surgery ─────────────────────────────────────────────────────
        if ($this->isTruthy($priorSurgery)) {
            $flags[] = 'revision_blepharoplasty';
            $bullets[] = 'Prior eyelid surgery. Revision blepharoplasty carries higher risk — '
                .'conservative approach required. Assess for lagophthalmos and residual tissue.';
        }

        $harmonyScore = $proportions['overall_harmony'] ?? 50;

        return [
            'procedure' => 'eyelid_surgery',
            'confidence' => 'medium',
            'primary_finding' => 'Blepharoplasty consultation — eyelid rejuvenation.',
            'flags' => array_values(array_unique($flags)),
            'key_points' => array_values(array_unique($bullets)),
            'technique_notes' => array_values(array_unique($techniques)),
            'harmony_score' => $harmonyScore,
        ];
    }

    // ─── Generic fallback ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $proportions
     * @param  array<string, mixed>  $quizAnswers
     * @return array<string, mixed>
     */
    private function genericRecommendations(array $proportions, array $quizAnswers): array
    {
        return [
            'procedure' => 'general',
            'confidence' => 'low',
            'primary_finding' => 'Evaluation completed. Manual review recommended.',
            'flags' => [],
            'key_points' => ['Full AI analysis not yet available for this procedure type. Coordinator should review and schedule consultation.'],
            'technique_notes' => [],
            'harmony_score' => $proportions['overall_harmony'] ?? 50,
        ];
    }

    // ─── Primary finding builders ─────────────────────────────────────────────

    /** @param  array<int, string>|mixed  $concerns */
    private function rhinoplastyPrimaryFinding(mixed $concerns, mixed $priorSurgery): string
    {
        if ($this->isTruthy($priorSurgery)) {
            return 'Revision rhinoplasty patient — prior surgery documented.';
        }

        if (is_array($concerns) && count($concerns) > 0) {
            $labels = [
                'bridge' => 'dorsal hump', 'tip' => 'nasal tip', 'nostrils' => 'alar base',
                'asymmetry' => 'nasal asymmetry', 'projection' => 'nasal projection',
            ];
            $mapped = array_filter(array_map(fn ($c) => $labels[$c] ?? null, $concerns));

            return 'Primary concerns: '.implode(', ', $mapped).'.';
        }

        return 'General rhinoplasty consultation requested.';
    }

    /** @param  array<int, string>|mixed  $concerns */
    private function bblPrimaryFinding(mixed $concerns, mixed $donorAreas): string
    {
        if (is_array($concerns) && count($concerns) > 0) {
            $labels = [
                'volume' => 'volume', 'lift' => 'lift + projection',
                'hourglass' => 'hourglass silhouette', 'proportions' => 'proportions', 'asymmetry' => 'asymmetry',
            ];
            $mapped = array_filter(array_map(fn ($c) => $labels[$c] ?? null, $concerns));

            return 'BBL goals: '.implode(', ', $mapped).'.';
        }

        return 'Brazilian Butt Lift consultation — body contouring goals to be defined at consultation.';
    }

    /** @param  array<int, string>|mixed  $concerns */
    private function lipoPrimaryFinding(mixed $concerns, mixed $skinLaxity): string
    {
        $laxityNote = match ($skinLaxity) {
            'moderate' => ' Skin laxity concern noted.',
            default => '',
        };

        if (is_array($concerns) && count($concerns) > 0) {
            $labels = [
                'upper_abdomen' => 'upper abdomen', 'lower_abdomen' => 'lower abdomen',
                'flanks' => 'flanks', 'back' => 'back', 'inner_thighs' => 'inner thighs', 'outer_thighs' => 'outer thighs',
            ];
            $mapped = array_filter(array_map(fn ($c) => $labels[$c] ?? null, $concerns));

            return 'Lipo 360° target areas: '.implode(', ', $mapped).'.'.$laxityNote;
        }

        return 'Liposuction 360° consultation — target areas to be confirmed at consultation.'.$laxityNote;
    }

    /** @param  array<int, string>|mixed  $concerns */
    private function breastAugPrimaryFinding(mixed $concerns, mixed $priorSurgery, mixed $sizeGoal): string
    {
        if ($this->isTruthy($priorSurgery)) {
            return 'Revision breast augmentation — prior breast surgery documented.';
        }

        $sizeNote = match ($sizeGoal) {
            '1_cup' => '+1 cup goal', '2_cups' => '+2 cups goal',
            '3_plus' => '+3 or more cups goal', 'unsure' => 'size goal TBD at consultation',
            default => '',
        };

        if (is_array($concerns) && count($concerns) > 0) {
            $labels = ['size' => 'size increase', 'shape' => 'shape', 'restore' => 'volume restoration', 'asymmetry' => 'asymmetry', 'lift' => 'ptosis/lift'];
            $mapped = array_filter(array_map(fn ($c) => $labels[$c] ?? null, $concerns));

            return 'Breast augmentation: '.implode(', ', $mapped).($sizeNote ? " ({$sizeNote})" : '').'.';
        }

        return 'Breast augmentation consultation'.($sizeNote ? " ({$sizeNote})" : '').'.';
    }

    /** @param  array<int, string>|mixed  $concerns */
    private function faceliftPrimaryFinding(mixed $concerns, ?int $estimatedAge): string
    {
        $ageNote = $estimatedAge !== null ? " (est. age ~{$estimatedAge})" : '';

        if (is_array($concerns) && count($concerns) > 0) {
            $labels = ['jowls' => 'jowls', 'neck' => 'neck laxity', 'nasolabial' => 'nasolabial folds', 'jaw_definition' => 'jaw definition', 'overall_aging' => 'comprehensive rejuvenation'];
            $mapped = array_filter(array_map(fn ($c) => $labels[$c] ?? null, $concerns));

            return 'Facelift concerns: '.implode(', ', $mapped).$ageNote.'.';
        }

        return 'Facelift consultation requested'.$ageNote.'.';
    }

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private function confidenceFromHarmony(int $harmonyScore): string
    {
        return match (true) {
            $harmonyScore >= 75 => 'high',
            $harmonyScore >= 50 => 'medium',
            default => 'low',
        };
    }

    private function isTruthy(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    private function isFalsy(mixed $value): bool
    {
        return $value === false || $value === 'false' || $value === 0 || $value === '0';
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateBasicRecommendationsJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error' => $e->getMessage(),
        ]);

        Evaluation::withoutGlobalScopes()
            ->where('id', $this->evaluationId)
            ->update(['status' => Evaluation::STATUS_FAILED]);
    }
}
