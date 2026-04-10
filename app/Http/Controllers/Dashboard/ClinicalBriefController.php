<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Services\AuditLog;
use App\Services\ClinicalBriefService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalBriefController extends Controller
{
    public function __construct(
        private readonly AuditLog $auditLog,
        private readonly ClinicalBriefService $briefService,
    ) {}

    /**
     * Generate and download a Clinical Brief PDF for the given evaluation.
     *
     * HIPAA: PHI access is audit-logged before PDF generation begins.
     * The PDF is streamed directly to the browser — never persisted to disk.
     *
     * NOTE: Uses string $evaluationId to avoid TenantScope firing before
     * TenantContext is resolved (same pattern as EvaluationController::show).
     */
    public function download(string $evaluationId): Response|StreamedResponse
    {
        $evaluation = Evaluation::with(['patient', 'photos', 'tenant'])->findOrFail($evaluationId);

        $this->auditLog->record('evaluation.brief.downloaded', $evaluation);

        $filename = $this->briefService->filename($evaluation);
        $bytes = $this->briefService->generateBytes($evaluation);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($bytes),
        ]);
    }
}
