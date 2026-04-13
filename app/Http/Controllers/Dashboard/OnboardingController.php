<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Facades\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class OnboardingController extends Controller
{
    /**
     * Dismiss the onboarding checklist for this tenant.
     *
     * Stores a flag in tenant settings so the checklist no longer renders.
     */
    public function dismiss(): RedirectResponse
    {
        $tenant = TenantContext::get();

        $tenant->update([
            'settings' => array_merge($tenant->settings ?? [], [
                'onboarding_dismissed' => true,
            ]),
        ]);

        return back();
    }
}
