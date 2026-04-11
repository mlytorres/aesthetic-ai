<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProcedureResource;
use App\Models\Procedure;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the patient-facing intake wizard pages.
 * No authentication required — tenant is resolved from subdomain.
 */
class IntakeController extends Controller
{
    /**
     * Render the intake wizard with all config the frontend needs.
     * The wizard state lives entirely in React after this initial load.
     */
    public function show(\Illuminate\Http\Request $request): Response
    {
        $tenant = TenantContext::get();
        $enabledSlugs = $tenant->enabledProcedures();

        // Load enabled procedures with their active quiz definitions
        $procedures = Procedure::whereIn('slug', $enabledSlugs)
            ->where('active', true)
            ->with(['quizDefinitions' => fn ($q) => $q->where('is_active', true)])
            ->get();

        return Inertia::render('intake/wizard', [
            'clinic' => [
                'name'  => $tenant->name,
                'theme' => $tenant->settings['theme'] ?? 'luxury-dark',
                'logo'  => $tenant->settings['logo_url'] ?? null,
            ],
            'hideHeader' => $request->query('hide_header') === 'true',
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            // Resolve each resource individually — ::collection() wraps in {data:[]}
            // which Inertia passes through as an object, not an array.
            'procedures' => $procedures->map(fn (Procedure $p) => (new ProcedureResource($p))->resolve())->values(),
        ]);
    }

    /**
     * Render the success screen after submission.
     * Token passed as query param for "what happens next" messaging.
     */
    public function success(): Response
    {
        return Inertia::render('intake/success', [
            'clinic' => [
                'name' => TenantContext::get()->name,
            ],
        ]);
    }
}
