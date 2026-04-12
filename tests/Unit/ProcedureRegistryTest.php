<?php

declare(strict_types=1);

use App\Services\ProcedureRegistry;

// ─── Pipeline type classification ─────────────────────────────────────────────

test('body procedures are correctly classified', function (string $slug): void {
    expect(ProcedureRegistry::isBodyProcedure($slug))->toBeTrue()
        ->and(ProcedureRegistry::isFaceProcedure($slug))->toBeFalse()
        ->and(ProcedureRegistry::pipelineType($slug))->toBe('body');
})->with([
    'bbl',
    'skinny_bbl',
    'reverse_bbl',
    'lipo_360',
    'liposuction',
    'tummy_tuck',
    'mommy_makeover',
    'abdominal_etching',
    'j_plasma',
    'breast_augmentation',
    'breast_lift',
    'breast_reduction',
    'gynecomastia',
    'arm_lipo_lift',
    'arm_thigh_lift',
    'back_liposuction_lift',
    'axillary_liposuction',
    'labiaplasty',
    'scar_revision',
]);

test('face procedures are correctly classified', function (string $slug): void {
    expect(ProcedureRegistry::isFaceProcedure($slug))->toBeTrue()
        ->and(ProcedureRegistry::isBodyProcedure($slug))->toBeFalse()
        ->and(ProcedureRegistry::pipelineType($slug))->toBe('face');
})->with([
    'rhinoplasty',
    'facelift',
    'face_and_neck_lift',
    'chin_lipo',
    'eyelid_surgery',
    'bichectomy',
    'otoplasty',
]);

test('unknown slug defaults to face pipeline', function (): void {
    expect(ProcedureRegistry::pipelineType('unknown_procedure'))->toBe('face')
        ->and(ProcedureRegistry::isBodyProcedure('unknown_procedure'))->toBeFalse();
});

// ─── High-revenue classification ──────────────────────────────────────────────

test('high revenue procedures are flagged', function (string $slug): void {
    expect(ProcedureRegistry::isHighRevenue($slug))->toBeTrue();
})->with([
    'mommy_makeover',
    'tummy_tuck',
    'facelift',
    'face_and_neck_lift',
    'breast_augmentation',
]);

test('standard procedures are not high revenue', function (string $slug): void {
    expect(ProcedureRegistry::isHighRevenue($slug))->toBeFalse();
})->with([
    'chin_lipo',
    'bichectomy',
    'otoplasty',
    'axillary_liposuction',
    'j_plasma',
]);

// ─── allSlugs() completeness ──────────────────────────────────────────────────

test('allSlugs includes both body and face slugs', function (): void {
    $all = ProcedureRegistry::allSlugs();

    expect($all)->toContain('bbl')
        ->and($all)->toContain('rhinoplasty')
        ->and($all)->toContain('mommy_makeover')
        ->and($all)->toContain('eyelid_surgery')
        ->and($all)->toContain('tummy_tuck');
});

test('allSlugs has no duplicates', function (): void {
    $all = ProcedureRegistry::allSlugs();
    expect(count($all))->toBe(count(array_unique($all)));
});
