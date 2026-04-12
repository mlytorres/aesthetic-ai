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
                'simulation_data'   => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateSimulationJob failed', [
                'evaluation_id' => $this->evaluationId,
                'error'         => $e->getMessage(),
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
        $prompt    = $this->buildPrompt($evaluation);
        $photoPath = $this->resolvePhotoPath($evaluation);

        // Build the image request — attach the patient photo when available so
        // the model performs an image edit; otherwise generate from prompt alone.
        // HIPAA: image bytes are never logged; raw S3 path never exposed.
        $imageRequest = Image::of($prompt)->timeout(180);

        if ($photoPath !== null) {
            // Files\Image::fromPath() uploads the source photo inline for editing.
            $imageRequest = $imageRequest->attachments([
                Files\Image::fromPath($photoPath),
            ]);
        }

        $imageResponse = $imageRequest->generate();

        // The SDK returns raw PNG bytes via (string) cast.
        $s3Key = $this->storeSimulationImage($evaluation, base64_encode((string) $imageResponse));

        return [
            'mode'              => 'openai',
            'model'             => 'gpt-image-1',
            'prompt'            => $prompt,
            'simulation_s3_key' => $s3Key,
            'has_source_photo'  => $photoPath !== null,
            'generated_at'      => now()->toIso8601String(),
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
            'procedure'     => $evaluation->procedure_slug,
        ]);

        return [
            'mode'                => 'simulated',
            'model'               => null,
            'prompt'              => $prompt,
            'simulation_s3_key'   => null,
            'placeholder'         => true,
            'placeholder_message' => 'Simulation image generation requires FEATURE_AI_VISION=true and a valid OPENAI_API_KEY.',
            'generated_at'        => now()->toIso8601String(),
        ];
    }

    // ─── Prompt building ──────────────────────────────────────────────────────

    private function buildPrompt(Evaluation $evaluation): string
    {
        $data = $evaluation->analysis_data ?? [];

        return match ($evaluation->procedure_slug) {
            'bbl'                  => $this->bblPrompt($data),
            'lipo_360'             => $this->lipo360Prompt($data),
            'breast_augmentation'  => $this->breastAugPrompt($data),
            'rhinoplasty'          => $this->rhinoplastyPrompt($data),
            'facelift'             => $this->faceliftPrompt($data),
            default                => $this->genericPrompt($evaluation->procedure_slug),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function bblPrompt(array $data): string
    {
        $proportions = $data['body_proportions'] ?? [];
        $whr         = $proportions['waist_hip_ratio']['ratio'] ?? 0.75;
        $gluteal     = $proportions['gluteal_projection']['score'] ?? 50;

        return sprintf(
            'Professional cosmetic surgery simulation. Enhance the gluteal region for a natural BBL result. '
            . 'Current waist-to-hip ratio: %.2f (target 0.70). Gluteal projection score: %d/100. '
            . 'Apply subtle volume to the buttocks and light waist definition. '
            . 'Photorealistic skin texture, natural lighting, anatomical proportions. '
            . 'Clinical, tasteful depiction for medical consultation.',
            $whr,
            $gluteal,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function lipo360Prompt(array $data): string
    {
        $proportions    = $data['body_proportions'] ?? [];
        $abdominalValue = $proportions['abdominal_projection']['value'] ?? 0.12;

        return sprintf(
            'Professional cosmetic surgery simulation for Lipo 360. '
            . 'Circumferential abdominal contouring — reduce anterior projection (current %.2f) '
            . 'and define the waistline uniformly. Natural skin texture, realistic shadows. '
            . 'Clinical, tasteful depiction for medical consultation.',
            $abdominalValue,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function breastAugPrompt(array $data): string
    {
        $proportions   = $data['body_proportions'] ?? [];
        $shoulderWaist = $proportions['shoulder_waist_ratio']['ratio'] ?? 1.40;

        return sprintf(
            'Professional cosmetic surgery simulation for breast augmentation. '
            . 'Natural, proportionate enhancement for this frame (shoulder-to-waist ratio: %.2f). '
            . 'Photorealistic skin texture, natural shape, anatomical symmetry. '
            . 'Clinical, tasteful depiction for medical consultation.',
            $shoulderWaist,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function rhinoplastyPrompt(array $data): string
    {
        $proportions = $data['facial_proportions'] ?? [];
        $noseScore   = $proportions['nose']['score'] ?? 65;

        return sprintf(
            'Professional cosmetic surgery simulation for rhinoplasty. '
            . 'Refine nasal shape for facial harmony (current nose score: %d/100). '
            . 'Subtle dorsal reduction, improved tip definition, balanced proportions. '
            . 'Photorealistic, clinical depiction for medical consultation.',
            $noseScore,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function faceliftPrompt(array $data): string
    {
        return 'Professional cosmetic surgery simulation for facelift. '
            . 'Subtle rejuvenation: improved jawline, reduced jowling, smoothed nasolabial folds. '
            . 'Natural expressions and skin texture preserved. '
            . 'Photorealistic, clinical depiction for medical consultation.';
    }

    private function genericPrompt(string $procedure): string
    {
        return sprintf(
            'Professional cosmetic surgery simulation for %s. '
            . 'Natural, photorealistic post-operative result. '
            . 'Clinical depiction for medical consultation.',
            str_replace('_', ' ', $procedure),
        );
    }

    // ─── Storage helpers ──────────────────────────────────────────────────────

    private function storeSimulationImage(Evaluation $evaluation, string $b64): string
    {
        $key  = "{$evaluation->tenant_id}/{$evaluation->id}/simulation_" . now()->format('YmdHis') . '.png';
        $disk = config('features.ai_vision', false) ? 's3' : 'local';

        Storage::disk($disk)->put($key, base64_decode($b64, true), 'private');

        return $key;
    }

    private function resolvePhotoPath(Evaluation $evaluation): ?string
    {
        $photo = $evaluation->photos()->where('type', 'front')->first();

        if ($photo === null) {
            return null;
        }

        if (config('features.ai_vision', false)) {
            return null; // S3 download handled separately in Sprint 8
        }

        $path = storage_path('app/private/' . decrypt($photo->s3_key));

        return file_exists($path) ? $path : null;
    }
}
