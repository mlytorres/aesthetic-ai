<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Clinic\BillingController;
use App\Http\Controllers\Clinic\ClinicController;
use App\Http\Controllers\Clinic\IntegrationController;
use App\Http\Controllers\Clinic\TeamController;
use App\Http\Controllers\Clinic\WebhookDeliveryController;
use App\Http\Controllers\ClinicAccessRequestController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\ClinicalBriefController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EvaluationController as DashboardEvaluationController;
use App\Http\Controllers\Dashboard\EvaluationExportController;
use App\Http\Controllers\Dashboard\OnboardingController;
use App\Http\Controllers\Dashboard\PhotoStreamController;
use App\Http\Controllers\Dashboard\SimulationController;
use App\Http\Controllers\Intake\EvaluationController;
use App\Http\Controllers\Intake\IntakeController;
use App\Http\Controllers\Intake\PatientReportController;
use App\Http\Controllers\Intake\PhotoController;
use App\Http\Controllers\Intake\SimulationShareController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// ─── Public landing ───────────────────────────────────────────────────────────

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::post('/access-requests', [ClinicAccessRequestController::class, 'store'])->name('access-requests.store');

// ─── Magic link authentication ────────────────────────────────────────────────
// One-time coordinator login links sent in evaluation notification emails.
// No auth middleware — this IS the authentication step.
// Tenant resolved from subdomain so TenantContext is available.

Route::middleware(['tenant'])->group(function (): void {
    Route::get('/magic/{token}', MagicLinkController::class)->name('magic-link.use');
});

// ─── Clinic dashboard (authenticated staff) ───────────────────────────────────
// 'tenant' runs after 'auth' so TenantMiddleware can fall back to the
// authenticated user's tenant_id when staff access the main domain directly.
//
// Role access summary:
//   All roles          → dashboard, evaluations (view only)
//   Owner/Admin/Coord  → evaluation actions (status, notes), simulation, analytics, webhooks
//   Owner/Admin        → clinic settings, team, integrations, billing
//   Owner/Admin/Coord/Surgeon → clinical brief download

// ── Billing routes — no billing.access gate so expired tenants can upgrade ──
// Owner/admin only (same role restriction as before).
Route::middleware(['auth', 'verified', 'tenant', 'role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN])])
    ->prefix('clinic')->name('clinic.')
    ->group(function (): void {
        Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
        Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
        Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    });

// ── All other authenticated clinic routes — gated by active billing ──────────
Route::middleware(['auth', 'verified', 'tenant', 'billing.access'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');

    // ── Session keepalive (HIPAA inactivity timer) ────────────────────────
    Route::get('keepalive', fn () => response()->noContent())->name('keepalive');

    // ── Local-dev photo streaming (FEATURE_AI_VISION=false only) ─────────
    Route::get('/photos/{hash}', PhotoStreamController::class)->name('photos.stream');

    // ── Analytics — all roles except Surgeon ─────────────────────────────
    Route::get('analytics', [AnalyticsController::class, 'index'])
        ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR, User::ROLE_VIEWER]))
        ->name('analytics');

    // ── Evaluations ───────────────────────────────────────────────────────
    Route::prefix('evaluations')->name('evaluations.')->group(function (): void {
        // View — all roles
        Route::get('/', [DashboardEvaluationController::class, 'index'])->name('index');

        // CSV export — must be before /{evaluation} wildcard to avoid being swallowed.
        // Owner, admin, coordinator only (PHI access).
        Route::get('/export', EvaluationExportController::class)
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR]))
            ->name('export');

        Route::get('/{evaluation}', [DashboardEvaluationController::class, 'show'])->name('show');

        // Mutating actions — clinical actors only (owner, admin, coordinator)
        // Policy checks are also applied inside each controller method.
        Route::patch('/{evaluation}/status', [DashboardEvaluationController::class, 'updateStatus'])
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR]))
            ->name('update-status');

        Route::patch('/{evaluation}/notes', [DashboardEvaluationController::class, 'updateNotes'])
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR]))
            ->name('update-notes');

        // Clinical brief — owner, admin, coordinator, surgeon (not viewer)
        Route::get('/{evaluation}/brief', [ClinicalBriefController::class, 'download'])
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR, User::ROLE_SURGEON]))
            ->name('brief');

        // ── AI Simulation — owner, admin, coordinator, surgeon (not viewer) ──
        Route::post('/{evaluation}/simulation', [SimulationController::class, 'store'])
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR, User::ROLE_SURGEON]))
            ->name('simulation.store');

        Route::get('/{evaluation}/simulation', [SimulationController::class, 'show'])
            ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR, User::ROLE_SURGEON]))
            ->name('simulation.show');
    });

    // ── Clinic settings, team & integrations — owner and admin only ───────
    Route::prefix('clinic')->name('clinic.')
        ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN]))
        ->group(function (): void {
            Route::get('/settings', [ClinicController::class, 'edit'])->name('settings.edit');
            Route::patch('/settings', [ClinicController::class, 'update'])->name('settings.update');

            Route::get('/team', [TeamController::class, 'index'])->name('team.index');
            Route::post('/team', [TeamController::class, 'store'])->name('team.store');
            Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

            Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
            Route::patch('/integrations/webhook', [IntegrationController::class, 'updateWebhook'])->name('integrations.webhook.update');
            Route::post('/integrations/webhook/rotate', [IntegrationController::class, 'rotateSecret'])->name('integrations.webhook.rotate');
        });

    // ── Webhook delivery log — owner, admin, coordinator (read + retry) ───
    Route::prefix('clinic')->name('clinic.')
        ->middleware('role:'.implode(',', [User::ROLE_OWNER, User::ROLE_ADMIN, User::ROLE_COORDINATOR]))
        ->group(function (): void {
            Route::get('/webhooks', [WebhookDeliveryController::class, 'index'])->name('webhooks.index');
            Route::post('/webhooks/{webhookDelivery}/retry', [WebhookDeliveryController::class, 'retry'])->name('webhooks.retry');
        });
});

