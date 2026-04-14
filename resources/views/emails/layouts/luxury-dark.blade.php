<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SymetriHealth')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0A0A0F;
            color: #F5F0E8;
            padding: 32px 16px;
        }
        .wrapper {
            max-width: 580px;
            margin: 0 auto;
            background: #111116;
            border: 1px solid rgba(245, 240, 232, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        .header {
            background: #0A0A0F;
            padding: 32px 32px 24px;
            text-align: center;
            border-bottom: 1px solid rgba(245, 240, 232, 0.05);
        }
        .header .logo-text {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #C9A84C;
            text-transform: uppercase;
        }
        .header .clinic-name {
            font-size: 12px;
            color: #9B9B8E;
            margin-top: 4px;
            letter-spacing: 0.04em;
        }
        .body {
            padding: 32px;
            line-height: 1.6;
        }
        .body p {
            margin-bottom: 16px;
            font-size: 15px;
            color: #D5D0C8;
        }
        .body h1, .body h2, .body h3 {
            color: #F5F0E8;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .cta-btn {
            display: inline-block;
            background: #C9A84C;
            color: #0A0A0F;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            padding: 14px 32px;
            border-radius: 6px;
            margin: 24px 0;
            text-align: center;
            letter-spacing: 0.02em;
        }
        .footer {
            background: #0A0A0F;
            padding: 24px 32px;
            text-align: center;
            border-top: 1px solid rgba(245, 240, 232, 0.05);
        }
        .footer p {
            font-size: 12px;
            color: #9B9B8E;
            line-height: 1.6;
            margin: 0;
        }
        .footer a {
            color: #C9A84C;
            text-decoration: none;
        }
        /* Components */
        .card {
            background: #0A0A0F;
            border: 1px solid rgba(245, 240, 232, 0.05);
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        .metric-row {
            padding: 12px 0;
            border-bottom: 1px solid rgba(245, 240, 232, 0.05);
        }
        .metric-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .metric-label {
            display: block;
            font-size: 11px;
            color: #9B9B8E;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 4px;
        }
        .metric-value {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #F5F0E8;
            word-break: break-all;
        }
        .steps { margin: 0 0 20px; padding: 0; list-style: none; }
        .steps li { display: flex; align-items: flex-start; gap: 12px; font-size: 14px; color: #D5D0C8; line-height: 1.6; padding: 12px 0; border-bottom: 1px solid rgba(245, 240, 232, 0.05); }
        .steps li:last-child { border-bottom: none; }
        .step-num { min-width: 24px; width: 24px; height: 24px; border-radius: 50%; background: rgba(201, 168, 76, 0.1); border: 1px solid rgba(201, 168, 76, 0.3); color: #C9A84C; font-size: 12px; font-weight: 700; text-align: center; line-height: 22px; margin-top: 1px; }
        .divider { border: none; border-top: 1px solid rgba(245, 240, 232, 0.05); margin: 24px 0; }
        .note-box { font-size: 13px; color: #9B9B8E; background: rgba(255, 255, 255, 0.02); border-left: 3px solid #C9A84C; padding: 12px 16px; border-radius: 0 6px 6px 0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="logo-text">SymetriHealth</div>
            @if(isset($clinicName) || isset($tenant))
                <div class="clinic-name">{{ $clinicName ?? $tenant->name ?? '' }}</div>
            @endif
        </div>
        
        <div class="body">
            @yield('content')
        </div>

        <div class="footer">
            <p>
                SymetriHealth · HIPAA-Compliant Operations<br>
                @yield('footer-note', 'Secure & Encrypted Patient Management')
            </p>
        </div>
    </div>
</body>
</html>
