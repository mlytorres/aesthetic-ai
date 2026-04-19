<?php

declare(strict_types=1);

/**
 * Application security headers and embed / CSP tuning.
 *
 * @see SecurityHeadersMiddleware
 * @see AllowFramesMiddleware
 */
return [

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security (HSTS)
    |--------------------------------------------------------------------------
    |
    | Sent only when the request is HTTPS and the app runs in production.
    |
    */
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 63072000),

    'hsts_include_subdomains' => filter_var(
        env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        FILTER_VALIDATE_BOOL,
    ),

    'hsts_preload' => filter_var(env('SECURITY_HSTS_PRELOAD', false), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Extra parent origins for the intake embed (all environments)
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of full origins (e.g. https://www.clinic.com) merged
    | with each tenant's `embed_parent_origins` setting. Use for staging URLs
    | or shared marketing domains.
    |
    */
    'embed_parent_origins_extra' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SECURITY_EMBED_PARENT_ORIGINS_EXTRA', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Extra Vite dev server origins (local only)
    |--------------------------------------------------------------------------
    |
    | Comma-separated full origins for @vite when not using localhost (e.g. Herd +
    | laravel-vite-plugin detectTls). Merged with origins derived from APP_URL + :5173.
    |
    */
    'csp_vite_dev_origins_extra' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CSP_VITE_DEV_ORIGINS_EXTRA', '')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Require BAA before intake mutations
    |--------------------------------------------------------------------------
    |
    | When true, POST routes that create or advance evaluations are rejected until
    | super-admin records baa_signed_at. Set SECURITY_REQUIRE_BAA_FOR_INTAKE=false
    | in testing or local demos if needed.
    |
    */
    'require_baa_for_intake_submissions' => filter_var(
        env('SECURITY_REQUIRE_BAA_FOR_INTAKE', true),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Require 2FA for privileged tenant roles (dashboard)
    |--------------------------------------------------------------------------
    |
    | When true, authenticated clinic users with role owner, admin, or
    | coordinator must complete Fortify two-factor setup before accessing
    | tenant-scoped dashboard routes. They are redirected to Security settings.
    | Set SECURITY_REQUIRE_2FA_PRIVILEGED_ROLES=false in tests or local demos.
    |
    */
    'require_two_factor_for_privileged_tenant_roles' => filter_var(
        env('SECURITY_REQUIRE_2FA_PRIVILEGED_ROLES', true),
        FILTER_VALIDATE_BOOL,
    ),

    /*
    |--------------------------------------------------------------------------
    | Coordinator email OTP fallback (when authenticator app is not enabled)
    |--------------------------------------------------------------------------
    */
    'coordinator_email_otp_code_minutes' => (int) env('SECURITY_COORDINATOR_EMAIL_OTP_MINUTES', 10),
    'coordinator_email_otp_session_minutes' => (int) env('SECURITY_COORDINATOR_EMAIL_OTP_SESSION_MINUTES', 720),
    'coordinator_email_otp_max_attempts' => (int) env('SECURITY_COORDINATOR_EMAIL_OTP_MAX_ATTEMPTS', 5),

];
