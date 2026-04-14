<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clinic;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClinicController extends Controller
{
    public function edit(): Response
    {
        $tenant = TenantContext::get();
        $settings = $tenant->settings ?? [];

        return Inertia::render('clinic/settings', [
            'clinic' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'theme' => match ($settings['theme'] ?? 'luxury-dark') {
                    'clean-light' => 'luxury-light',
                    default => $settings['theme'] ?? 'luxury-dark',
                },
                'logo_url' => $settings['logo_url'] ?? null,
                'brand_primary' => $settings['brand_primary'] ?? null,
                'from_name' => $settings['from_name'] ?? null,
                'custom_domain' => $settings['custom_domain'] ?? null,
                'locale' => $settings['locale'] ?? 'en',
                'procedures_enabled' => $settings['procedures_enabled'] ?? ['rhinoplasty'],
                'coordinator_emails' => $settings['coordinator_emails'] ?? [],
                'phone' => $settings['phone'] ?? null,
                'booking_url' => $settings['booking_url'] ?? null,
            ],
            'availableProcedures' => Procedure::where('active', true)
                ->get(['slug', 'label', 'category'])
                ->map(fn ($p) => ['slug' => $p->slug, 'label' => $p->label, 'category' => $p->category])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'theme' => ['required', Rule::in(['luxury-dark', 'luxury-light', 'clinical'])],
            'brand_primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'from_name' => ['nullable', 'string', 'max:80'],
            'custom_domain' => ['nullable', 'string', 'max:253', 'regex:/^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]$/i'],
            'locale' => ['required', Rule::in(['en', 'es'])],
            'procedures_enabled' => ['required', 'array', 'min:1'],
            'procedures_enabled.*' => ['string', 'exists:procedures,slug'],
            'coordinator_emails' => ['nullable', 'array'],
            'coordinator_emails.*' => ['email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'booking_url' => ['nullable', 'url', 'max:500'],
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'settings' => array_merge($tenant->settings ?? [], [
                'theme' => $validated['theme'],
                'brand_primary' => $validated['brand_primary'] ?: null,
                'from_name' => $validated['from_name'] ?: null,
                'custom_domain' => $validated['custom_domain'] ?: null,
                'locale' => $validated['locale'],
                'procedures_enabled' => $validated['procedures_enabled'],
                'coordinator_emails' => $validated['coordinator_emails'] ?? [],
                'phone' => $validated['phone'] ?: null,
                'booking_url' => $validated['booking_url'] ?: null,
                'clinic_configured' => true,
            ]),
        ]);

        return back()->with('flash.success', 'Clinic settings saved.');
    }

    /**
     * Upload or replace the clinic logo.
     * Stored in the public disk so it can be served without a signed URL
     * (logos are not PHI — they're publicly visible on the intake page).
     */
    public function uploadLogo(Request $request): RedirectResponse
    {
        $tenant = TenantContext::get();

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,webp,svg', 'max:2048'],
        ]);

        // Delete the old logo if one exists.
        $existing = $tenant->settings['logo_path'] ?? null;
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        $path = $request->file('logo')->store(
            "logos/{$tenant->id}",
            'public',
        );

        $url = Storage::disk('public')->url($path);

        $tenant->update([
            'settings' => array_merge($tenant->settings ?? [], [
                'logo_url' => $url,
                'logo_path' => $path,
            ]),
        ]);

        return back()->with('flash.success', 'Logo updated.');
    }

    /**
     * Remove the clinic logo and fall back to the wordmark.
     */
    public function deleteLogo(): RedirectResponse
    {
        $tenant = TenantContext::get();

        $path = $tenant->settings['logo_path'] ?? null;
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        $tenant->update([
            'settings' => array_merge($tenant->settings ?? [], [
                'logo_url' => null,
                'logo_path' => null,
            ]),
        ]);

        return back()->with('flash.success', 'Logo removed.');
    }
}
