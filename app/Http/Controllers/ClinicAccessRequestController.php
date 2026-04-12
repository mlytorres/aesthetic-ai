<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClinicAccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClinicAccessRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        ClinicAccessRequest::create($validated);

        Log::info('Clinic Platform Access Request Received', ['email' => $validated['email'], 'clinic' => $validated['clinic_name']]);

        return back()->with('success', 'Your request has been securely submitted! Our team will review your clinic and reach out shortly.');
    }
}
