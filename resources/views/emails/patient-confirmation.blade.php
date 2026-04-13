<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Evaluation Received</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .header { background: #1a1a24; padding: 28px 32px 22px; }
        .header-eyebrow { font-size: 10px; text-transform: uppercase; letter-spacing: 2px; color: #0E9E8E; margin-bottom: 8px; }
        .header-title { font-size: 22px; font-weight: 700; color: #F5F0E8; line-height: 1.25; }
        .header-sub { font-size: 12px; color: #9B9B8E; margin-top: 4px; }
        .body { padding: 28px 32px; }
        .greeting { font-size: 15px; color: #333; line-height: 1.65; margin-bottom: 20px; }
        .card { background: #f0faf9; border: 1px solid #b2deda; border-radius: 6px; padding: 16px 18px; margin-bottom: 20px; }
        .card-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #0E9E8E; margin-bottom: 8px; }
        .card-text { font-size: 13px; color: #444; line-height: 1.6; }
        .steps { margin: 0 0 20px; padding: 0; list-style: none; }
        .steps li { display: flex; align-items: flex-start; gap: 12px; font-size: 13px; color: #444; line-height: 1.6; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .steps li:last-child { border-bottom: none; }
        .step-num { min-width: 24px; width: 24px; height: 24px; border-radius: 50%; background: #0E9E8E; color: #fff; font-size: 11px; font-weight: 700; text-align: center; line-height: 24px; margin-top: 1px; }
        .divider { border: none; border-top: 1px solid #eee; margin: 20px 0; }
        .footer { background: #f9f9f9; padding: 16px 32px; border-top: 1px solid #eee; }
        .footer-text { font-size: 10px; color: #aaa; text-align: center; line-height: 1.6; }
        .badge { display: inline-block; background: #0E9E8E15; border: 1px solid #0E9E8E40; color: #0E9E8E; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 3px 8px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <div class="header-eyebrow">{{ $clinicName }}</div>
        <div class="header-title">Evaluation Received ✓</div>
        <div class="header-sub">{{ $procedureLabel }} — Submission Confirmed</div>
    </div>

    <div class="body">
        <div class="greeting">
            Hi {{ $firstName }},<br><br>
            We've successfully received your <strong>{{ $procedureLabel }}</strong> evaluation
            at <strong>{{ $clinicName }}</strong>. Our AI is now reviewing your submission —
            you'll receive your personalised Beauty Roadmap by email once the analysis
            is complete.
        </div>

        <div class="card">
            <div class="card-title">What happens next</div>
            <ul class="steps">
                <li>
                    <span class="step-num">1</span>
                    <span>Our AI analyses your photos and quiz responses — this typically takes just a few minutes.</span>
                </li>
                <li>
                    <span class="step-num">2</span>
                    <span>You'll receive a personalised Beauty Roadmap report by email with your results and insights.</span>
                </li>
                <li>
                    <span class="step-num">3</span>
                    <span>A member of our clinical team will reach out within <strong>1–2 business days</strong> to discuss your results and schedule a consultation.</span>
                </li>
            </ul>
        </div>

        <hr class="divider"/>

        <p style="font-size:13px; color:#555; line-height:1.6; margin-bottom: 6px;">
            If you have any questions in the meantime, please contact us directly and
            reference your submission ID:
        </p>
        <p style="margin: 0 0 20px;">
            <span class="badge">{{ strtoupper(substr($secureToken, 0, 12)) }}</span>
        </p>

        <p style="font-size:13px; color:#555; line-height:1.6;">
            Thank you for choosing <strong>{{ $clinicName }}</strong>. We look forward
            to supporting you on your aesthetic journey.
        </p>

        <p style="font-size:13px; color:#555; margin-top:14px;">
            Warm regards,<br>
            <strong>{{ $clinicName }}</strong>
        </p>

        <hr class="divider"/>

        <p style="font-size:10px; color:#bbb; line-height:1.6;">
            This is an automated confirmation. Do not reply to this email. All clinical
            findings will be discussed with a qualified surgeon during your consultation.
            Your information is protected under HIPAA.
        </p>
    </div>

    <div class="footer">
        <div class="footer-text">
            {{ $clinicName }} &bull; Powered by SymetriHealth<br>
            You received this email because you submitted an aesthetic evaluation request.
        </div>
    </div>

</div>
</body>
</html>
