<?php

declare(strict_types=1);

namespace App\Services;

use App\Facades\TenantContext;
use App\Models\AuditLogEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * HIPAA-compliant audit logging service.
 *
 * Every access to PHI must call AuditLog::record().
 * Logs are append-only and never deleted.
 *
 * Usage:
 *   AuditLog::record('evaluation.photos.viewed', $evaluation);
 *   AuditLog::record('patient.profile.viewed', $patient, ['reason' => 'coordinator_review']);
 *
 * Standard action namespaces:
 *   evaluation.created / evaluation.submitted / evaluation.status.changed
 *   evaluation.photos.viewed     ← PHI access
 *   evaluation.brief.downloaded  ← PHI access
 *   evaluation.analysis.complete
 *   patient.profile.viewed       ← PHI access
 *   coordinator.portal.accessed
 *   coordinator.logged_in / coordinator.logged_out
 *   api_token.used
 *   webhook.delivered / webhook.failed
 */
class AuditLog
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record an audit event (HTTP request context — use in controllers).
     *
     * @param string $action     Dot-separated namespace, e.g. 'evaluation.photos.viewed'
     * @param Model|null $subject  The entity being accessed or modified
     * @param array<string, mixed> $metadata  Additional safe context — NEVER include PHI
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
    ): void {
        try {
            AuditLogEntry::create([
                'tenant_id'    => TenantContext::isSet() ? TenantContext::id() : null,
                'user_id'      => Auth::id(),
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'metadata'     => $metadata,
                'ip_address'   => $this->request->ip(),
                'user_agent'   => $this->request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit log failure block the primary request.
            // But always log the failure itself.
            Log::critical('AuditLog write failed', [
                'action'    => $action,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a system-generated audit event (no HTTP context — use in queue jobs).
     *
     * Queue workers don't have an HTTP request, so ip_address and user_agent
     * are set to system identifiers rather than null (which would look like
     * a missing log entry in HIPAA audits).
     *
     * @param string $action
     * @param Model|null $subject
     * @param array<string, mixed> $metadata
     */
    public function recordSystem(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
    ): void {
        try {
            // Resolve tenant_id from the subject model itself if available
            $tenantId = null;
            if ($subject instanceof Model && isset($subject->tenant_id)) {
                $tenantId = $subject->tenant_id;
            } elseif (TenantContext::isSet()) {
                $tenantId = TenantContext::id();
            }

            AuditLogEntry::create([
                'tenant_id'    => $tenantId,
                'user_id'      => null,   // system action, no user
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'metadata'     => array_merge($metadata, ['_source' => 'queue_job']),
                'ip_address'   => '127.0.0.1',
                'user_agent'   => 'AestheticAI/QueueWorker',
            ]);
        } catch (\Throwable $e) {
            Log::critical('AuditLog::recordSystem write failed', [
                'action'    => $action,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
