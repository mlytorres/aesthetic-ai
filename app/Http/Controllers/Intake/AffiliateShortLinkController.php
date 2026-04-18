<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
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
        $link = AffiliateLink::query()
            ->where('short_code', $code)
            ->where('status', AffiliateLink::STATUS_ACTIVE)
            ->first();

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
