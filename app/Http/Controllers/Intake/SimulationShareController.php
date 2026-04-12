<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Serves the patient-facing AI simulation result page.
 *
 * Gated by the evaluation's secure_token — no authentication required.
 * Returns 404 if the token is invalid or the simulation is not yet complete.
 *
 * PHI: displays the simulation image only — no contact info, clinical notes, or analysis data.
 */
class SimulationShareController extends Controller
{
    /**
     * Render the public simulation share page.
     *
     * Token acts as the authorization credential — same pattern as the patient report.
     * The coordinator copies this URL from the dashboard and sends it to the patient.
     */
    public function show(string $token): InertiaResponse|Response
    {
        // withoutGlobalScopes: this is a public, token-gated route — no authenticated
        // tenant context is available. The secure_token is globally unique and acts
        // as the authorization credential (same pattern as patient report/intake).
        $evaluation = Evaluation::withoutGlobalScopes()
            ->with('tenant')
            ->where('secure_token', $token)
            ->where('simulation_status', 'complete')
            ->firstOrFail();

        $simulationUrl = $this->resolveSimulationUrl($evaluation);

        return Inertia::render('intake/simulation-share', [
            'procedure' => $evaluation->procedure_slug,
            'simulationUrl' => $simulationUrl,
            'tenantName' => $evaluation->tenant?->name,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveSimulationUrl(Evaluation $evaluation): ?string
    {
        $data = $evaluation->simulation_data;

        if (! isset($data['simulation_s3_key']) || ! is_string($data['simulation_s3_key'])) {
            return null;
        }

        $s3Key = $data['simulation_s3_key'];

        try {
            $disk = config('features.ai_vision', false) ? 's3' : 'local';

            if ($disk === 'local') {
                return null;
            }

            return Storage::disk('s3')->temporaryUrl(
                $s3Key,
                now()->addMinutes(60),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
