@extends('emails.layouts.luxury-dark')

@section('title', 'Evaluation Received')

@section('content')
    <h1 style="color: #F5F0E8; font-size: 22px; margin-bottom: 24px; font-weight: 500;">Evaluation Received ✓</h1>
    <p>
        Hi {{ $firstName }},<br><br>
        We've successfully received your <strong>{{ $procedureLabel }}</strong> evaluation
        at <strong>{{ $clinicName }}</strong>. Our AI is now reviewing your submission —
        you'll receive your personalised Beauty Roadmap by email once the analysis
        is complete.
    </p>

    <div class="card">
        <h3 style="margin-top: 0; color: #C9A84C; font-size: 13px; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid rgba(245, 240, 232, 0.05); padding-bottom: 12px; margin-bottom: 0;">What happens next</h3>
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

    <p style="font-size:14px; text-align: center; margin-bottom: 24px;">
        You can track the live status of your evaluation and view your AI Simulation at any time via your secure patient portal:
    </p>
    <div style="text-align: center;">
        <a href="{{ url('/intake/portal/' . $secureToken) }}" class="cta-btn" style="margin: 0 0 24px 0;">
            Check My Status
        </a>
    </div>

    <p style="font-size: 14px;">
        Thank you for choosing <strong>{{ $clinicName }}</strong>. We look forward
        to supporting you on your aesthetic journey.
    </p>

    <p style="font-size: 14px; margin-top: 16px;">
        Warm regards,<br>
        <strong>{{ $clinicName }}</strong>
    </p>

    <hr class="divider"/>

    <p style="font-size: 11px; color: #9B9B8E; line-height: 1.6;">
        This is an automated confirmation. Do not reply to this email. All clinical
        findings will be discussed with a qualified surgeon during your consultation.
        Your information is protected under HIPAA.
    </p>
@endsection

@section('footer-note')
    Powered by SymetriHealth<br>
    You received this email because you submitted an aesthetic evaluation request.
@endsection
