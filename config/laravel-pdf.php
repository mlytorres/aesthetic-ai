<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default PDF Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "browsershot", "cloudflare"
    |
    | We use Cloudflare Browser Rendering in all environments. Set the env var
    | LARAVEL_PDF_DRIVER=cloudflare and supply the API credentials below.
    |
    */
    'driver' => env('LARAVEL_PDF_DRIVER', 'cloudflare'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Browser Rendering
    |--------------------------------------------------------------------------
    |
    | Required env vars:
    |   LARAVEL_PDF_CLOUDFLARE_API_TOKEN
    |   LARAVEL_PDF_CLOUDFLARE_ACCOUNT_ID
    |
    */
    'cloudflare' => [
        'api_token' => env('LARAVEL_PDF_CLOUDFLARE_API_TOKEN'),
        'account_id' => env('LARAVEL_PDF_CLOUDFLARE_ACCOUNT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Browsershot (local fallback — requires Puppeteer)
    |--------------------------------------------------------------------------
    */
    'browsershot' => [
        'node_binary' => env('LARAVEL_PDF_NODE_BINARY', 'node'),
        'npm_binary' => env('LARAVEL_PDF_NPM_BINARY', 'npm'),
        'node_modules_path' => env('LARAVEL_PDF_NODE_MODULES_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default options passed to every PDF render unless overridden per-call
    |--------------------------------------------------------------------------
    */
    'options' => [
        'format' => 'A4',
        'margin_top' => 0,
        'margin_right' => 0,
        'margin_bottom' => 0,
        'margin_left' => 0,
    ],

];
