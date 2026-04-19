<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Services\AffiliateAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateShortLinkController extends Controller
{
    public function __construct(
        private readonly AffiliateAttributionService $attributionService
    ) {}

    public function redirect(Request $request, string $code): RedirectResponse
    {
        // Service bypasses TenantScope for the lookup (short_code is unique across tenants)
        // and then hydrates TenantContext from the resolved link, so RLS allows downstream writes.
        $link = $this->attributionService->findActiveLinkByShortCode($code);

        if ($link === null) {
            return redirect()->route('intake.show');
        }

        // Record the click event
        $this->attributionService->trackClick($link, $request);

        // Redirect to intake wizard with the full affiliate token
        return redirect()->route('intake.show', [
            'aff' => $link->token,
        ]);
    }
}
