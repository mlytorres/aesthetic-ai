@extends('emails.layouts.luxury-dark')

@section('title', 'Evaluation Usage Alert')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="color: #F5F0E8; font-size: 24px; font-weight: 400; margin: 0 0 16px 0; letter-spacing: -0.02em;">
            Evaluation Usage Alert
        </h1>
        <p style="color: #9B9B8E; margin: 0;">{{ $tenant->name }}</p>
    </div>

    <div class="note-box" style="border-left-color: #F97316; margin-bottom: 24px;">
        <strong style="color: #F97316;">Heads up!</strong> You have used <strong>{{ $percentUsed }}%</strong> of your
        monthly evaluation allowance. Once you reach 100%, new intake submissions will be paused
        until your plan resets or you upgrade.
    </div>

    <div style="background: rgba(255,255,255,0.05); border-radius: 99px; height: 10px; margin: 16px 0 8px; overflow: hidden;">
        <div style="height: 100%; border-radius: 99px; background: #C9A84C; width: {{ min($percentUsed, 100) }}%;"></div>
    </div>
    <p style="font-size: 12px; color: #9B9B8E; margin: 0 0 24px; text-align: center;">{{ $currentCount }} of {{ $limit }} evaluations used this month</p>

    <div style="display: flex; gap: 16px; margin-bottom: 24px;">
        <div style="flex: 1; background: #0A0A0F; border: 1px solid rgba(245, 240, 232, 0.05); border-radius: 8px; padding: 16px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #F5F0E8;">{{ $currentCount }}</div>
            <div style="font-size: 12px; color: #9B9B8E; margin-top: 4px;">Used this month</div>
        </div>
        <div style="flex: 1; background: #0A0A0F; border: 1px solid rgba(245, 240, 232, 0.05); border-radius: 8px; padding: 16px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #C9A84C;">{{ $limit - $currentCount }}</div>
            <div style="font-size: 12px; color: #9B9B8E; margin-top: 4px;">Remaining</div>
        </div>
        <div style="flex: 1; background: #0A0A0F; border: 1px solid rgba(245, 240, 232, 0.05); border-radius: 8px; padding: 16px; text-align: center;">
            <div style="font-size: 24px; font-weight: 600; color: #F5F0E8;">{{ $limit }}</div>
            <div style="font-size: 12px; color: #9B9B8E; margin-top: 4px;">Plan limit</div>
        </div>
    </div>

    <p style="text-align: center;">
        To avoid any interruption to your patient intake, consider upgrading your plan before
        you reach the limit.
    </p>

    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/clinic/billing" class="cta-btn">View Billing &amp; Upgrade</a>
    </div>
@endsection

@section('footer-note')
    This alert was sent because your account has crossed the 80% usage threshold.<br>
    To manage your subscription, visit your <a href="{{ config('app.url') }}/clinic/billing">billing page</a>.
@endsection
