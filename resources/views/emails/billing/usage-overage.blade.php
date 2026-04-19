@extends('emails.layouts.luxury-dark')

@section('content')
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="margin: 0 0 8px 0;">Usage Limit Warning</h1>

        <div style="display: inline-block; background: #F0FDF9; border: 1px solid rgba(14, 158, 142, 0.2); border-radius: 10px; padding: 16px 28px; margin-top: 8px;">
            <div style="color: #6B7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 4px;">
                Current Evaluations
            </div>
            <div style="color: #0E9E8E; font-size: 32px; font-weight: 700; line-height: 1;">
                {{ $currentUsage }} <span style="font-size: 16px; color: #9CA3AF; font-weight: 400;">/ {{ $limit }}</span>
            </div>
        </div>
    </div>

    <p>Hello,</p>

    <p>Your clinic, <strong>{{ $tenant->name }}</strong>, has reached 80% of its monthly evaluation limit.</p>

    <p>To ensure your patients can continue submitting intakes without interruption, we recommend upgrading
    your plan before reaching the {{ $limit }} evaluation cap. Once the limit is reached, new evaluations
    will be temporarily paused.</p>

    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ url('/clinic/billing') }}" class="cta-btn">Manage Billing &amp; Upgrade</a>
    </div>

    <p style="color: #9CA3AF; font-size: 14px;">
        If you've recently upgraded, please disregard this notice while our systems synchronize.
    </p>
@endsection
