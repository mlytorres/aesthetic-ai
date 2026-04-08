<?php

declare(strict_types=1);

use App\Http\Controllers\Intake\EvaluationController;
use App\Http\Controllers\Intake\IntakeController;
use App\Http\Controllers\Intake\PhotoController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// ─── Public landing ───────────────────────────────────────────────────────────

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

// ─── Clinic dashboard (authenticated staff) ───────────────────────────────────
// 'tenant' runs after 'auth' so TenantMiddleware can fall back to the
// authenticated user's tenant_id when staff access the main domain directly.

Route::middleware(['auth', 'verified', 'tenant'])->group(function (): void {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
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
