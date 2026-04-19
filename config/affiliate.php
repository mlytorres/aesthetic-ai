<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Fraud / Velocity Scoring
    |--------------------------------------------------------------------------
    |
    | Thresholds for the synchronous heuristics that fire in
    | AffiliateAttributionService::calculateFraudFlags before a payout ledger
    | row is created. Flags are advisory — the ledger still enters
    | STATUS_PENDING_HOLD so a human reviewer can approve or reject.
    |
    */
    'fraud' => [
        // More than this many completed evaluations for a single partner in
        // the last 24h flags 'high_velocity_24h' on the new ledger row.
        'daily_completion_threshold' => (int) env('AFFILIATE_FRAUD_DAILY_COMPLETION_THRESHOLD', 10),

        // More than this many real-user clicks from the same IP hash in the
        // last 1h for a single partner flags 'high_click_velocity_1h'.
        'click_velocity_1h_threshold' => (int) env('AFFILIATE_FRAUD_CLICK_VELOCITY_1H_THRESHOLD', 5),

        // Ledgers with this risk level or higher require explicit fraud review
        // before the payout can be released. Values: 'medium', 'high'.
        'review_required_from_risk' => env('AFFILIATE_FRAUD_REVIEW_REQUIRED_FROM_RISK', 'medium'),
    ],
];
