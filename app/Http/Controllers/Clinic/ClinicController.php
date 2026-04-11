<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClinicController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();

        return Inertia::render('clinic/settings', [
            'clinic' => [
                'name'                => $tenant->name,
                'slug'                => $tenant->slug,
                'theme'               => $tenant->settings['theme'] ?? 'luxury-dark',
                'logo_url'            => $tenant->settings['logo_url'] ?? null,
                'procedures_enabled'  => $tenant->settings['procedures_enabled'] ?? ['rhinoplasty'],
                'coordinator_emails'  => $tenant->settings['coordinator_emails'] ?? [],
            ],
            'availableProcedures' => \App\Models\Procedure::where('active', true)
                ->get(['slug', 'label', 'category'])
                ->map(fn ($p) => ['slug' => $p->slug, 'label' => $p->label, 'category' => $p->category])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name'                        => ['required', 'string', 'max:255'],
            'theme'                       => ['required', Rule::in(['luxury-dark', 'clean-light'])],
            'procedures_enabled'          => ['required', 'array', 'min:1'],
            'procedures_enabled.*'        => ['string', 'exists:procedures,slug'],
            'coordinator_emails'          => ['nullable', 'array'],
            'coordinator_emails.*'        => ['email'],
        ]);

        $tenant->update([
            'name'        => $validated['name'],
            'settings'    => array_merge($tenant->settings ?? [], [
                'theme'              => $validated['theme'],
                'procedures_enabled' => $validated['procedures_enabled'],
                'coordinator_emails' => $validated['coordinator_emails'] ?? [],
            ]),
        ]);

        return back()->with('flash.success', 'Clinic settings saved.');
    }
}
