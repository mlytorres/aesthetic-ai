<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Central registry of all procedures supported by the platform.
 *
 * Each tenant activates a subset of these slugs via the procedures table.
 * The registry drives pipeline routing, simulation prompt selection, and
 * recommendation strategy — add new slugs here first, then implement
 * the corresponding prompt/recommendation methods.
 *
 * Pipeline types:
 *   'body' — ExtractBodyLandmarksJob → CalculateBodyProportionsJob
 *   'face' — ExtractFacialLandmarksJob → CalculateProportionsJob
 */
class ProcedureRegistry
{
    /**
     * Body procedures — full-body landmark and proportion analysis.
     *
     * @var array<string>
     */
    public const BODY_PROCEDURES = [
        // ── Core body sculpting ──────────────────────────────────
        'bbl',
        'skinny_bbl',
        'reverse_bbl',
        'lipo_360',
        'liposuction',

        // ── Abdomen & torso ──────────────────────────────────────
        'tummy_tuck',
        'mommy_makeover',
        'abdominal_etching',
        'j_plasma',

        // ── Breast ───────────────────────────────────────────────
        'breast_augmentation',
        'breast_lift',
        'breast_reduction',
        'gynecomastia',

        // ── Arms, back & extremities ─────────────────────────────
        'arm_lipo_lift',
        'arm_thigh_lift',
        'back_liposuction_lift',
        'axillary_liposuction',

        // ── Other body ───────────────────────────────────────────
        'labiaplasty',
        'scar_revision',
    ];

    /**
     * Face & neck procedures — facial landmark and proportion analysis.
     *
     * @var array<string>
     */
    public const FACE_PROCEDURES = [
        'rhinoplasty',
        'facelift',
        'face_and_neck_lift',
        'chin_lipo',
        'eyelid_surgery',
        'bichectomy',
        'otoplasty',
    ];

    /**
     * High-revenue procedures that warrant a priority boost regardless of
     * quiz score, because the consultation value justifies immediate follow-up.
     *
     * @var array<string>
     */
    public const HIGH_REVENUE_PROCEDURES = [
        'mommy_makeover',
        'tummy_tuck',
        'facelift',
        'face_and_neck_lift',
        'breast_augmentation',
    ];

    /**
     * Return the pipeline type for a procedure slug.
     * Defaults to 'face' for unknown slugs so the AI pipeline
     * always runs (generic photo quality check still fires).
     */
    public static function pipelineType(string $slug): string
    {
        if (in_array($slug, self::BODY_PROCEDURES, strict: true)) {
            return 'body';
        }

        return 'face';
    }

    public static function isBodyProcedure(string $slug): bool
    {
        return in_array($slug, self::BODY_PROCEDURES, strict: true);
    }

    public static function isFaceProcedure(string $slug): bool
    {
        return in_array($slug, self::FACE_PROCEDURES, strict: true);
    }

    public static function isHighRevenue(string $slug): bool
    {
        return in_array($slug, self::HIGH_REVENUE_PROCEDURES, strict: true);
    }

    /**
     * All known procedure slugs (body + face combined).
     *
     * @return array<string>
     */
    public static function allSlugs(): array
    {
        return [...self::BODY_PROCEDURES, ...self::FACE_PROCEDURES];
    }
}
