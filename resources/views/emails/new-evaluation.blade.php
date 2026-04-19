@extends('emails.layouts.luxury-dark')

@section('title', 'New Evaluation — ' . $procedure)

@section('content')
    <h1>New evaluation ready for review</h1>
    <p>
        A new patient evaluation has completed AI analysis and is ready for your review.
    </p>

    <div class="card">
        <h3>{{ $procedure }} Evaluation</h3>

        <div class="metric-row">
            <span class="metric-label">Patient</span>
            <span class="metric-value">{{ $patientFirstName }}</span>
        </div>

        <div class="metric-row">
            <span class="metric-label">Lead Score</span>
            <span class="metric-value" style="color: #0E9E8E; font-size: 20px; font-weight: 700;">
                {{ $leadScore ?? '—' }}<span style="font-size: 13px; color: #9CA3AF; font-weight: 400;">/100</span>
            </span>
        </div>

        <div class="metric-row">
            <span class="metric-label">Priority</span>
            <span class="metric-value" style="color: {{ strtolower($priority) === 'urgent' ? '#EF4444' : (strtolower($priority) === 'high' ? '#F97316' : '#374151') }}; font-weight: 600;">
                {{ ucfirst($priority) }}
            </span>
        </div>

        <div class="metric-row">
            <span class="metric-label">Submitted</span>
            <span class="metric-value">{{ now()->format('M j, Y · g:i A') }}</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $magicUrl }}" class="cta-btn">Review Evaluation →</a>
    </div>

    <p style="font-size: 13px; color: #6B7280; margin-top: 8px;">
        This link grants you <strong>one-time direct access</strong> to the evaluation — no login required.
        It expires in <strong>15 minutes</strong>.<br><br>
        If the link has expired, log in at
        <a href="{{ config('app.url') }}" style="color: #0E9E8E;">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</a>
        and find the evaluation in your queue.
    </p>
@endsection

@section('footer-note')
    You're receiving this because you're listed as a coordinator for {{ $clinicName }}.<br>
    <a href="{{ config('app.url') }}/clinic/settings">Manage notification preferences</a>
@endsection
