<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Services\AuditLog;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClinicalBriefController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    /**
     * Generate and download a Clinical Brief PDF for the given evaluation.
     *
     * HIPAA: PHI access is audit-logged before PDF generation begins.
     * The PDF is streamed directly to the browser — never persisted to disk.
     *
     * NOTE: Uses string $evaluationId to avoid TenantScope firing before
     * TenantContext is resolved (same pattern as EvaluationController::show).
     */
    public function download(string $evaluationId): StreamedResponse
    {
        $evaluation = Evaluation::with(['patient', 'photos', 'tenant'])->findOrFail($evaluationId);

        $this->auditLog->record('evaluation.brief.downloaded', $evaluation);

        $filename = sprintf(
            'clinical-brief-%s-%s.pdf',
            $evaluation->procedure_slug,
            substr($evaluation->id, 0, 8),
        );

        return Pdf::view('pdf.clinical-brief', [
            'evaluation' => $evaluation,
        ])
            ->format('A4')
            ->download($filename);
    }
}
