<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Evaluation — {{ $procedure }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #F5F0E8;
            color: #1a1a1a;
            padding: 32px 16px;
        }
        .wrapper {
            max-width: 540px;
            margin: 0 auto;
        }
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
        .greeting {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .eval-card {
            background: #0A0A0F;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
        }
        .eval-card .procedure {
            font-size: 18px;
            font-weight: 600;
            color: #F5F0E8;
            margin-bottom: 16px;
        }
        .metric-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .metric-row:last-child {
            border-bottom: none;
        }
        .metric-label {
            font-size: 12px;
            color: #9B9B8E;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .metric-value {
            font-size: 14px;
            font-weight: 600;
            color: #F5F0E8;
        }
        .score-value {
            color: #0E9E8E;
            font-size: 20px;
        }
        .priority-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-urgent  { background: rgba(239,68,68,0.15); color: #f87171; }
        .priority-high    { background: rgba(249,115,22,0.15); color: #fb923c; }
        .priority-medium  { background: rgba(234,179,8,0.15);  color: #facc15; }
        .priority-standard{ background: rgba(155,155,142,0.15);color: #9B9B8E; }
        .cta {
            text-align: center;
            margin: 28px 0 20px;
        }
        .cta a {
            display: inline-block;
            background: #0E9E8E;
            color: #0A0A0F;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            padding: 14px 36px;
            border-radius: 8px;
            letter-spacing: 0.02em;
        }
        .note {
            font-size: 12px;
            color: #9B9B8E;
            line-height: 1.6;
            margin-top: 20px;
        }
        .footer {
            background: #0A0A0F;
            border-radius: 0 0 12px 12px;
            padding: 20px 32px;
            text-align: center;
        }
        .footer p {
            font-size: 11px;
            color: #9B9B8E;
            line-height: 1.8;
        }
        .footer a {
            color: #0E9E8E;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">

        <!-- Header -->
        <div class="header">
            <div class="logo-text">SymetriHealth</div>
            <div class="clinic-name">{{ $clinicName }}</div>
        </div>

        <!-- Body -->
        <div class="body">
            <p class="greeting">
                A new patient evaluation has completed AI analysis and is ready for your review.
            </p>

            <!-- Evaluation card -->
            <div class="eval-card">
                <div class="procedure">{{ $procedure }} Evaluation</div>

                <div class="metric-row">
                    <span class="metric-label">Patient</span>
                    <span class="metric-value">{{ $patientFirstName }}</span>
                </div>

                <div class="metric-row">
                    <span class="metric-label">Lead Score</span>
                    <span class="metric-value score-value">
                        {{ $leadScore ?? '—' }}<span style="font-size:12px; color:#9B9B8E;">/100</span>
                    </span>
                </div>

                <div class="metric-row">
                    <span class="metric-label">Priority</span>
                    <span class="metric-value">
                        <span class="priority-badge priority-{{ strtolower($priority) }}">
                            {{ $priority }}
                        </span>
                    </span>
                </div>

                <div class="metric-row">
                    <span class="metric-label">Submitted</span>
                    <span class="metric-value">{{ now()->format('M j, Y · g:i A') }}</span>
                </div>
            </div>

            <!-- CTA -->
            <div class="cta">
                <a href="{{ $magicUrl }}">Review Evaluation →</a>
            </div>

            <p class="note">
                This link grants you <strong>one-time direct access</strong> to the evaluation
                — no login required. It expires in <strong>15 minutes</strong>.<br><br>
                If the link has expired, log in at
                <a href="{{ config('app.url') }}" style="color:#0E9E8E;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
                and find the evaluation in your queue.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                SymetriHealth · HIPAA-Compliant Pre-Qualification Platform<br>
                You're receiving this because you're listed as a coordinator for {{ $clinicName }}.<br>
                <a href="{{ config('app.url') }}/clinic/settings">Manage notification preferences</a>
            </p>
        </div>

    </div>
</body>
</html>
