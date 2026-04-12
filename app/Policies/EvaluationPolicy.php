<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Evaluation;
use App\Models\User;

/**
 * Authorization rules for evaluation actions by tenant role.
 *
 * Role matrix:
 * ┌────────────────────────┬───────┬───────┬─────────────┬─────────┬────────┐
 * │ Action                 │ Owner │ Admin │ Coordinator │ Surgeon │ Viewer │
 * ├────────────────────────┼───────┼───────┼─────────────┼─────────┼────────┤
 * │ view                   │  ✅   │  ✅   │     ✅      │   ✅    │  ✅    │
 * │ updateStatus           │  ✅   │  ✅   │     ✅      │   ❌    │  ❌    │
 * │ updateNotes            │  ✅   │  ✅   │     ✅      │   ❌    │  ❌    │
 * │ downloadBrief          │  ✅   │  ✅   │     ✅      │   ✅    │  ❌    │
 * │ requestSimulation      │  ✅   │  ✅   │     ✅      │   ✅    │  ❌    │
 * └────────────────────────┴───────┴───────┴─────────────┴─────────┴────────┘
 */
class EvaluationPolicy
{
    /** All tenant roles can view evaluations. */
    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->tenant_id === $evaluation->tenant_id;
    }

    /** Clinical actors (owner, admin, coordinator) can change evaluation status. */
    public function updateStatus(User $user, Evaluation $evaluation): bool
    {
        return $user->tenant_id === $evaluation->tenant_id
            && $user->isClinicalActor();
    }

    /** Clinical actors can add/edit coordinator notes. */
    public function updateNotes(User $user, Evaluation $evaluation): bool
    {
        return $user->tenant_id === $evaluation->tenant_id
            && $user->isClinicalActor();
    }

    /**
     * Owner, Admin, Coordinator, and Surgeon can download the clinical brief.
     * Viewers are excluded — the brief contains detailed clinical analysis.
     */
    public function downloadBrief(User $user, Evaluation $evaluation): bool
    {
        return $user->tenant_id === $evaluation->tenant_id
            && ! $user->isViewer();
    }

    /**
     * Owner, Admin, Coordinator, and Surgeon can request AI simulations.
     * Viewers are read-only and cannot trigger AI processing.
     */
    public function requestSimulation(User $user, Evaluation $evaluation): bool
    {
        return $user->tenant_id === $evaluation->tenant_id
            && ! $user->isViewer();
    }
}
