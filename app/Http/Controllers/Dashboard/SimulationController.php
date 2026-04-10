<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\AI\GenerateSimulationJob;
use App\Models\Evaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Handles AI before/after simulation requests for individual evaluations.
 *
 * Routes:
 *   POST   /evaluations/{evaluation}/simulation   — request a new simulation
 *   GET    /evaluations/{evaluation}/simulation   — poll current simulation status
 *
 * The simulation job runs asynchronously on the 'ai' queue.
 * The frontend polls the status endpoint until simulation_status = 'complete' | 'failed'.
 */
class SimulationController extends Controller
{
    /**
     * Request a new simulation for an evaluation.
     *
     * Returns immediately with status='pending'. The job runs asynchronously.
     * If a simulation is already in progress or complete, it returns the current state.
     */
    public function store(Request $request, Evaluation $evaluation): JsonResponse
    {
        // Prevent duplicate requests
        if (in_array($evaluation->simulation_status, ['pending', 'processing'], strict: true)) {
            return response()->json([
                'status' => $evaluation->simulation_status,
                'message' => 'Simulation already in progress.',
            ]);
        }

        $evaluation->update([
            'simulation_status' => 'pending',
            'simulation_data' => null,
            'simulation_requested_at' => now(),
        ]);

        GenerateSimulationJob::dispatch($evaluation->id)->onQueue('ai');

        return response()->json([
            'status' => 'pending',
            'message' => 'Simulation queued. Poll the status endpoint for updates.',
        ], 202);
    }

    /**
     * Return the current simulation status and result data for an evaluation.
     *
     * Used by the React SimulationViewer to poll until complete.
     *
     * @return JsonResponse{status: string, simulation_data: array|null, simulation_s3_url: string|null}
     */
    public function show(Request $request, Evaluation $evaluation): JsonResponse
    {
        $data = $evaluation->simulation_data;
        $simulationUrl = null;

        // Resolve a signed URL for the simulation image if available
        if ($evaluation->simulation_status === 'complete' && isset($data['simulation_s3_key']) && is_string($data['simulation_s3_key'])) {
            $simulationUrl = $this->signedSimulationUrl($data['simulation_s3_key']);
        }

        return response()->json([
            'status' => $evaluation->simulation_status,
            'simulation_data' => $data,
            'simulation_url' => $simulationUrl,
            'requested_at' => $evaluation->simulation_requested_at?->toIso8601String(),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function signedSimulationUrl(string $s3Key): ?string
    {
        try {
            $disk = config('features.ai_vision', false) ? 's3' : 'local';

            if ($disk === 'local') {
                // Local dev: stream the simulation image through a signed temporary URL
                // For now, return null — the SimulationViewer shows a placeholder in dev
                return null;
            }

            return Storage::disk('s3')->temporaryUrl(
                $s3Key,
                now()->addMinutes(30),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
