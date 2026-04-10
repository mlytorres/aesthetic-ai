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
            'rhinoplasty'         => $this->rhinoplastyRecommendations($proportions, $quizAnswers),
            'bbl'                 => $this->bblRecommendations($proportions, $quizAnswers),
            'lipo_360'            => $this->lipo360Recommendations($proportions, $quizAnswers),
            'breast_augmentation' => $this->breastAugRecommendations($proportions, $quizAnswers),
            'facelift'            => $this->faceliftRecommendations($proportions, $quizAnswers, $faceAttrs),
            default               => $this->genericRecommendations($proportions, $quizAnswers),
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
