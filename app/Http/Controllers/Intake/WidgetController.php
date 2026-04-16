<?php

declare(strict_types=1);

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class WidgetController extends Controller
{
    /**
     * Serves the dynamically generated JavaScript widget.
     * Injecting the correct APP_URL based on environment.
     */
    public function show(): Response
    {
        $appUrl = config('app.url');
        
        // Strip scheme for multi-tenant subdomain construction if needed,
        // or just use the base domain.
        $domain = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?? 'https';

        $js = <<<JS
(function() {
    const script = document.currentScript;
    const clinic = script.getAttribute('data-clinic');
    const containerId = script.getAttribute('data-container');
    const height = script.getAttribute('data-height') || '750px';

    if (!clinic) {
        console.error('AestheticAI Error: data-clinic attribute is required');
        return;
    }

    const container = containerId ? document.getElementById(containerId) : null;
    const target = container || script.parentNode;

    const iframe = document.createElement('iframe');
    
    // Construct the tenant-specific URL
    const url = '{$scheme}://' + clinic + '.{$domain}/intake?hide_header=true';

    iframe.src = url;
    iframe.style.width = '100%';
    iframe.style.height = height;
    iframe.style.border = 'none';
    iframe.style.overflow = 'hidden';
    iframe.setAttribute('scrolling', 'no');
    iframe.setAttribute('allow', 'camera;microhone'); // For future video consults

    target.appendChild(iframe);

    // Listen for completion events to allow parent-side tracking (GA, GTM, etc.)
    window.addEventListener('message', (event) => {
        // Only accept messages from our domain
        if (event.origin !== '{$scheme}://' + clinic + '.{$domain}') return;

        if (event.data?.type === 'EVALUATION_COMPLETE') {
            const detail = { clinic: event.data.clinic };
            const customEvent = new CustomEvent('aesthetic-ai:complete', { detail });
            document.dispatchEvent(customEvent);
            
            console.log('AestheticAI: Evaluation complete', detail);
        }
    });

    // Auto-resize if needed (future enhancement)
})();
JS;

        return response($js)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
