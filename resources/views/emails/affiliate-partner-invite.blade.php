@extends('emails.layouts.luxury-dark')

@section('title', "You're in — " . $tenant->name . ' affiliate program')

@section('content')
    <h1>Welcome, {{ $partner->name }}!</h1>
    <p>
        <strong>{{ $tenant->name }}</strong> has invited you to their affiliate program.
        Your private creator portal is ready — no password required.
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Handle</span>
            <span class="metric-value">{{ $partner->handle ?? '—' }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Platform</span>
            <span class="metric-value">{{ ucfirst($partner->platform) }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Payout per qualified lead</span>
            <span class="metric-value" style="color: #0E9E8E; font-weight: 700;">
                {{ $partner->currency }} {{ number_format($partner->payout_cents / 100, 2) }}
            </span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $portalUrl }}" class="cta-btn">Open Your Creator Portal →</a>
    </div>

    <div class="note-box" style="margin-top: 24px;">
        ⭐ <strong>First step:</strong> open your portal and accept the program terms.
        Until you do, your tracking link is paused and clicks won't count toward payouts.
    </div>

    <p style="margin-top: 24px; font-size: 13px; color: #6B7280;">
        This link is unique to you — don't forward it. If you share it with another creator, their activity will be credited to your account.
    </p>
@endsection

@section('footer-note')
    This invitation was sent by {{ $tenant->name }} via the SymetriHealth affiliate platform.<br>
    If you believe you received this email by mistake, please ignore it.
@endsection
