@extends('emails.layouts.luxury-dark')

@section('title', 'Payout update — ' . $tenant->name)

@section('content')
    <h1>Payout not approved</h1>
    <p>
        <strong>{{ $tenant->name }}</strong> has reviewed a payout associated with your affiliate account
        and was unable to approve it at this time.
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Amount</span>
            <span class="metric-value">{{ $formattedAmount }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Status</span>
            <span class="metric-value" style="color: #991B1B;">Not Approved</span>
        </div>
        @if($payout->rejection_reason)
        <div class="metric-row" style="align-items: flex-start;">
            <span class="metric-label">Reason</span>
            <span class="metric-value" style="text-align: right; max-width: 60%;">{{ $payout->rejection_reason }}</span>
        </div>
        @endif
    </div>

    <div style="text-align: center;">
        <a href="{{ $portalUrl }}" class="cta-btn">View Your Portal →</a>
    </div>

    <div class="note-box warning" style="margin-top: 24px;">
        If you believe this is an error or have questions, please reach out to {{ $tenant->name }} directly.
    </div>
@endsection

@section('footer-note')
    This notification was sent by {{ $tenant->name }} via the SymetriHealth affiliate platform.
@endsection
