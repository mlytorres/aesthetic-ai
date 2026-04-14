<?php

declare(strict_types=1);

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the public-facing Patient Portal.
 *
 * Gated by the evaluation's secure_token — no authentication required.
 * Acts as a hub for the patient to view their status, access their
 * Beauty Roadmap (PDF) and AI Simulation, and find contact details.
 */
class PatientPortalController extends Controller
{
    public function show(string $token): Response
    {
        // Use withoutGlobalScopes since the patient accesses this from an
        // unauthenticated external context. We resolve by globally unique token.
        $evaluation = Evaluation::withoutGlobalScopes()
            ->with(['tenant', 'patient', 'procedure'])
            ->where('secure_token', $token)
            ->firstOrFail();

        return Inertia::render('patient/portal', [
            'evaluation' => $evaluation,
            'status' => $evaluation->status,
            'isComplete' => $evaluation->isAiComplete() || in_array($evaluation->status, [
                Evaluation::STATUS_CONTACTED, 
                Evaluation::STATUS_BOOKED
            ]),
            'tenant' => $evaluation->tenant,
        ]);
    }
}
