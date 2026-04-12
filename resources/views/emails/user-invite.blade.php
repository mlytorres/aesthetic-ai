<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to {{ $tenant->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F5F0E8;
            color: #1a1a1a;
            padding: 32px 16px;
        }
        .wrapper { max-width: 540px; margin: 0 auto; }
        .header {
            background: #0A0A0F;
            border-radius: 12px 12px 0 0;
            padding: 32px 32px 24px;
            text-align: center;
        }
        .header .logo-text {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #0E9E8E;
            text-transform: uppercase;
        }
        .header .clinic-name {
            font-size: 12px;
            color: #9B9B8E;
            margin-top: 4px;
            letter-spacing: 0.04em;
        }
        .body {
            background: #ffffff;
            padding: 32px;
        }
        .body h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0A0A0F;
            margin-bottom: 12px;
        }
        .body p {
            font-size: 15px;
            color: #444;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .credentials-box {
            background: #F5F0E8;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 24px 0;
        }
        .credentials-box .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 14px;
            border-bottom: 1px solid #e5e0d8;
            gap: 16px;
        }
        .credentials-box .row:last-child { border-bottom: none; }
        .credentials-box .label { color: #9B9B8E; font-weight: 500; }
        .credentials-box .value { color: #0A0A0F; font-weight: 600; font-family: monospace; }
        .cta {
            display: block;
            background: #0E9E8E;
            color: #0A0A0F;
            text-decoration: none;
            text-align: center;
            font-weight: 700;
            font-size: 15px;
            padding: 14px 32px;
            border-radius: 8px;
            margin: 24px 0;
            letter-spacing: 0.02em;
        }
        .note {
            font-size: 13px;
            color: #9B9B8E;
            background: #fafafa;
            border-left: 3px solid #0E9E8E;
            padding: 10px 14px;
            border-radius: 0 6px 6px 0;
        }
        .footer {
            background: #0A0A0F;
            border-radius: 0 0 12px 12px;
            padding: 20px 32px;
            text-align: center;
        }
        .footer p {
            font-size: 12px;
            color: #9B9B8E;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="logo-text">SymetriHealth</div>
            <div class="clinic-name">{{ $tenant->name }}</div>
        </div>

        <div class="body">
            <h1>Welcome, {{ $user->name }}!</h1>
            <p>
                You've been invited to join <strong>{{ $tenant->name }}</strong> on SymetriHealth.
                Your account has been created with the following credentials:
            </p>

            <div class="credentials-box">
                <div class="row">
                    <span class="label">Login URL</span>
                    <span class="value">{{ $loginUrl }}</span>
                </div>
                <div class="row">
                    <span class="label">Email</span>
                    <span class="value">{{ $user->email }}</span>
                </div>
                <div class="row">
                    <span class="label">Temporary Password</span>
                    <span class="value">{{ $temporaryPassword }}</span>
                </div>
                <div class="row">
                    <span class="label">Role</span>
                    <span class="value">{{ ucfirst($user->role) }}</span>
                </div>
            </div>

            <a href="{{ $loginUrl }}" class="cta">Log In to Your Dashboard →</a>

            <p class="note">
                ⚠️ For security, please change your password immediately after your first login
                via <strong>Settings → Security</strong>.
            </p>
        </div>

        <div class="footer">
            <p>
                This invitation was sent by the SymetriHealth platform.<br>
                If you did not expect this email, please ignore it.
            </p>
        </div>
    </div>
</body>
</html>
