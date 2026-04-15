@extends('emails.layouts.luxury-dark')

@section('title', 'Your Consultation Is Scheduled')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #F5F0E8; font-size: 24px; font-weight: 400; margin: 0 0 16px 0; letter-spacing: -0.02em;">
            Your consultation is confirmed
        </h1>
        <p style="color: #9B9B8E; margin: 0;">{{ $clinicName }}</p>
    </div>

    <p style="margin: 0 0 20px;">
        Hi {{ $patientName }}, your video consultation has been scheduled. Here are the details:
    </p>

    <div class="note-box" style="border-left-color: #0E9E8E; margin-bottom: 24px;">
        <div style="margin-bottom: 8px;">
            <strong style="color: #F5F0E8;">📅 Date &amp; Time</strong><br>
            <span style="color: #F5F0E8;">{{ $formattedDate }}</span>
        </div>
        <div>
            <strong style="color: #F5F0E8;">⏱ Duration</strong><br>
            <span style="color: #9B9B8E;">{{ $durationLabel }}</span>
        </div>
    </div>

    <p style="margin: 0 0 8px; color: #9B9B8E; font-size: 14px;">
        When it's time for your consultation, click the button below to join the secure video call directly from your browser — no downloads required.
    </p>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{ $joinUrl }}" class="cta-btn">Join Video Consultation</a>
    </div>

    <p style="font-size: 12px; color: #9B9B8E; text-align: center; margin: 0;">
        Or copy this link: <span style="color: #F5F0E8; font-family: monospace;">{{ $joinUrl }}</span>
    </p>
@endsection

@section('footer-note')
    This invitation was sent because a consultation was scheduled for you at {{ $clinicName }}.<br>
    If you have questions, please contact your clinic directly.
@endsection
