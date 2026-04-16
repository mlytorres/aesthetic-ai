(function() {
    console.log("AestheticAI Widget Loader Initializing...");

    // Find our script tag
    const scriptTags = document.querySelectorAll('script[src*="loader.js"]');
    const scriptTag = document.currentScript || scriptTags[scriptTags.length - 1];
    if (!scriptTag) {
        console.error("AestheticAI Widget: Could not find script tag.");
        return;
    }

    const domain = scriptTag.getAttribute('data-domain');
    const procedure = scriptTag.getAttribute('data-procedure');
    const theme = scriptTag.getAttribute('data-theme') || 'luxury-dark';
    const primaryColor = scriptTag.getAttribute('data-primary-color') || '#C9A84C';
    const fontFam = scriptTag.getAttribute('data-font-family') || 'system-ui, sans-serif';
    const language = scriptTag.getAttribute('data-language') || 'en';
    const renderMode = scriptTag.getAttribute('data-render-mode') || 'inline';
    const buttonLabel = scriptTag.getAttribute('data-button-label') || 'Start Free Evaluation';
    const hideHeader = scriptTag.getAttribute('data-hide-header') === 'true';

    if (!domain) {
        console.error("AestheticAI Widget: Missing data-domain attribute on script tag.");
        return;
    }

    // Build iframe URL
    let iframeUrl = domain + '/intake?embedded=true';
    if (theme) iframeUrl += '&theme=' + encodeURIComponent(theme);
    if (procedure) iframeUrl += '&procedure=' + encodeURIComponent(procedure);
    if (language) iframeUrl += '&lang=' + encodeURIComponent(language);
    if (primaryColor) iframeUrl += '&color=' + encodeURIComponent(primaryColor);
    if (fontFam) iframeUrl += '&font=' + encodeURIComponent(fontFam);
    if (hideHeader) iframeUrl += '&hide_header=true';

    // Helper: Create the iframe
    function createIframe() {
        const iframe = document.createElement('iframe');
        iframe.src = iframeUrl;
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.minHeight = '750px';
        iframe.style.border = 'none';
        iframe.style.borderRadius = '12px';
        iframe.style.backgroundColor = theme.includes('light') ? '#ffffff' : '#0A0A0F';
        iframe.allow = "camera *; microphone *";
        return iframe;
    }

    // Helper: Create Modal Overlay
    function openModal() {
        // Prevent multiple modals
        if (document.getElementById('aestheticai-modal')) return;

        const modal = document.createElement('div');
        modal.id = 'aestheticai-modal';
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.zIndex = '2147483647'; // Max z-index
        modal.style.backgroundColor = 'rgba(0,0,0,0.7)';
        modal.style.backdropFilter = 'blur(4px)';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.padding = '20px';
        modal.style.opacity = '0';
        modal.style.transition = 'opacity 0.2s ease-in-out';
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.position = 'absolute';
        closeBtn.style.top = '20px';
        closeBtn.style.right = '20px';
        closeBtn.style.background = 'transparent';
        closeBtn.style.border = 'none';
        closeBtn.style.color = '#fff';
        closeBtn.style.fontSize = '36px';
        closeBtn.style.cursor = 'pointer';
        closeBtn.style.zIndex = '10';
        closeBtn.onclick = () => {
            modal.style.opacity = '0';
            setTimeout(() => document.body.removeChild(modal), 200);
        };

        const iframeContainer = document.createElement('div');
        iframeContainer.style.width = '100%';
        iframeContainer.style.maxWidth = '600px';
        iframeContainer.style.height = '85vh';
        iframeContainer.style.maxHeight = '900px';
        iframeContainer.style.position = 'relative';
        iframeContainer.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.5)';
        iframeContainer.style.borderRadius = '12px';
        iframeContainer.style.overflow = 'hidden';

        iframeContainer.appendChild(createIframe());
        modal.appendChild(closeBtn);
        modal.appendChild(iframeContainer);
        document.body.appendChild(modal);

        // trigger fade in
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
        });
    }

    // Determine target container (if there is one defined in HTML before script)
    let container = document.getElementById('aestheticai-widget');

    if (renderMode === 'inline') {
        if (!container) {
            container = document.createElement('div');
            container.style.width = '100%';
            container.style.minHeight = '750px';
            scriptTag.parentNode.insertBefore(container, scriptTag.nextSibling);
        }
        container.style.minHeight = '750px';
        container.innerHTML = '';
        container.appendChild(createIframe());

    } else if (renderMode === 'button-modal') {
        const btn = document.createElement('button');
        btn.innerText = buttonLabel;
        btn.style.backgroundColor = primaryColor;
        btn.style.color = '#000000';
        btn.style.fontFamily = fontFam;
        btn.style.padding = '12px 24px';
        btn.style.border = 'none';
        btn.style.borderRadius = '6px';
        btn.style.fontWeight = '600';
        btn.style.fontSize = '16px';
        btn.style.cursor = 'pointer';
        btn.style.boxShadow = '0 4px 6px -1px rgba(0, 0, 0, 0.1)';
        btn.onclick = openModal;

        if (container) {
            container.appendChild(btn);
        } else {
            scriptTag.parentNode.insertBefore(btn, scriptTag.nextSibling);
        }

    } else if (renderMode === 'fab') {
        const fab = document.createElement('button');
        fab.innerText = buttonLabel;
        fab.style.position = 'fixed';
        fab.style.bottom = '24px';
        fab.style.right = '24px';
        fab.style.zIndex = '2147483646';
        fab.style.backgroundColor = primaryColor;
        fab.style.color = '#000000';
        fab.style.fontFamily = fontFam;
        fab.style.padding = '14px 28px';
        fab.style.border = 'none';
        fab.style.borderRadius = '9999px';
        fab.style.fontWeight = '600';
        fab.style.fontSize = '16px';
        fab.style.cursor = 'pointer';
        fab.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
        fab.style.transition = 'transform 0.2s';
        fab.onmouseover = () => fab.style.transform = 'scale(1.05)';
        fab.onmouseout = () => fab.style.transform = 'scale(1)';
        fab.onclick = openModal;
        document.body.appendChild(fab);
    }

    // ─── Post-Message Communication ───────────────────────────────────────────
    window.addEventListener('message', function(event) {
        // Basic security: Check that message is from our expected domain
        // In production, you might want to be more strict here
        if (!event.data || typeof event.data !== 'object') return;

        const { type, data } = event.data;

        // Parent window event dispatcher
        if (typeof window.onAestheticAIEvent === 'function') {
            window.onAestheticAIEvent(event.data);
        }

        // Standard Behaviors
        if (type === 'EVALUATION_COMPLETE') {
            console.log("AestheticAI: Evaluation complete event received.");
            // If we are in a modal, maybe we don't redirect but show a success message?
            // success.tsx handles the redirect if booking_url is present.
        }
        
        if (type === 'LEAD_CAPTURED') {
            console.log("AestheticAI: Lead data captured.", event.data.email);
        }
    });
})();
