<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Clinic\ClinicController;
use App\Http\Controllers\Clinic\TeamController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\EvaluationController as DashboardEvaluationController;
use App\Http\Controllers\Intake\EvaluationController;
use App\Http\Controllers\Intake\IntakeController;
use App\Http\Controllers\Intake\PhotoController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// ─── Public landing ───────────────────────────────────────────────────────────

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

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

    // ── Evaluations (coordinator priority queue) ──────────────────────────
    Route::prefix('evaluations')->name('evaluations.')->group(function (): void {
        Route::get('/', [DashboardEvaluationController::class, 'index'])->name('index');
        Route::get('/{evaluation}', [DashboardEvaluationController::class, 'show'])->name('show');
        Route::patch('/{evaluation}/status', [DashboardEvaluationController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{evaluation}/notes', [DashboardEvaluationController::class, 'updateNotes'])->name('update-notes');
    });

    // ── Clinic settings & team (owner / admin only) ───────────────────────
    Route::prefix('clinic')->name('clinic.')->group(function (): void {
        Route::get('/settings', [ClinicController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [ClinicController::class, 'update'])->name('settings.update');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');
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
        ->name('evaluations.store');

    Route::post('/evaluations/{token}/quiz', [EvaluationController::class, 'quiz'])
        ->name('evaluations.quiz');

    Route::post('/evaluations/{token}/submit', [EvaluationController::class, 'submit'])
        ->name('evaluations.submit');

    // Photo upload
    Route::post('/evaluations/{token}/photos', [PhotoController::class, 'store'])
        ->name('evaluations.photos');
});

require __DIR__.'/settings.php';
