<?php

declare(strict_types=1);

namespace App\Jobs\AI;

use App\Concerns\ResolvesJobTenant;
use App\Models\Evaluation;
use App\Models\Photo;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job 2/4 in the AI pipeline.
 *
 * Extracts facial landmarks from the FRONT photo using AWS Rekognition DetectFaces.
 * Stores normalised landmark coordinates (0.0–1.0 relative to image dimensions)
 * in evaluation.analysis_data.landmarks.
 *
 * Rekognition returns 27 landmark types including:
 *   eyeLeft, eyeRight, nose, mouthLeft, mouthRight, leftEyeBrowLeft,
 *   leftEyeBrowRight, rightEyeBrowLeft, rightEyeBrowRight, leftPupil,
 *   rightPupil, upperJawlineLeft, midJawlineLeft, chinBottom,
 *   midJawlineRight, upperJawlineRight + more.
 *
 * Only runs on the 'front' photo — profiles provide supplementary data
 * for proportion calculations but landmark extraction targets the frontal view.
 *
 * SIMULATION MODE (FEATURE_AI_VISION=false):
 *   Generates an idealised landmark set approximating the "golden ratio"
 *   facial geometry so CalculateProportionsJob has something to work with.
 */
class ExtractFacialLandmarksJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ResolvesJobTenant;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly string $evaluationId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->setTenantFromEvaluation($this->evaluationId);

        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::findOrFail($this->evaluationId);

        $frontPhoto = Photo::where('evaluation_id', $this->evaluationId)
            ->where('type', Photo::TYPE_FRONT)
            ->where('analysis_status', Photo::ANALYSIS_COMPLETE)
            ->first();

        if (!$frontPhoto) {
            Log::warning('ExtractFacialLandmarksJob: no valid front photo', [
                'evaluation_id' => $this->evaluationId,
            ]);
            // Non-fatal — continue pipeline with partial data
            return;
        }

        $landmarks = config('features.ai_vision', false)
            ? $this->runRekognition($frontPhoto)
            : $this->simulateLandmarks();

        // Merge into analysis_data — preserving any existing keys (quality scores, etc.)
        $existing = $evaluation->analysis_data ?? [];
        $evaluation->update([
            'analysis_data' => array_merge($existing, [
                'landmarks'           => $landmarks,
                'landmarks_photo_id'  => $frontPhoto->id,
                'landmarks_extracted_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Real Rekognition call.
     *
     * @return array<string, array{x: float, y: float}>
     */
    private function runRekognition(Photo $frontPhoto): array
    {
        try {
            $client = new RekognitionClient([
                'version' => 'latest',
                'region'  => config('services.rekognition.region', 'us-east-1'),
            ]);

            $result = $client->detectFaces([
                'Image' => [
                    'S3Object' => [
                        'Bucket' => config('filesystems.disks.s3.bucket'),
                        'Name'   => $frontPhoto->s3_key,
                    ],
                ],
                'Attributes' => ['ALL'],
            ]);

            $faces = $result->get('FaceDetails');

            if (empty($faces)) {
                return [];
            }

            $face = $faces[0];

            // Normalise Rekognition landmark format → our internal format
            $landmarks = [];
            foreach ($face['Landmarks'] ?? [] as $lm) {
                $landmarks[$lm['Type']] = [
                    'x' => round((float) $lm['X'], 4),
                    'y' => round((float) $lm['Y'], 4),
                ];
            }

            // Store face attributes alongside landmarks for downstream jobs
            $landmarks['_face_attributes'] = $this->extractFaceAttributes($face);

            return $landmarks;
        } catch (\Throwable $e) {
            Log::error('Rekognition landmark extraction failed', [
                'evaluation_id' => $this->evaluationId,
                'error'         => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Extract structured face attributes from a Rekognition FaceDetail object.
     *
     * Captures age estimate, photo quality, pose angles, and key facial attributes
     * that inform procedure-specific recommendations (especially facelift age context).
     *
     * @param  array<string, mixed>  $face  A single FaceDetails entry from Rekognition
     * @return array<string, mixed>
     */
    private function extractFaceAttributes(array $face): array
    {
        $attrs = [];

        // Age range estimate (Low/High bounds in years)
        if (isset($face['AgeRange'])) {
            $low = (int)($face['AgeRange']['Low'] ?? 0);
            $high = (int)($face['AgeRange']['High'] ?? 0);
            $attrs['age_range'] = ['low' => $low, 'high' => $high, 'midpoint' => (int)round(($low + $high) / 2)];
        }

        // Photo quality (Brightness 0–100, Sharpness 0–100)
        if (isset($face['Quality'])) {
            $attrs['photo_quality'] = [
                'brightness' => round((float)($face['Quality']['Brightness'] ?? 0), 1),
                'sharpness'  => round((float)($face['Quality']['Sharpness'] ?? 0), 1),
            ];
        }

        // Pose — yaw/pitch/roll for detecting profile vs frontal photo
        if (isset($face['Pose'])) {
            $attrs['pose'] = [
                'yaw'   => round((float)($face['Pose']['Yaw'] ?? 0), 1),
                'pitch' => round((float)($face['Pose']['Pitch'] ?? 0), 1),
                'roll'  => round((float)($face['Pose']['Roll'] ?? 0), 1),
            ];
        }

        // Confidence that a face was detected
        $attrs['confidence'] = round((float)($face['Confidence'] ?? 0), 1);

        return $attrs;
    }

    /**
     * Simulation mode — generates a landmark set for an average face.
     * Coordinates are normalised (0.0–1.0) relative to image dimensions.
     * Modelled after Rekognition's output format and typical facial geometry.
     *
     * @return array<string, array{x: float, y: float}>
     */
    private function simulateLandmarks(): array
    {
        // Add small random variance to simulate natural variation between patients
        $jitter = fn (float $base, float $range = 0.01): float =>
            round($base + (mt_rand(-100, 100) / 100) * $range, 4);

        $ageLow  = mt_rand(28, 42);
        $ageHigh = $ageLow + mt_rand(4, 8);

        return [
            '_face_attributes' => [
                'age_range'    => ['low' => $ageLow, 'high' => $ageHigh, 'midpoint' => (int)round(($ageLow + $ageHigh) / 2)],
                'photo_quality' => ['brightness' => round(mt_rand(60, 90) + mt_rand(0, 9) / 10, 1), 'sharpness' => round(mt_rand(55, 85) + mt_rand(0, 9) / 10, 1)],
                'pose'         => ['yaw' => round(mt_rand(-8, 8) / 10, 1), 'pitch' => round(mt_rand(-5, 5) / 10, 1), 'roll' => round(mt_rand(-3, 3) / 10, 1)],
                'confidence'   => round(mt_rand(970, 999) / 10, 1),
            ],
            'eyeLeft'              => ['x' => $jitter(0.37),  'y' => $jitter(0.38)],
            'eyeRight'             => ['x' => $jitter(0.63),  'y' => $jitter(0.38)],
            'nose'                 => ['x' => $jitter(0.50),  'y' => $jitter(0.55)],
            'mouthLeft'            => ['x' => $jitter(0.40),  'y' => $jitter(0.70)],
            'mouthRight'           => ['x' => $jitter(0.60),  'y' => $jitter(0.70)],
            'leftEyeBrowLeft'      => ['x' => $jitter(0.28),  'y' => $jitter(0.31)],
            'leftEyeBrowRight'     => ['x' => $jitter(0.42),  'y' => $jitter(0.30)],
            'rightEyeBrowLeft'     => ['x' => $jitter(0.58),  'y' => $jitter(0.30)],
            'rightEyeBrowRight'    => ['x' => $jitter(0.72),  'y' => $jitter(0.31)],
            'leftPupil'            => ['x' => $jitter(0.37),  'y' => $jitter(0.38)],
            'rightPupil'           => ['x' => $jitter(0.63),  'y' => $jitter(0.38)],
            'upperJawlineLeft'     => ['x' => $jitter(0.22),  'y' => $jitter(0.45)],
            'midJawlineLeft'       => ['x' => $jitter(0.26),  'y' => $jitter(0.68)],
            'chinBottom'           => ['x' => $jitter(0.50),  'y' => $jitter(0.88)],
            'midJawlineRight'      => ['x' => $jitter(0.74),  'y' => $jitter(0.68)],
            'upperJawlineRight'    => ['x' => $jitter(0.78),  'y' => $jitter(0.45)],
            'noseLeft'             => ['x' => $jitter(0.44),  'y' => $jitter(0.62)],
            'noseRight'            => ['x' => $jitter(0.56),  'y' => $jitter(0.62)],
            'mouthUp'              => ['x' => $jitter(0.50),  'y' => $jitter(0.67)],
            'mouthDown'            => ['x' => $jitter(0.50),  'y' => $jitter(0.73)],
            'leftEyeLeft'          => ['x' => $jitter(0.33),  'y' => $jitter(0.38)],
            'leftEyeRight'         => ['x' => $jitter(0.41),  'y' => $jitter(0.38)],
            'leftEyeUp'            => ['x' => $jitter(0.37),  'y' => $jitter(0.36)],
            'leftEyeDown'          => ['x' => $jitter(0.37),  'y' => $jitter(0.40)],
            'rightEyeLeft'         => ['x' => $jitter(0.59),  'y' => $jitter(0.38)],
            'rightEyeRight'        => ['x' => $jitter(0.67),  'y' => $jitter(0.38)],
            'rightEyeUp'           => ['x' => $jitter(0.63),  'y' => $jitter(0.36)],
            'rightEyeDown'         => ['x' => $jitter(0.63),  'y' => $jitter(0.40)],
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ExtractFacialLandmarksJob failed', [
            'evaluation_id' => $this->evaluationId,
            'error'         => $e->getMessage(),
        ]);
    }
}