// ─── Patient Intake Wizard ────────────────────────────────────────────────────
// Tenant resolved from subdomain (e.g. miamilife.aesthetic-ai.test)
// No auth — patients are not logged in.

Route::middleware(['tenant'])->prefix('intake')->name('intake.')->group(function (): void {
    // Inertia page — loads wizard with clinic config + quiz definitions
    Route::get('/', [IntakeController::class, 'show'])->name('show');

    // Success page — shown after evaluation submitted
    Route::get('/success', [IntakeController::class, 'success'])->name('success');

    // Evaluation lifecycle (JSON responses — not Inertia redirects)
    Route::post('/evaluations', [EvaluationController::class, 'store'])
        ->middleware(['throttle:5,1', 'plan.limits'])
        ->name('evaluations.store');

    Route::post('/evaluations/{token}/quiz', [EvaluationController::class, 'quiz'])
        ->name('evaluations.quiz');

    Route::post('/evaluations/{token}/submit', [EvaluationController::class, 'submit'])
        ->middleware(['throttle:5,1', 'plan.limits'])
        ->name('evaluations.submit');

    // Plan limit exceeded — shown when a clinic's eval cap or subscription has lapsed.
    Route::get('/blocked', [IntakeController::class, 'blocked'])->name('blocked');

    // Photo upload
    Route::post('/evaluations/{token}/photos', [PhotoController::class, 'store'])
        ->name('evaluations.photos.store');

    // Patient Beauty Roadmap PDF (no auth — gated by evaluation token)
    Route::get('/evaluations/{token}/report', [PatientReportController::class, 'download'])
        ->name('evaluations.report');

    // AI simulation share page (no auth — gated by evaluation secure_token)
    Route::get('/simulations/{token}', [SimulationShareController::class, 'show'])
        ->name('simulation.share');
});

// ─── Super-admin panel ────────────────────────────────────────────────────────
// Accessible to platform operators (tenant_id = null users) only.
// No 'tenant' middleware — super-admins don't belong to a clinic.
// Cannot be reached by any tenant user regardless of role.

Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.tenants.index'));

    Route::prefix('tenants')->name('tenants.')->group(function (): void {
        Route::get('/', [TenantAdminController::class, 'index'])->name('index');
        Route::get('/create', [TenantAdminController::class, 'create'])->name('create');
        Route::post('/', [TenantAdminController::class, 'store'])->name('store');
        // Note: show/update use string $id so they work with soft-deleted tenants.
        Route::get('/{id}', [TenantAdminController::class, 'show'])->name('show');
        Route::patch('/{id}', [TenantAdminController::class, 'update'])->name('update');
        Route::delete('/{tenant}', [TenantAdminController::class, 'deactivate'])->name('deactivate');
        Route::post('/{id}/restore', [TenantAdminController::class, 'restore'])->name('restore');
        Route::post('/{tenant}/users', [TenantAdminController::class, 'addUser'])->name('users.store');
        Route::post('/{tenant}/users/{user}/resend-invite', [TenantAdminController::class, 'resendInvite'])->name('users.resend');
    });

    // Impersonation — at /admin/users/{user}/impersonate (outside the tenants prefix).
    Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'impersonate'])->name('users.impersonate');
});

// Stop impersonating — accessible while logged in as a tenant user (no super-admin middleware).
Route::delete('/impersonate', [ImpersonationController::class, 'leave'])
    ->middleware(['auth'])
    ->name('impersonate.leave');

require __DIR__.'/settings.php';
