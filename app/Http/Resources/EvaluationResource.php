<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evaluation;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Evaluation $this */
        return [
            'id' => $this->id,
            'procedure_slug' => $this->procedure_slug,
            'status' => $this->status,
            'lead_score' => $this->lead_score,
            'priority' => $this->priority,
            'secure_token' => $this->secure_token,
            'coordinator_notes' => $this->coordinator_notes,
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            // Patient — partial PHI (decrypted for authorised staff)
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => $this->patient->name_encrypted,   // cast decrypts automatically
                'email' => $this->patient->email_encrypted,
                'phone' => $this->patient->phone_encrypted,
            ]),

            // Photos — return signed URLs, never raw S3 keys
            'photos' => $this->whenLoaded('photos', function () {
                $svc = app(SecureFileService::class);

                return $this->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'type' => $photo->type,
                    'quality_score' => $photo->quality_score,
                    'analysis_status' => $photo->analysis_status,
                    'signed_url' => $svc->getSignedUrl($photo->s3_key),
                ]);
            }),

            'photos_count' => $this->whenCounted('photos'),
            'quiz_answers' => $this->when($this->relationLoaded('patient'), fn () => $this->quiz_answers),
            'analysis_data' => $this->when($this->analysis_data !== [], fn () => $this->analysis_data),

            // Simulation
            'simulation_status' => $this->simulation_status,
            'simulation_data' => $this->simulation_data,
            'simulation_requested_at' => $this->simulation_requested_at?->toIso8601String(),
        ];
    }
}
