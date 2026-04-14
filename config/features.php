<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Vision (AWS Rekognition)
    |--------------------------------------------------------------------------
    |
    | When true:  real Rekognition calls are made, S3 is used, KMS encryption
    |             is enforced. Only set to true on staging/production.
    |
    | When false: all AI jobs run in "simulation mode" — they generate
    |             realistic-looking placeholder data without hitting AWS.
    |             Safe to use in local development without AWS credentials.
    |
    */
    'ai_vision' => (bool) env('FEATURE_AI_VISION', false),

    /*
    |--------------------------------------------------------------------------
    | Lead Scoring
    |--------------------------------------------------------------------------
    |
    | Master switch for the lead scoring pipeline. If false, evaluations are
    | marked complete but lead_score stays null and priority stays 'standard'.
    |
    */
    'lead_scoring' => (bool) env('FEATURE_LEAD_SCORING', true),

    /*
    |--------------------------------------------------------------------------
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Controls whether coordinator notification emails are sent. Even when
    | true, in local dev with MAIL_MAILER=log they go to the log file.
    |
    */
    'notifications' => (bool) env('FEATURE_NOTIFICATIONS', true),

    /*
    |--------------------------------------------------------------------------
    | SMS Notifications
    |--------------------------------------------------------------------------
    |
    | Master switch for Twilio SMS notifications. If false, SMS text
    | messages will not be sent to patients, even if they opt in.
    |
    */

    'sms_enabled' => env('FEATURE_SMS_ENABLED', false),

];
