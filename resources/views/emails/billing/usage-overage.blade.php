@extends('emails.layouts.luxury-dark')

@section('content')
<div style="text-align: center; margin-bottom: 24px;">
    <h1 style="color: #F5F0E8; font-size: 24px; font-weight: 400; margin: 0 0 16px 0; letter-spacing: -0.02em;">
        Usage Limit Warning
    </h1>
    
    <div style="display: inline-block; background-color: rgba(201, 168, 76, 0.1); border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 8px; padding: 16px 24px;">
        <div style="color: #9B9B8E; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
            Current Evaluations
        </div>
        <div style="color: #C9A84C; font-size: 32px; font-weight: 300; line-height: 1;">
            {{ $currentUsage }} <span style="font-size: 16px; color: #9B9B8E;">/ {{ $limit }}</span>
        </div>
    </div>
</div>

<p>Hello,</p>

<p>Your clinic, <strong>{{ $tenant->name }}</strong>, has reached 80% of its monthly evaluation limit.</p>

<p>To ensure your patients can continue submitting intakes without interruption, we recommend upgrading your plan before reaching the {{ $limit }} evaluation cap. Once the limit is reached, new evaluations will be temporarily paused.</p>

<div style="text-align: center; margin: 32px 0;">
    <a href="{{ url('/clinic/billing') }}" 
       style="display: inline-block; background-color: #C9A84C; color: #0A0A0F; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-weight: 500; font-size: 14px;">
        Manage Billing & Upgrade
    </a>
</div>

<p style="color: #9B9B8E; font-size: 14px;">
    If you've recently upgraded, please disregard this notice while our systems synchronize.
</p>
@endsection
