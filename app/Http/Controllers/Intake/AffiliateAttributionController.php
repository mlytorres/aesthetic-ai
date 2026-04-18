<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Services\AffiliateAttributionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateAttributionController extends Controller
{
    public function __construct(private readonly AffiliateAttributionService $attributionService) {}

    public function track(Request $request, string $token): RedirectResponse
    {
        $link = $this->attributionService->findActiveLinkByToken($token);

        if ($link !== null) {
            $this->attributionService->trackClick($link, $request);
        }

        return redirect()->route('intake.show', [
            'aff' => $token,
        ]);
    }
}
