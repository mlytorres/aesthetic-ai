<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProcedureResource;
use App\Models\Procedure;
use Illuminate\Http\Request;
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
    public function show(Request $request): Response
    {
        $tenant = TenantContext::get();
        $enabledSlugs = $tenant->enabledProcedures();

        // Load enabled procedures with their active quiz definitions
        $procedures = Procedure::whereIn('slug', $enabledSlugs)
            ->where('active', true)
            ->with(['quizDefinitions' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $settings = $tenant->settings ?? [];

        return Inertia::render('intake/wizard', [
            'clinic' => [
                'name' => $tenant->name,
                'theme' => $request->query('theme') ?? match ($settings['theme'] ?? 'luxury-dark') {
                    'clean-light' => 'luxury-light',
                    default => $settings['theme'] ?? 'luxury-dark',
                },
                'logo' => $settings['logo_url'] ?? null,
                'brand_primary' => $request->query('color') ?? $settings['brand_primary'] ?? null,
                'brand_font' => $request->query('font') ?? $settings['brand_font'] ?? null,
                'locale' => $request->query('lang') ?? $settings['locale'] ?? 'en',
                'lead_capture_position' => $settings['lead_capture_position'] ?? 'end',
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
        $tenant = TenantContext::get();
        $settings = $tenant->settings ?? [];

        return Inertia::render('intake/success', [
            'clinic' => [
                'name' => $tenant->name,
                'logo' => $settings['logo_url'] ?? null,
                'booking_url' => $settings['booking_url'] ?? null,
                'theme' => $settings['theme'] ?? 'luxury-dark',
                'brand_primary' => $settings['brand_primary'] ?? null,
                'brand_font' => $settings['brand_font'] ?? null,
            ],
            'evaluation' => [
                'token' => request()->query('token'),
                'name' => request()->query('name'),
                'email' => request()->query('email'),
            ],
        ]);
    }

    /**
     * Shown to patients when the clinic has exceeded their plan limits.
     * Patients shouldn't see a raw 402 — give them a friendly message instead.
     */
    public function blocked(Request $request): Response
    {
        return Inertia::render('intake/blocked', [
            'clinic' => ['name' => TenantContext::get()->name],
            'message' => $request->session()->get(
                'blocked_message',
                'This clinic is temporarily unable to accept new evaluations. Please contact them directly.',
            ),
        ]);
    }
}
