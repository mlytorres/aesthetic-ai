@extends('emails.layouts.luxury-dark')

@section('title', 'Your Consultation Is Scheduled')

@section('content')
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="margin: 0 0 8px 0;">Your consultation is confirmed</h1>
        <p style="color: #6B7280; margin: 0; font-size: 14px;">{{ $clinicName }}</p>
    </div>

    <p>
        Hi {{ $patientName }}, your video consultation has been scheduled. Here are the details:
    </p>

    <div class="note-box" style="margin-bottom: 24px;">
        <div style="margin-bottom: 10px;">
            <strong style="color: #111827;">📅 Date &amp; Time</strong><br>
            <span style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedDate }}</span>
        </div>
        <div>
            <strong style="color: #111827;">⏱ Duration</strong><br>
            <span style="color: #6B7280;">{{ $durationLabel }}</span>
        </div>
    </div>

    <p style="font-size: 14px; color: #6B7280;">
        When it's time for your consultation, click the button below to join the secure video call directly
        from your browser — no downloads required.
    </p>

    <div style="text-align: center; margin: 28px 0;">
        <a href="{{ $joinUrl }}" class="cta-btn">Join Video Consultation</a>
    </div>

    <p style="font-size: 12px; color: #9CA3AF; text-align: center; margin: 0;">
        Or copy this link: <span style="color: #374151; font-family: 'Courier New', monospace;">{{ $joinUrl }}</span>
    </p>
@endsection

@section('footer-note')
    This invitation was sent because a consultation was scheduled for you at {{ $clinicName }}.<br>
    If you have questions, please contact your clinic directly.
@endsection
