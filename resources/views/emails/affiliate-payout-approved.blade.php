@extends('emails.layouts.luxury-dark')

@section('title', 'Payout approved — ' . $tenant->name)

@section('content')
    <h1>Your payout has been approved!</h1>
    <p>
        <strong>{{ $tenant->name }}</strong> has approved a payout for your affiliate activity.
        The payment is being prepared for release.
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Amount</span>
            <span class="metric-value" style="color: #0E9E8E; font-weight: 700;">{{ $formattedAmount }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Status</span>
            <span class="metric-value">Approved</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $portalUrl }}" class="cta-btn">View Your Portal →</a>
    </div>

    <div class="note-box" style="margin-top: 24px;">
        Your payment will be released shortly. You can track the status of all your payouts in your creator portal.
    </div>
@endsection

@section('footer-note')
    This notification was sent by {{ $tenant->name }} via the SymetriHealth affiliate platform.
@endsection
