<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluation Usage Alert</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .wrapper { max-width: 580px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #1a1a24; padding: 32px 36px 28px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; color: #f5f0e8; letter-spacing: 0.3px; }
        .header p { margin: 6px 0 0; font-size: 13px; color: #9b9b8e; }
        .body { padding: 32px 36px; }
        .alert-box { background: #fff8e6; border: 1px solid #f0c050; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; }
        .alert-box p { margin: 0; font-size: 14px; color: #856404; }
        .progress-bar-wrap { background: #eee; border-radius: 99px; height: 10px; margin: 16px 0 8px; overflow: hidden; }
        .progress-bar-fill { height: 100%; border-radius: 99px; background: #f0c050; }
        .progress-label { font-size: 12px; color: #666; margin: 0 0 24px; }
        .stat-row { display: flex; gap: 16px; margin-bottom: 24px; }
        .stat-box { flex: 1; background: #f9f9f9; border-radius: 6px; padding: 16px; text-align: center; }
        .stat-box .num { font-size: 28px; font-weight: 700; color: #1a1a24; }
        .stat-box .lbl { font-size: 12px; color: #888; margin-top: 4px; }
        .cta-btn { display: inline-block; background: #0E9E8E; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; margin-top: 8px; }
        .footer { background: #f9f9f9; padding: 20px 36px; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Evaluation Usage Alert</h1>
        <p>{{ $tenant->name }}</p>
    </div>

    <div class="body">
        <div class="alert-box">
            <p>
                <strong>Heads up!</strong> You have used <strong>{{ $percentUsed }}%</strong> of your
                monthly evaluation allowance. Once you reach 100%, new intake submissions will be paused
                until your plan resets or you upgrade.
            </p>
        </div>

        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width: {{ min($percentUsed, 100) }}%"></div>
        </div>
        <p class="progress-label">{{ $currentCount }} of {{ $limit }} evaluations used this month</p>

        <div class="stat-row">
            <div class="stat-box">
                <div class="num">{{ $currentCount }}</div>
                <div class="lbl">Used this month</div>
            </div>
            <div class="stat-box">
                <div class="num">{{ $limit - $currentCount }}</div>
                <div class="lbl">Remaining</div>
            </div>
            <div class="stat-box">
                <div class="num">{{ $limit }}</div>
                <div class="lbl">Plan limit</div>
            </div>
        </div>

        <p style="font-size: 14px; color: #444; margin: 0 0 20px;">
            To avoid any interruption to your patient intake, consider upgrading your plan before
            you reach the limit.
        </p>

        <a href="{{ config('app.url') }}/clinic/billing" class="cta-btn">View Billing &amp; Upgrade</a>
    </div>

    <div class="footer">
        This alert was sent because your account has crossed the 80% usage threshold.
        You will only receive this once per billing period.
        To manage your subscription, visit your <a href="{{ config('app.url') }}/clinic/billing" style="color:#0E9E8E;">billing page</a>.
    </div>
</div>
</body>
</html>
