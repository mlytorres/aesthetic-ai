<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Nightwatch Enabled
    |--------------------------------------------------------------------------
    |
    | Disable via NIGHTWATCH_ENABLED=false in .env to turn off all telemetry
    | (e.g. for local development or CI pipelines).
    |
    */

    'enabled' => env('NIGHTWATCH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Token & Deployment Identity
    |--------------------------------------------------------------------------
    */

    'token'      => env('NIGHTWATCH_TOKEN'),
    'deployment' => env('NIGHTWATCH_DEPLOY', env('LARAVEL_CLOUD_DEPLOY_UUID', env('FORGE_DEPLOY_COMMIT', env('VAPOR_COMMIT_HASH')))),
    'server'     => env('NIGHTWATCH_SERVER', (string) gethostname()),

    /*
    |--------------------------------------------------------------------------
    | Exception Source Code Capture
    |--------------------------------------------------------------------------
    |
    | Captures the relevant lines of PHP source code when an exception occurs.
    | Source code does not contain PHI — safe to keep enabled.
    |
    */

    'capture_exception_source_code' => env('NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE', true),

    /*
    |--------------------------------------------------------------------------
    | Request Payload Capture — DISABLED for HIPAA compliance
    |--------------------------------------------------------------------------
    |
    | AestheticAI handles PHI (patient name, email, phone, quiz answers).
    | Request payloads MUST NOT be captured to avoid sending unencrypted PHI
    | to Nightwatch's ingest endpoint.  Defense-in-depth: even if this were
    | toggled on, all known PHI field names are listed in redact_payload_fields.
    |
    */

    'capture_request_payload' => env('NIGHTWATCH_CAPTURE_REQUEST_PAYLOAD', false),

    /*
    |--------------------------------------------------------------------------
    | PHI Field Redaction
    |--------------------------------------------------------------------------
    |
    | Any payload key matching these names will be replaced with [REDACTED]
    | before transmission.  This list covers:
    |   - Standard Laravel auth fields
    |   - AestheticAI patient PHI fields (patients table)
    |   - Quiz free-text fields that may contain patient-provided PHI
    |   - Coordinator notes (may reference patient details)
    |
    | Note: PHI columns use EncryptedString/EncryptedDate casts in the ORM,
    | so DB values are already encrypted at rest.  This redaction list acts as
    | a safety net for any raw form submissions.
    |
    */

    'redact_payload_fields' => explode(',', env(
        'NIGHTWATCH_REDACT_PAYLOAD_FIELDS',
        implode(',', [
            // Laravel defaults
            '_token',
            'password',
            'password_confirmation',
            'current_password',

            // Patient PHI — maps to encrypted columns on the patients table
            'name',
            'email',
            'phone',
            'dob',
            'date_of_birth',
            'patient_name',
            'patient_email',
            'patient_phone',

            // Quiz free-text answers that may contain PHI
            'quiz_answers',
            'answers',

            // Coordinator-generated content
            'coordinator_notes',

            // Magic link / signed tokens — not PHI but sensitive
            'token',
            'secure_token',
        ])
    )),

    /*
    |--------------------------------------------------------------------------
    | Header Redaction
    |--------------------------------------------------------------------------
    |
    | Extends the defaults with Inertia's XSRF header which carries session
    | context on every mutating request.
    |
    */

    'redact_headers' => explode(',', env(
        'NIGHTWATCH_REDACT_HEADERS',
        'Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN,X-CSRF-TOKEN'
    )),

    /*
    |--------------------------------------------------------------------------
    | Sampling Rates
    |--------------------------------------------------------------------------
    |
    | Production recommendations for a HIPAA multi-tenant SaaS:
    |
    |   requests       0.10 — 10 % sample keeps volume manageable; raises to
    |                          1.0 temporarily when debugging specific issues.
    |   commands       1.00 — CLI commands are infrequent; full capture useful.
    |   exceptions     1.00 — Always capture every exception; critical for
    |                          HIPAA incident detection & audit.
    |   scheduled_tasks 1.00 — Queued AI jobs, webhook retries — full coverage.
    |
    | Override per-environment via .env without changing this file.
    |
    */

    'sampling' => [
        'requests'        => env('NIGHTWATCH_REQUEST_SAMPLE_RATE',       0.10),
        'commands'        => env('NIGHTWATCH_COMMAND_SAMPLE_RATE',        1.0),
        'exceptions'      => env('NIGHTWATCH_EXCEPTION_SAMPLE_RATE',      1.0),
        'scheduled_tasks' => env('NIGHTWATCH_SCHEDULED_TASK_SAMPLE_RATE', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Filtering
    |--------------------------------------------------------------------------
    |
    | Cache events are very high-frequency in an Octane application; ignore
    | them to reduce ingest volume.  All other event types are retained.
    |
    | Log level threshold: only ship `warning` and above to Nightwatch in
    | production to suppress verbose debug/info lines from queue workers.
    |
    */

    'filtering' => [
        'ignore_cache_events'       => env('NIGHTWATCH_IGNORE_CACHE_EVENTS',       true),
        'ignore_mail'               => env('NIGHTWATCH_IGNORE_MAIL',               false),
        'ignore_notifications'      => env('NIGHTWATCH_IGNORE_NOTIFICATIONS',      false),
        'ignore_outgoing_requests'  => env('NIGHTWATCH_IGNORE_OUTGOING_REQUESTS',  false),
        'ignore_queries'            => env('NIGHTWATCH_IGNORE_QUERIES',            false),
        'log_level'                 => env('NIGHTWATCH_LOG_LEVEL',                 'warning'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingest Agent Connection
    |--------------------------------------------------------------------------
    |
    | Nightwatch agent runs as a sidecar on the same host (default 127.0.0.1).
    | Short timeouts prevent the agent from adding latency to Octane requests.
    |
    */

    'ingest' => [
        'uri'                => env('NIGHTWATCH_INGEST_URI',              '127.0.0.1:2407'),
        'timeout'            => env('NIGHTWATCH_INGEST_TIMEOUT',          0.5),
        'connection_timeout' => env('NIGHTWATCH_INGEST_CONNECTION_TIMEOUT', 0.5),
        'event_buffer'       => env('NIGHTWATCH_INGEST_EVENT_BUFFER',     500),
    ],

];
