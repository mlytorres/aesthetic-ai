<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Evaluation;
use App\Services\SecureFileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * External API v1 evaluation resource.
 *
 * Returns the full evaluation payload used by Zapier and CRM integrations.
 * PHI (patient name, email, phone) is included because external API callers
 * that reach this endpoint have already authenticated with a Bearer token
 * scoped for PHI access (phi:read scope enforced at the route level).
 *
 * Photo URLs are 15-minute pre-signed S3 links — callers must download or
 * re-request before the window closes.
 */
class EvaluationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Evaluation $this */
        $svc = app(SecureFileService::class);

        $photosByType = [];
        if ($this->relationLoaded('photos')) {
            foreach ($this->photos as $photo) {
                $photosByType[$photo->type] = [
                    'url' => $svc->getSignedUrl($photo->s3_key),
                    'expires_at' => now()->addMinutes(15)->toIso8601String(),
                    'quality_score' => $photo->quality_score,
                ];
            }
        }

        $quizAnswers = $this->quiz_answers ?? [];

        return [
            'data' => [
                'id' => $this->id,
                'evaluation_token' => $this->secure_token,
                'procedure_interest' => $this->procedure_slug,
                'status' => $this->status,
                'lead_score' => $this->lead_score,
                'priority' => $this->priority,
                'ready_for_call' => $this->isReadyForCall(),
                'ai_analysis_complete' => $this->isAiComplete(),
                'created_at' => $this->created_at->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),

                // Patient PHI — always present for external API; phi:read scope required at route level
                'patient' => $this->whenLoaded('patient', fn () => [
                    'id' => $this->patient->id,
                    'name' => $this->patient->name_encrypted,
                    'email' => $this->patient->email_encrypted,
                    'phone' => $this->patient->phone_encrypted,
                ]),

                // Quiz summary — CRM-friendly flattened fields
                'quiz_summary' => [
                    'concerns' => $quizAnswers['concerns'] ?? null,
                    'prior_surgery' => $quizAnswers['prior_rhinoplasty']
                        ?? $quizAnswers['prior_surgery']
                        ?? null,
                    'breathing_issues' => $quizAnswers['breathing_issues'] ?? null,
                    'skin_thickness' => $quizAnswers['q_skin_thickness']
                        ?? $quizAnswers['skin_thickness']
                        ?? null,
                    'timeline' => $quizAnswers['timeline'] ?? null,
                    'budget_range' => $quizAnswers['budget_range'] ?? null,
                ],

                // AI analysis — null until pipeline completes
                'ai_analysis' => $this->when(
                    $this->isAiComplete(),
                    fn () => $this->analysis_data
                ),

                // Pre-signed photo URLs keyed by type (front, left_profile, right_profile)
                'photos' => $photosByType ?: null,
            ],
        ];
    }
}
