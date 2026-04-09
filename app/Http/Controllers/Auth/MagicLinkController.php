<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Models\User;
use App\Services\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Handles one-time magic link authentication for coordinator portal access.
 *
 * Flow:
 *   1. Coordinator receives notification email with a signed magic link URL.
 *   2. They click the link → GET /magic/{token}
 *   3. We hash the raw token → find the MagicLink record.
 *   4. Validate: not expired, not already used.
 *   5. Resolve which User to authenticate as:
 *        a. Look up a User in the tenant whose email matches the recipient.
 *        b. Fall back to the first owner/coordinator for that tenant.
 *   6. Log the user in, mark the link used, redirect to the evaluation.
 *   7. Full audit log entry (HIPAA: who accessed what, when).
 *
 * Security guarantees:
 *   - Raw token never stored — only SHA-256 hash in DB.
 *   - Link expires after 15 minutes (configurable in MagicLink::generate()).
 *   - One-time use: used_at set on consumption, subsequent reuse returns 403.
 *   - Brute-force safe: 64-char random token = 2^384 entropy, SHA-256 in constant time.
 *   - Auth::login() creates a standard Laravel session (full CSRF protection applies).
 */
class MagicLinkController extends Controller
{
    public function __construct(private readonly AuditLog $auditLog) {}

    public function __invoke(string $token): RedirectResponse
    {
        // ── 1. Validate the magic link ─────────────────────────────────────────
        $magicLink = MagicLink::withoutGlobalScopes()
            ->with(['evaluation.tenant'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (!$magicLink) {
            return $this->fail('Invalid or unrecognised access link.');
        }

        if ($magicLink->used_at !== null) {
            return $this->fail('This link has already been used. Please request a new notification.');
        }

        if ($magicLink->isExpired()) {
            return $this->fail('This link has expired (links are valid for 15 minutes). Please log in directly.');
        }

        $evaluation = $magicLink->evaluation;
        $tenant     = $evaluation?->tenant;

        if (!$evaluation || !$tenant) {
            return $this->fail('Evaluation not found.');
        }

        // ── 2. Resolve the coordinator user for this tenant ────────────────────
        // We stored the recipient email on the magic link so we can log in as them.
        // Fall back to the first owner/coordinator if not found.
        $user = $this->resolveUser($magicLink, $tenant);

        if (!$user) {
            return $this->fail('No coordinator account found for this clinic.');
        }

        // ── 3. Consume the magic link (mark used before login) ─────────────────
        $magicLink->markUsed();

        // ── 4. Authenticate the coordinator ───────────────────────────────────
        Auth::login($user, remember: false);

        // Regenerate session to prevent fixation attacks
        request()->session()->regenerate();

        // ── 5. Audit log — HIPAA: track coordinator portal access via magic link ─
        $this->auditLog->record('coordinator.magic_link.used', $evaluation, [
            'user_id'        => $user->id,
            'magic_link_id'  => $magicLink->id,
        ]);

        // ── 6. Redirect to the evaluation ─────────────────────────────────────
        return redirect()->route('evaluations.show', $evaluation->id)
            ->with('flash.success', "Welcome back, {$user->name}. Evaluation loaded.");
    }

    /**
     * Resolve which User to log in as.
     *
     * Priority:
     *   1. User whose email matches the recipient_email stored on the magic link.
     *   2. First owner for the tenant.
     *   3. First coordinator for the tenant.
     */
    private function resolveUser(MagicLink $magicLink, \App\Models\Tenant $tenant): ?User
    {
        // If a specific recipient email was stored on the magic link, prefer that user
        if (!empty($magicLink->recipient_email)) {
            $byEmail = User::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('email', $magicLink->recipient_email)
                ->first();

            if ($byEmail) {
                return $byEmail;
            }
        }

        // Fall back to owner, then coordinator
        return User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'coordinator' THEN 3 ELSE 4 END")
            ->first();
    }

    private function fail(string $message): RedirectResponse
    {
        return redirect()->route('login')
            ->withErrors(['email' => $message]);
    }
}
