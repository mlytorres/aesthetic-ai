@extends('emails.layouts.luxury-dark')

@section('title', 'Payment sent — ' . $tenant->name)

@section('content')
    <h1>Your payment is on its way!</h1>
    <p>
        <strong>{{ $tenant->name }}</strong> has released your affiliate payout.
        Please allow a few business days for the funds to arrive depending on the payment method.
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Amount Released</span>
            <span class="metric-value" style="color: #0E9E8E; font-weight: 700;">{{ $formattedAmount }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Status</span>
            <span class="metric-value">Released</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Released On</span>
            <span class="metric-value">{{ $payout->released_at?->format('M j, Y') ?? now()->format('M j, Y') }}</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $portalUrl }}" class="cta-btn">View Your Portal →</a>
    </div>

    <p style="margin-top: 24px; font-size: 13px; color: #6B7280;">
        If you have questions about the payment amount or timing, please contact {{ $tenant->name }} directly.
    </p>
@endsection

@section('footer-note')
    This notification was sent by {{ $tenant->name }} via the SymetriHealth affiliate platform.
@endsection
