<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\ContentSecurityPolicy;

test('normalizeParentOrigin accepts https origins with port', function (): void {
    expect(ContentSecurityPolicy::normalizeParentOrigin('https://www.example.com:8443'))
        ->toBe('https://www.example.com:8443');
});

test('normalizeParentOrigin prepends https when scheme omitted', function (): void {
    expect(ContentSecurityPolicy::normalizeParentOrigin('www.example.com'))
        ->toBe('https://www.example.com');
});

test('normalizeParentOrigin rejects invalid values', function (): void {
    expect(ContentSecurityPolicy::normalizeParentOrigin('not a url'))->toBeNull()
        ->and(ContentSecurityPolicy::normalizeParentOrigin('ftp://bad.example'))->toBeNull()
        ->and(ContentSecurityPolicy::normalizeParentOrigin('https://bad host'))->toBeNull();
});

test('parentOriginsForTenant dedupes and merges config extras', function (): void {
    config(['security.embed_parent_origins_extra' => ['https://extra.example']]);

    $tenant = Tenant::factory()->make([
        'settings' => [
            'embed_parent_origins' => [
                'https://a.example',
                'https://a.example',
                'https://b.example',
            ],
        ],
    ]);

    $origins = ContentSecurityPolicy::parentOriginsForTenant($tenant);

    expect($origins)->toEqualCanonicalizing([
        'https://a.example',
        'https://b.example',
        'https://extra.example',
    ]);
});

test('forIntakeEmbed uses frame-ancestors none when no origins', function (): void {
    $tenant = Tenant::factory()->make(['settings' => []]);

    $csp = ContentSecurityPolicy::forIntakeEmbed($tenant);

    expect($csp)->toContain("frame-ancestors 'none'");
});

test('forIntakeEmbed includes configured origins', function (): void {
    $tenant = Tenant::factory()->make([
        'settings' => [
            'embed_parent_origins' => ['https://parent.example'],
        ],
    ]);

    $csp = ContentSecurityPolicy::forIntakeEmbed($tenant);

    expect($csp)->toContain('frame-ancestors https://parent.example');
});

test('connect-src allows both ws and wss for Laravel Reverb', function (): void {
    config([
        'broadcasting.connections.reverb.options' => [
            'host' => 'localhost',
            'port' => 8080,
            'scheme' => 'https',
        ],
    ]);

    $csp = ContentSecurityPolicy::forApplication();

    expect($csp)->toContain('connect-src')
        ->and($csp)->toContain('ws://localhost:8080')
        ->and($csp)->toContain('wss://localhost:8080');
});
