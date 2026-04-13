<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Services\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams all evaluations for the current tenant as a UTF-8 CSV download.
 *
 * PHI (patient name, email, phone) is decrypted by Eloquent casts on the
 * Patient model — we never touch the raw ciphertext here.
 *
 * The export is audit-logged for HIPAA compliance.
 */
class EvaluationExportController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in([
                'active', 'analyzing', 'complete', 'submitted',
                'contacted', 'booked', 'no_show', 'not_a_fit', 'failed',
            ])],
        ]);

        $status = $validated['status'] ?? null;

        $this->auditLog->record('evaluations.exported', null, ['status_filter' => $status]);

        $filename = 'evaluations-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($status): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // UTF-8 BOM so Excel opens the file correctly without garbling names.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Patient Name',
                'Patient Email',
                'Patient Phone',
                'Procedure',
                'Priority',
                'Lead Score',
                'Status',
                'Coordinator Notes',
                'Submitted At',
                'Completed At',
                'Follow-up At',
            ]);

            Evaluation::with('patient')
                ->when(
                    $status === 'active' || $status === null,
                    fn ($q) => $q->when($status === 'active', fn ($q) => $q->whereNotIn('status', [
                        Evaluation::STATUS_BOOKED,
                        Evaluation::STATUS_NO_SHOW,
                        Evaluation::STATUS_NOT_A_FIT,
                        Evaluation::STATUS_DRAFT,
                        Evaluation::STATUS_FAILED,
                    ]))
                )
                ->when(
                    $status !== null && $status !== 'active',
                    fn ($q) => $q->where('status', $status)
                )
                ->orderByDesc('created_at')
                ->chunk(200, function ($evaluations) use ($handle): void {
                    foreach ($evaluations as $ev) {
                        fputcsv($handle, [
                            $ev->id,
                            $ev->patient?->name_encrypted ?? '',
                            $ev->patient?->email_encrypted ?? '',
                            $ev->patient?->phone_encrypted ?? '',
                            $ev->procedure_slug,
                            $ev->priority ?? '',
                            $ev->lead_score ?? '',
                            $ev->status,
                            $ev->coordinator_notes ?? '',
                            $ev->created_at?->toDateTimeString() ?? '',
                            $ev->completed_at?->toDateTimeString() ?? '',
                            $ev->follow_up_at?->toDateString() ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
