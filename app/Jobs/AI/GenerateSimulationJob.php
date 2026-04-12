<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Models\Evaluation;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files;
use Laravel\Ai\Image;

/**
 * Generates an AI before/after simulation image for an evaluation.
 *
 * Flow:
 *   1. Resolve primary front photo for the evaluation
 *   2. Build a procedure-specific prompt using body proportion data
 *   3. Call OpenAI image generation via the Laravel AI SDK (gpt-image-1)
 *   4. Store the generated PNG to S3 / local disk
 *   5. Update evaluation simulation_status to 'complete'
 *
 * When FEATURE_AI_VISION=false (simulation mode):
 *   Skips the API call and stores a placeholder result so the full UI
 *   flow works without an OpenAI key in local development.
 */
class GenerateSimulationJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, ResolvesJobTenant, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(private readonly string $evaluationId) {}

    public function handle(): void
    {
        $this->setTenantFromEvaluation($this->evaluationId);

        $evaluation = Evaluation::withoutGlobalScopes()->findOrFail($this->evaluationId);

        $evaluation->update(['simulation_status' => 'processing']);

        try {
            $result = config('features.ai_vision', false)
                ? $this->runRealSimulation($evaluation)
                : $this->runSimulatedSimulation($evaluation);

            $evaluation->update([
                'simulation_status' => 'complete',
                'simulation_data' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateSimulationJob failed', [
                'evaluation_id' => $this->evaluationId,
                'error' => $e->getMessage(),
            ]);

            $evaluation->update(['simulation_status' => 'failed']);

            throw $e;
        }
    }

    // ─── Real simulation via Laravel AI SDK ──────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function runRealSimulation(Evaluation $evaluation): array
    {
        $prompt = $this->buildPrompt($evaluation);
        $attachment = $this->resolvePhotoAttachment($evaluation);

        // Build the image request — attach the patient photo when available so
        // the model performs an image edit; otherwise generate from prompt alone.
        // HIPAA: image bytes are never logged; raw S3 key never exposed.
        $imageRequest = Image::of($prompt)->quality('high')->timeout(180);

        if ($attachment !== null) {
            // Files\Image::fromStorage() streams the photo from S3 (prod) or
            // the local disk (dev) directly to the SDK — no temp file needed.
            $imageRequest = $imageRequest->attachments([$attachment]);
        }

        // Explicitly request gpt-image-1 — the SDK default may differ.
        $imageResponse = $imageRequest->generate(model: 'gpt-image-1');

        // firstImage()->image is already the base64-encoded PNG from the API.
        $s3Key = $this->storeSimulationImage($evaluation, $imageResponse->firstImage()->image);

        return [
            'mode' => 'openai',
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'simulation_s3_key' => $s3Key,
            'has_source_photo' => $attachment !== null,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ─── Simulation mode (no API key required) ────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function runSimulatedSimulation(Evaluation $evaluation): array
    {
        $prompt = $this->buildPrompt($evaluation);

        Log::info('GenerateSimulationJob: AI Vision disabled — returning placeholder simulation', [
            'evaluation_id' => $this->evaluationId,
            'procedure' => $evaluation->procedure_slug,
        ]);

        return [
            'mode' => 'simulated',
            'model' => null,
            'prompt' => $prompt,
            'simulation_s3_key' => null,
            'placeholder' => true,
            'placeholder_message' => 'Simulation image generation requires FEATURE_AI_VISION=true and a valid OPENAI_API_KEY.',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ─── Prompt building ──────────────────────────────────────────────────────

    private function buildPrompt(Evaluation $evaluation): string
    {
        $data = $evaluation->analysis_data ?? [];

        return match ($evaluation->procedure_slug) {
            // ── Body sculpting ────────────────────────────────────────────────
            'bbl' => $this->bblPrompt($data),
            'skinny_bbl' => $this->skinnyBblPrompt($data),
            'reverse_bbl' => $this->reverseBblPrompt($data),
            'lipo_360' => $this->lipo360Prompt($data),
            'liposuction' => $this->liposuctionPrompt($data),

            // ── Abdomen & torso ───────────────────────────────────────────────
            'tummy_tuck' => $this->tummyTuckPrompt($data),
            'mommy_makeover' => $this->mommyMakeoverPrompt($data),
            'abdominal_etching' => $this->abdominalEtchingPrompt($data),
            'j_plasma' => $this->jPlasmaPrompt($data),

            // ── Breast ────────────────────────────────────────────────────────
            'breast_augmentation' => $this->breastAugPrompt($data),
            'breast_lift' => $this->breastLiftPrompt($data),
            'breast_reduction' => $this->breastReductionPrompt($data),
            'gynecomastia' => $this->gynecomastiaPrompt($data),

            // ── Arms, back & extremities ──────────────────────────────────────
            'arm_lipo_lift' => $this->armLipoLiftPrompt($data),
            'arm_thigh_lift' => $this->armThighLiftPrompt($data),
            'back_liposuction_lift' => $this->backLipoLiftPrompt($data),
            'axillary_liposuction' => $this->axillaryLipoPrompt($data),

            // ── Face & neck ───────────────────────────────────────────────────
            'rhinoplasty' => $this->rhinoplastyPrompt($data),
            'facelift' => $this->faceliftPrompt($data),
            'face_and_neck_lift' => $this->faceNeckLiftPrompt($data),
            'chin_lipo' => $this->chinLipoPrompt($data),
            'eyelid_surgery' => $this->eyelidSurgeryPrompt($data),
            'bichectomy' => $this->bichectomyPrompt($data),
            'otoplasty' => $this->otoplastyPrompt($data),

            default => $this->genericPrompt($evaluation->procedure_slug),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bblPrompt(array $data): string
    {
        $proportions = $data['body_proportions'] ?? [];
        $whr = $proportions['waist_hip_ratio']['ratio'] ?? 0.75;
        $gluteal = $proportions['gluteal_projection']['score'] ?? 50;

        return sprintf(
            'Professional cosmetic surgery simulation. Enhance the gluteal region for a natural BBL result. '
            .'Current waist-to-hip ratio: %.2f (target 0.70). Gluteal projection score: %d/100. '
            .'Apply subtle volume to the buttocks and light waist definition. '
            .'Photorealistic skin texture, natural lighting, anatomical proportions. '
            .'Clinical, tasteful depiction for medical consultation.',
            $whr,
            $gluteal,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lipo360Prompt(array $data): string
    {
        $proportions = $data['body_proportions'] ?? [];
        $abdominalValue = $proportions['abdominal_projection']['value'] ?? 0.12;

        return sprintf(
            'Professional cosmetic surgery simulation for Lipo 360. '
            .'Circumferential abdominal contouring — reduce anterior projection (current %.2f) '
            .'and define the waistline uniformly. Natural skin texture, realistic shadows. '
            .'Clinical, tasteful depiction for medical consultation.',
            $abdominalValue,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function breastAugPrompt(array $data): string
    {
        $proportions = $data['body_proportions'] ?? [];
        $shoulderWaist = $proportions['shoulder_waist_ratio']['ratio'] ?? 1.40;

        return sprintf(
            'Professional cosmetic surgery simulation for breast augmentation. '
            .'Natural, proportionate enhancement for this frame (shoulder-to-waist ratio: %.2f). '
            .'Photorealistic skin texture, natural shape, anatomical symmetry. '
            .'Clinical, tasteful depiction for medical consultation.',
            $shoulderWaist,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function rhinoplastyPrompt(array $data): string
    {
        $proportions = $data['facial_proportions'] ?? [];
        $noseScore = $proportions['nose']['score'] ?? 65;

        return sprintf(
            'Professional cosmetic surgery simulation for rhinoplasty. '
            .'Refine nasal shape for facial harmony (current nose score: %d/100). '
            .'Subtle dorsal reduction, improved tip definition, balanced proportions. '
            .'Photorealistic, clinical depiction for medical consultation.',
            $noseScore,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function faceliftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for facelift. '
            .'Subtle rejuvenation: improved jawline, reduced jowling, smoothed nasolabial folds. '
            .'Natural expressions and skin texture preserved. '
            .'Photorealistic, clinical depiction for medical consultation.';
    }

    // ─── New body procedure prompts ───────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    private function skinnyBblPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for Skinny BBL. '
            .'Subtle gluteal enhancement with minimal donor fat — sculpted, athletic result. '
            .'Preserve lean physique; improve projection without adding bulk. '
            .'Photorealistic skin texture, natural lighting. '
            .'Clinical, tasteful depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function reverseBblPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for Reverse BBL. '
            .'Fat transfer from gluteal region to enhance upper body (breast area and/or flanks). '
            .'Reduce posterior projection while improving anterior contour. '
            .'Natural, proportionate result. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function liposuctionPrompt(array $data): string
    {
        $proportions = $data['body_proportions'] ?? [];
        $abdominal = $proportions['abdominal_projection']['value'] ?? 0.12;

        return sprintf(
            'Professional cosmetic surgery simulation for liposuction. '
            .'Targeted fat reduction and body contouring (abdominal projection index: %.2f). '
            .'Smooth, even contour with natural skin texture and realistic shadows. '
            .'Clinical, tasteful depiction for medical consultation.',
            $abdominal,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tummyTuckPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for tummy tuck (abdominoplasty). '
            .'Flatten and firm the abdomen: remove excess skin, repair diastasis recti, '
            .'define waistline. Natural scar placement concealed by underwear line. '
            .'Photorealistic result showing smooth, toned abdominal profile. '
            .'Clinical, tasteful depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mommyMakeoverPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for Mommy Makeover. '
            .'Combined procedure: restore breast volume and shape, flatten and firm abdomen, '
            .'contour flanks and waist. Show post-pregnancy body restoration — '
            .'natural, proportionate result reflecting full-body rejuvenation. '
            .'Photorealistic, clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function abdominalEtchingPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for abdominal etching / hi-definition liposuction. '
            .'Define abdominal musculature by selective superficial fat removal along muscle borders. '
            .'Athletic, sculpted appearance with natural skin texture. '
            .'Clinical, tasteful depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jPlasmaPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for J Plasma (Renuvion) skin tightening. '
            .'Show improved skin laxity and retraction in the treated areas — '
            .'smoother, tighter abdominal or body skin without implants or fat removal. '
            .'Natural result, photorealistic skin texture. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function breastLiftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for breast lift (mastopexy). '
            .'Elevate breast position, reshape breast mound, reduce areolar size if indicated. '
            .'Perky, youthful contour preserved with natural volume. '
            .'Scar lines tastefully implied. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function breastReductionPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for breast reduction (reduction mammaplasty). '
            .'Reduce breast volume and elevate position for improved posture and comfort. '
            .'Proportionate, natural result. Clinical, tasteful depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function gynecomastiaPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for gynecomastia correction. '
            .'Flatten male chest: reduce glandular tissue and excess fat for a masculine, '
            .'toned contour. Natural skin texture, realistic lighting. '
            .'Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function armLipoLiftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for arm liposuction and lift (brachioplasty). '
            .'Reduce upper arm fullness and tighten loose skin — lean, toned arm profile. '
            .'Natural result. Clinical, tasteful depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function armThighLiftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for arm and thigh lift. '
            .'Tighten and contour upper arms and inner thighs — smooth skin, improved tone. '
            .'Natural, proportionate result. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function backLipoLiftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for back liposuction and lift. '
            .'Reduce back fat rolls, smooth bra-line area, define posterior waistline. '
            .'Natural result, photorealistic skin texture. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function axillaryLipoPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for axillary liposuction. '
            .'Remove excess fat from the armpit and lateral chest region — '
            .'clean, streamlined contour without visible transition. '
            .'Clinical, tasteful depiction for medical consultation.';
    }

    // ─── New face / neck procedure prompts ───────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     */
    private function faceNeckLiftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for face and neck lift. '
            .'Comprehensive rejuvenation: improved jawline definition, reduced neck laxity, '
            .'smoothed jowls, refined cervical angle. Natural expressions preserved. '
            .'Photorealistic, clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function chinLipoPrompt(array $data): string
    {
        $proportions = $data['facial_proportions'] ?? [];
        $chinScore = $proportions['chin']['score'] ?? 60;

        return sprintf(
            'Professional cosmetic surgery simulation for chin liposuction / submental fat removal. '
            .'Reduce submental fullness (chin harmony score: %d/100) and define cervicomental angle. '
            .'Clean jawline, natural neck contour. Clinical, tasteful depiction for medical consultation.',
            $chinScore,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function eyelidSurgeryPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for eyelid surgery (blepharoplasty). '
            .'Remove excess upper eyelid skin and/or lower eyelid bags — '
            .'bright, refreshed, wide-awake appearance. Natural crease preserved. '
            .'Subtle, photorealistic result. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bichectomyPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for bichectomy (buccal fat removal). '
            .'Slim and define the mid-face by reducing buccal fat pad prominence — '
            .'subtle cheekbone definition, more sculpted facial contour. '
            .'Natural result. Clinical depiction for medical consultation.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function otoplastyPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for otoplasty (ear pinning). '
            .'Reshape and reposition prominent ears closer to the head — '
            .'natural ear contour, balanced facial profile. '
            .'Subtle, photorealistic result. Clinical depiction for medical consultation.';
    }

    private function genericPrompt(string $procedure): string
    {
        return sprintf(
            'Professional cosmetic surgery simulation for %s. '
            .'Natural, photorealistic post-operative result. '
            .'Clinical depiction for medical consultation.',
            str_replace('_', ' ', $procedure),
        );
    }

    // ─── Storage helpers ──────────────────────────────────────────────────────

    private function storeSimulationImage(Evaluation $evaluation, string $b64): string
    {
        $key = "{$evaluation->tenant_id}/{$evaluation->id}/simulation_".now()->format('YmdHis').'.png';
        $disk = config('features.ai_vision', false) ? 's3' : 'local';

        Storage::disk($disk)->put($key, base64_decode($b64, true), 'private');

        return $key;
    }

    /**
     * Resolve the front photo as a Files\Image attachment for the SDK.
     *
     * Uses Files\Image::fromStorage() so the SDK reads bytes directly from the
     * configured disk (S3 in production, local disk in dev) without writing a
     * temp file. The s3_key is auto-decrypted by the encrypted cast.
     *
     * Returns null when no front photo exists or (in dev) when the file has not
     * yet been written to the local disk.
     */
    private function resolvePhotoAttachment(Evaluation $evaluation): ?Files\Image
    {
        $photo = $evaluation->photos()->where('type', 'front')->first();

        if ($photo === null) {
            return null;
        }

        $disk = config('features.ai_vision', false) ? 's3' : 'local';
        $key = $photo->s3_key; // encrypted cast auto-decrypts on read

        // In dev mode the photo may not exist on local disk yet — skip gracefully.
        if ($disk === 'local' && ! Storage::disk('local')->exists($key)) {
            return null;
        }

        return Files\Image::fromStorage($key, $disk);
    }
}
