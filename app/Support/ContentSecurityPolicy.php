<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Builds Content-Security-Policy header values shared by the app shell and intake embed.
 */
final class ContentSecurityPolicy
{
    /**
     * Normalize a user-provided string into a valid frame-ancestors token (origin only).
     * Returns null if the value cannot be represented as an http(s) origin.
     */
    public static function normalizeParentOrigin(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '://') && ! Str::startsWith($value, ['http://', 'https://'])) {
            return null;
        }

        if (! Str::startsWith($value, ['http://', 'https://'])) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $host = $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        $origin = $parts['scheme'].'://'.$host.$port;

        if (filter_var($origin, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $origin;
    }

    /**
     * CSP for standard Inertia pages (dashboard, auth, marketing).
     * Includes frame-ancestors 'none' to prevent framing (paired with X-Frame-Options: DENY).
     */
    public static function forApplication(): string
    {
        return self::baseDirectives()."frame-ancestors 'none'";
    }

    /**
     * Full CSP for tenant intake routes that may be embedded in a clinic site iframe.
     * frame-ancestors is restricted to configured parent origins, or 'none' when unset.
     */
    public static function forIntakeEmbed(Tenant $tenant): string
    {
        return self::baseDirectives().self::frameAncestorsDirective($tenant);
    }

    /**
     * @return list<string>
     */
    public static function parentOriginsForTenant(Tenant $tenant): array
    {
        $fromSettings = $tenant->settings['embed_parent_origins'] ?? [];

        if (! is_array($fromSettings)) {
            $fromSettings = [];
        }

        $extra = config('security.embed_parent_origins_extra', []);

        if (! is_array($extra)) {
            $extra = [];
        }

        $merged = array_merge($fromSettings, $extra);

        $normalized = [];

        foreach ($merged as $raw) {
            if (! is_string($raw)) {
                continue;
            }

            $origin = self::normalizeParentOrigin($raw);

            if ($origin !== null) {
                $normalized[$origin] = true;
            }
        }

        return array_keys($normalized);
    }

    private static function frameAncestorsDirective(Tenant $tenant): string
    {
        $origins = self::parentOriginsForTenant($tenant);

        if ($origins === []) {
            return "frame-ancestors 'none'";
        }

        return 'frame-ancestors '.implode(' ', $origins);
    }

    private static function baseDirectives(): string
    {
        $connect = ["'self'"];

        $appUrl = config('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $parsed = parse_url($appUrl);

            if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                $origin = $parsed['scheme'].'://'.$parsed['host'];

                if (isset($parsed['port'])) {
                    $origin .= ':'.$parsed['port'];
                }

                $connect[] = $origin;
            }
        }

        if (self::shouldIncludeViteDevSources()) {
            foreach (self::localViteDevOrigins() as $viteOrigin) {
                $connect[] = $viteOrigin;
            }

            foreach (self::localViteWebSocketOrigins() as $wsOrigin) {
                $connect[] = $wsOrigin;
            }
        }

        foreach (self::reverbWebSocketConnectOrigins() as $reverbUrl) {
            $connect[] = $reverbUrl;
        }

        $connect = array_values(array_unique($connect));

        $connectSrc = implode(' ', $connect);

        $viteCsp = self::shouldIncludeViteDevSources()
            ? ' '.implode(' ', self::localViteDevOrigins())
            : '';

        $scriptSrc = self::shouldIncludeViteDevSources()
            ? "'self' 'unsafe-inline' 'unsafe-eval'{$viteCsp}"
            : "'self' 'unsafe-inline'";

        $styleSrc = self::shouldIncludeViteDevSources()
            ? "'self' 'unsafe-inline' https://fonts.bunny.net{$viteCsp}"
            : "'self' 'unsafe-inline' https://fonts.bunny.net";

        // app.blade.php loads Instrument Sans from Bunny fonts CDN
        $parts = [
            "default-src 'self'; ",
            "base-uri 'self'; ",
            "object-src 'none'; ",
            "script-src {$scriptSrc}; ",
            "style-src {$styleSrc}; ",
            "img-src 'self' data: blob: https://*.amazonaws.com; ",
            "font-src 'self' data: https://fonts.bunny.net; ",
            'media-src blob: data:; ',
            "connect-src {$connectSrc}; ",
            "worker-src 'self' blob:; ",
        ];

        if (app()->isProduction()) {
            $parts[] = 'upgrade-insecure-requests; ';
        }

        return implode('', $parts);
    }

    /**
     * Vite dev server URLs for @vite assets. Localhost plus APP_URL host + :5173 so Herd + TLS
     * (laravel-vite-plugin detectTls) works — e.g. https://aesthetic-ai.test:5173.
     *
     * @return list<string>
     */
    private static function localViteDevOrigins(): array
    {
        $origins = [
            'http://127.0.0.1:5173',
            'http://localhost:5173',
            'https://127.0.0.1:5173',
            'https://localhost:5173',
        ];

        $appUrl = config('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $parsed = parse_url($appUrl);

            if (is_array($parsed) && isset($parsed['host'])) {
                $host = $parsed['host'];
                $origins[] = 'http://'.$host.':5173';
                $origins[] = 'https://'.$host.':5173';
            }
        }

        $extra = config('security.csp_vite_dev_origins_extra', []);

        if (is_array($extra)) {
            foreach ($extra as $o) {
                if (is_string($o) && $o !== '') {
                    $origins[] = rtrim($o, '/');
                }
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * WebSocket origins for Vite client / HMR when the dev server uses a TLS host.
     *
     * @return list<string>
     */
    private static function localViteWebSocketOrigins(): array
    {
        $origins = [
            'ws://127.0.0.1:5173',
            'ws://localhost:5173',
            'wss://127.0.0.1:5173',
            'wss://localhost:5173',
        ];

        $appUrl = config('app.url');

        if (is_string($appUrl) && $appUrl !== '') {
            $parsed = parse_url($appUrl);

            if (is_array($parsed) && isset($parsed['host'])) {
                $host = $parsed['host'];
                $origins[] = 'ws://'.$host.':5173';
                $origins[] = 'wss://'.$host.':5173';
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * Reverb / Echo may connect with ws or wss (see {@see resources/js/hooks/use-echo.ts} forceTLS).
     * CSP must allow both; allowing only one scheme blocks the other.
     *
     * @return list<string>
     */
    private static function reverbWebSocketConnectOrigins(): array
    {
        $urls = [];

        $optionSets = [
            config('broadcasting.connections.reverb.options', []),
            config('reverb.apps.apps.0.options', []),
        ];

        foreach ($optionSets as $opts) {
            if (! is_array($opts)) {
                continue;
            }

            $host = $opts['host'] ?? null;

            if (! is_string($host) || $host === '') {
                continue;
            }

            $port = $opts['port'] ?? null;
            $port = is_numeric($port) ? (int) $port : null;
            $suffix = self::websocketPortSuffix($port);

            $urls[] = 'ws://'.$host.$suffix;
            $urls[] = 'wss://'.$host.$suffix;
        }

        $viteHost = env('VITE_REVERB_HOST');

        if (is_string($viteHost) && $viteHost !== '') {
            $vitePort = env('VITE_REVERB_PORT');
            $port = is_numeric($vitePort) ? (int) $vitePort : 8080;
            $suffix = self::websocketPortSuffix($port);

            $urls[] = 'ws://'.$viteHost.$suffix;
            $urls[] = 'wss://'.$viteHost.$suffix;
        }

        return array_values(array_unique($urls));
    }

    private static function websocketPortSuffix(?int $port): string
    {
        if ($port === null) {
            return '';
        }

        if (in_array($port, [80, 443], true)) {
            return '';
        }

        return ':'.$port;
    }

    private static function shouldIncludeViteDevSources(): bool
    {
        return app()->environment('local');
    }
}
