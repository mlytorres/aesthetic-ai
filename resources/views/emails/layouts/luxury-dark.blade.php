<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', 'SymetriHealth')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #F3F4F6;
            color: #111827;
            padding: 32px 16px;
            -webkit-text-size-adjust: 100%;
        }

        /* ── Wrapper ─────────────────────────────────────────────────── */
        .wrapper {
            max-width: 580px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            overflow: hidden;
        }

        /* ── Header ──────────────────────────────────────────────────── */
        .header {
            background: #FFFFFF;
            padding: 28px 32px 24px;
            border-bottom: 1px solid #F3F4F6;
        }
        .header-inner {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-wordmark {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #0E9E8E;
            text-transform: uppercase;
        }
        .header .clinic-name {
            font-size: 12px;
            color: #6B7280;
            margin-top: 6px;
            letter-spacing: 0.03em;
        }

        /* ── Teal accent bar ─────────────────────────────────────────── */
        .accent-bar {
            height: 3px;
            background: linear-gradient(90deg, #0E9E8E 0%, #2DD4BF 100%);
        }

        /* ── Body ────────────────────────────────────────────────────── */
        .body {
            padding: 32px;
            line-height: 1.65;
            color: #374151;
        }
        .body p {
            margin-bottom: 16px;
            font-size: 15px;
            color: #374151;
        }
        .body h1 {
            font-size: 22px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .body h2 {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }
        .body h3 {
            font-size: 13px;
            font-weight: 600;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }

        /* ── CTA button ──────────────────────────────────────────────── */
        .cta-btn {
            display: inline-block;
            background: #0E9E8E;
            color: #FFFFFF !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 14px 32px;
            border-radius: 8px;
            margin: 24px 0;
            text-align: center;
            letter-spacing: 0.01em;
        }
        .cta-btn:hover { background: #0c8f80; }

        /* ── Outlined secondary button ───────────────────────────────── */
        .btn-outline {
            display: inline-block;
            border: 1.5px solid #0E9E8E;
            color: #0E9E8E !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            padding: 10px 22px;
            border-radius: 8px;
            letter-spacing: 0.01em;
        }

        /* ── Card ────────────────────────────────────────────────────── */
        .card {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }

        /* ── Metric rows ─────────────────────────────────────────────── */
        .metric-row {
            padding: 11px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .metric-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .metric-label {
            display: block;
            font-size: 11px;
            color: #9CA3AF;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 3px;
        }
        .metric-value {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #111827;
            word-break: break-all;
        }
        .metric-value a {
            color: #0E9E8E;
            text-decoration: none;
        }

        /* ── Steps list ──────────────────────────────────────────────── */
        .steps { margin: 0; padding: 0; list-style: none; }
        .steps li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: #374151;
            line-height: 1.6;
            padding: 12px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .steps li:last-child { border-bottom: none; }
        .step-num {
            min-width: 24px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(14, 158, 142, 0.1);
            border: 1px solid rgba(14, 158, 142, 0.3);
            color: #0E9E8E;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            line-height: 22px;
            margin-top: 1px;
            flex-shrink: 0;
        }

        /* ── Note / callout box ──────────────────────────────────────── */
        .note-box {
            font-size: 13px;
            color: #4B5563;
            background: #F9FAFB;
            border-left: 3px solid #0E9E8E;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            line-height: 1.6;
        }
        .note-box.warning {
            border-left-color: #F97316;
            background: #FFF7ED;
        }
        .note-box.danger {
            border-left-color: #EF4444;
            background: #FEF2F2;
        }

        /* ── Divider ─────────────────────────────────────────────────── */
        .divider {
            border: none;
            border-top: 1px solid #F3F4F6;
            margin: 24px 0;
        }

        /* ── HIPAA badge strip ───────────────────────────────────────── */
        .hipaa-strip {
            background: #F0FDF9;
            border: 1px solid rgba(14, 158, 142, 0.2);
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 12px;
            color: #0E9E8E;
            font-weight: 600;
            text-align: center;
            margin-bottom: 24px;
            letter-spacing: 0.03em;
        }

        /* ── Footer ──────────────────────────────────────────────────── */
        .footer {
            background: #F9FAFB;
            padding: 20px 32px;
            border-top: 1px solid #F3F4F6;
        }
        .footer p {
            font-size: 12px;
            color: #9CA3AF;
            line-height: 1.6;
            margin: 0;
            text-align: center;
        }
        .footer a {
            color: #0E9E8E;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        {{-- Header --}}
        <div class="header">
            <div class="header-inner">
                <span class="logo-wordmark">SymetriHealth</span>
            </div>
            @if(isset($clinicName) || isset($tenant))
                <div class="clinic-name">{{ $clinicName ?? $tenant->name ?? '' }}</div>
            @endif
        </div>

        {{-- Teal accent bar --}}
        <div class="accent-bar"></div>

        {{-- Content --}}
        <div class="body">
            @yield('content')
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>
                <strong style="color: #6B7280;">SymetriHealth</strong> &middot; HIPAA-Compliant Patient Management<br>
                @yield('footer-note', 'Secure &amp; Encrypted Operations')<br><br>
                <a href="{{ config('app.url') }}/legal/terms">Terms of Service</a>
                &nbsp;&middot;&nbsp;
                <a href="{{ config('app.url') }}/legal/privacy">Privacy Policy</a>
            </p>
        </div>

    </div>
</body>
</html>
