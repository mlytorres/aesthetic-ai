<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evaluation;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Generates Clinical Brief PDFs for coordinator review.
 *
 * Encapsulates PDF generation so the same logic is reused by:
 *   - ClinicalBriefController  (download from dashboard)
 *   - NotifyClinicNewEvaluationJob  (email attachment)
 *
 * PHI: signed photo URLs are computed here — they expire after 15 minutes.
 * The PDF bytes are never persisted to disk in production.
 */
class ClinicalBriefService
{
    public function __construct(
        private readonly SecureFileService $files,
    ) {}

    /**
     * Generate a Clinical Brief PDF and return the raw bytes.
     *
     * @param  Evaluation  $evaluation  Must have 'patient', 'photos', 'tenant' already loaded.
     */
    public function generateBytes(Evaluation $evaluation): string
    {
        $photoData = $evaluation->photos->map(fn ($photo) => [
            'type' => $photo->type,
            'quality_score' => $photo->quality_score,
            'signed_url' => $this->files->getSignedUrl($photo->s3_key),
        ])->all();

        return Pdf::view('pdf.clinical-brief', [
            'evaluation' => $evaluation,
            'photoData' => $photoData,
        ])
            ->format('A4')
            ->generatePdfContent();
    }

    /**
     * Build the download filename for this evaluation's brief.
     */
    public function filename(Evaluation $evaluation): string
    {
        return sprintf(
            'clinical-brief-%s-%s.pdf',
            $evaluation->procedure_slug,
            substr($evaluation->id, 0, 8),
        );
    }
}
