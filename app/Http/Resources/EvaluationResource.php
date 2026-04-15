<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evaluation;
use App\Models\User;
use App\Services\LeadScoringService;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Evaluation $this */
        $user = $request->user();
        $canViewPhi = $user instanceof User && $user->canViewPhi();

        return [
            'id' => $this->id,
            'procedure_slug' => $this->procedure_slug,
            'status' => $this->status,
            'lead_score' => $this->lead_score,
            'priority' => $this->priority,
            'score_breakdown' => $this->when(
                ! empty($this->analysis_data) && ! empty($this->quiz_answers),
                fn () => app(LeadScoringService::class)->breakdown(
                    (array) ($this->analysis_data['proportions'] ?? []),
                    (array) $this->quiz_answers,
                )
            ),
            'secure_token' => $this->secure_token,
            'coordinator_notes' => $this->coordinator_notes,
            'follow_up_at' => $this->follow_up_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),

            // Patient PHI — name, email, phone only for clinical actors (owner, admin, coordinator).
            // Surgeons and Viewers receive only the patient ID for cross-referencing.
            'patient' => $this->whenLoaded('patient', fn () => $canViewPhi
                ? [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name_encrypted,   // cast decrypts automatically
                    'email' => $this->patient->email_encrypted,
                    'phone' => $this->patient->phone_encrypted,
                ]
                : [
                    'id' => $this->patient->id,
                    'name' => null,
                    'email' => null,
                    'phone' => null,
                ]
            ),

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

            // Quiz answers and analysis — available to all roles with access to this evaluation.
            // PHI may appear in free-text quiz fields, so quiz_answers is restricted to PHI-capable roles.
            'quiz_answers' => $this->when(
                $this->relationLoaded('patient') && $canViewPhi,
                fn () => $this->quiz_answers
            ),
            'analysis_data' => $this->when($this->analysis_data !== [], fn () => $this->analysis_data),

            // Simulation
            'simulation_status' => $this->simulation_status,
            'simulation_data' => $this->simulation_data,
            'simulation_requested_at' => $this->simulation_requested_at?->toIso8601String(),
        ];
    }
}
