<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\TenantAdminController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Clinic\ClinicController;
use App\Http\Controllers\Clinic\TeamController;
use App\Http\Controllers\Clinic\WebhookDeliveryController;
use App\Http\Controllers\Clinic\IntegrationController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\ClinicalBriefController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EvaluationController as DashboardEvaluationController;
use App\Http\Controllers\Dashboard\PhotoStreamController;
use App\Http\Controllers\Dashboard\SimulationController;
use App\Http\Controllers\Intake\EvaluationController;
use App\Http\Controllers\Intake\IntakeController;
use App\Http\Controllers\Intake\PatientReportController;
use App\Http\Controllers\Intake\PhotoController;
use App\Http\Controllers\ClinicAccessRequestController;
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

Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // ── Session keepalive (HIPAA inactivity timer) ────────────────────────
    // Touching this endpoint resets the server-side session lifetime.
    // Called by the client-side useSessionTimeout hook when the user confirms
    // they are still active.
    Route::get('keepalive', fn () => response()->noContent())->name('keepalive');

    // ── Local-dev photo streaming (FEATURE_AI_VISION=false only) ─────────
    // In production this route is never hit — SecureFileService returns S3 pre-signed URLs.
    Route::get('/photos/{hash}', PhotoStreamController::class)->name('photos.stream');

    // ── Evaluations (coordinator priority queue) ──────────────────────────
    Route::prefix('evaluations')->name('evaluations.')->group(function (): void {
        Route::get('/', [DashboardEvaluationController::class, 'index'])->name('index');
        Route::get('/{evaluation}', [DashboardEvaluationController::class, 'show'])->name('show');
        Route::patch('/{evaluation}/status', [DashboardEvaluationController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{evaluation}/notes', [DashboardEvaluationController::class, 'updateNotes'])->name('update-notes');
        Route::get('/{evaluation}/brief', [ClinicalBriefController::class, 'download'])->name('brief');

        // ── AI Simulation ──────────────────────────────────────────────────
        Route::post('/{evaluation}/simulation', [SimulationController::class, 'store'])->name('simulation.store');
        Route::get('/{evaluation}/simulation', [SimulationController::class, 'show'])->name('simulation.show');
    });

    // ── Clinic settings & team (owner / admin only) ───────────────────────
    Route::prefix('clinic')->name('clinic.')->group(function (): void {
        Route::get('/settings', [ClinicController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [ClinicController::class, 'update'])->name('settings.update');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::patch('/integrations/webhook', [IntegrationController::class, 'updateWebhook'])->name('integrations.webhook.update');
        Route::post('/integrations/webhook/rotate', [IntegrationController::class, 'rotateSecret'])->name('integrations.webhook.rotate');

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
        ->middleware('throttle:5,1')
        ->name('evaluations.store');

    Route::post('/evaluations/{token}/quiz', [EvaluationController::class, 'quiz'])
        ->name('evaluations.quiz');

    Route::post('/evaluations/{token}/submit', [EvaluationController::class, 'submit'])
        ->middleware('throttle:5,1')
        ->name('evaluations.submit');

    // Photo upload
    Route::post('/evaluations/{token}/photos', [PhotoController::class, 'store'])
        ->name('evaluations.photos.store');

    // Patient Beauty Roadmap PDF (no auth — gated by evaluation token)
    Route::get('/evaluations/{token}/report', [PatientReportController::class, 'download'])
        ->name('evaluations.report');
});

// ─── Super-admin panel ────────────────────────────────────────────────────────
// Accessible to platform operators (tenant_id = null users) only.
// No 'tenant' middleware — these users don't belong to a clinic.

Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('admin.tenants.index'));

    Route::prefix('tenants')->name('tenants.')->group(function (): void {
        Route::get('/', [TenantAdminController::class, 'index'])->name('index');
        Route::get('/create', [TenantAdminController::class, 'create'])->name('create');
        Route::post('/', [TenantAdminController::class, 'store'])->name('store');
        // Note: show/update use string $id in the controller so they work with soft-deleted tenants.
        Route::get('/{id}', [TenantAdminController::class, 'show'])->name('show');
        Route::patch('/{id}', [TenantAdminController::class, 'update'])->name('update');
        Route::delete('/{tenant}', [TenantAdminController::class, 'deactivate'])->name('deactivate');
        Route::post('/{id}/restore', [TenantAdminController::class, 'restore'])->name('restore');
        Route::post('/{tenant}/users', [TenantAdminController::class, 'addUser'])->name('users.store');
        Route::post('/{tenant}/users/{user}/resend-invite', [TenantAdminController::class, 'resendInvite'])->name('users.resend');
    });
});

require __DIR__.'/settings.php';
