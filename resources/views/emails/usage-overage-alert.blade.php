@extends('emails.layouts.luxury-dark')

@section('title', 'Evaluation Usage Alert')

@section('content')
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="margin: 0 0 8px 0;">Evaluation Usage Alert</h1>
        <p style="color: #6B7280; margin: 0; font-size: 14px;">{{ $tenant->name }}</p>
    </div>

    <div class="note-box warning" style="margin-bottom: 24px;">
        <strong>Heads up!</strong> You have used <strong>{{ $percentUsed }}%</strong> of your monthly
        evaluation allowance. Once you reach 100%, new intake submissions will be paused until your
        plan resets or you upgrade.
    </div>

    {{-- Progress bar --}}
    <div style="background: #E5E7EB; border-radius: 99px; height: 10px; margin: 0 0 8px; overflow: hidden;">
        <div style="height: 100%; border-radius: 99px; background: linear-gradient(90deg, #0E9E8E, #2DD4BF); width: {{ min($percentUsed, 100) }}%;"></div>
    </div>
    <p style="font-size: 12px; color: #9CA3AF; margin: 0 0 28px; text-align: center;">
        {{ $currentCount }} of {{ $limit }} evaluations used this month
    </p>

    {{-- Stats --}}
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">
        <tr>
            <td width="33%" style="padding-right: 8px;">
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #111827;">{{ $currentCount }}</div>
                    <div style="font-size: 12px; color: #9CA3AF; margin-top: 4px;">Used this month</div>
                </div>
            </td>
            <td width="33%" style="padding: 0 4px;">
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #0E9E8E;">{{ $limit - $currentCount }}</div>
                    <div style="font-size: 12px; color: #9CA3AF; margin-top: 4px;">Remaining</div>
                </div>
            </td>
            <td width="33%" style="padding-left: 8px;">
                <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: #111827;">{{ $limit }}</div>
                    <div style="font-size: 12px; color: #9CA3AF; margin-top: 4px;">Plan limit</div>
                </div>
            </td>
        </tr>
    </table>

    <p style="text-align: center; color: #6B7280;">
        To avoid any interruption to your patient intake, consider upgrading your plan before reaching the limit.
    </p>

    <div style="text-align: center;">
        <a href="{{ config('app.url') }}/clinic/billing" class="cta-btn">View Billing &amp; Upgrade</a>
    </div>
@endsection

@section('footer-note')
    This alert was sent because your account crossed the 80% usage threshold.<br>
    <a href="{{ config('app.url') }}/clinic/billing">Manage your subscription</a>
@endsection
