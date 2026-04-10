<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Services\PatientReportService;
use Illuminate\Http\Response;

/**
 * Serves the patient-facing Beauty Roadmap PDF.
 *
 * Gated by the evaluation token (no authentication required).
 * Only evaluations in 'complete' status have a generated report.
 *
 * PHI: PDF contains first name only — no contact info or clinical data.
 */
class PatientReportController extends Controller
{
    public function __construct(
        private readonly PatientReportService $reportService,
    ) {}

    /**
     * Download the Beauty Roadmap PDF for a completed evaluation.
     *
     * Token acts as the authorization credential — same pattern as the intake flow.
     * Returns 404 if the token is not found or the evaluation is not yet complete.
     */
    public function download(string $token): Response
    {
        $evaluation = Evaluation::with(['patient', 'tenant'])
            ->where('secure_token', $token)
            ->where('status', Evaluation::STATUS_COMPLETE)
            ->firstOrFail();

        $pdfBytes = $this->reportService->generateBytes($evaluation);
        $filename = $this->reportService->filename($evaluation);

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Content-Length' => strlen($pdfBytes),
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
