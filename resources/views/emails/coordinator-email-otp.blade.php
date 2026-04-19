@extends('emails.layouts.luxury-dark')

@section('title', 'Security Verification Code')

@section('clinic')
    Account Security
@endsection

@section('content')
    <h1>Security verification code</h1>

    <p>
        Hi {{ $user->name }}, here is your quick login code.
    </p>

    <p>
        Enter this 6-digit code on the verification screen to continue to your clinic dashboard.
    </p>

    <div class="card" style="text-align: center;">
        <div class="metric-label">Verification Code</div>
        <div class="metric-value" style="font-size: 28px; letter-spacing: 0.25em; font-weight: 700;">
            {{ $code }}
        </div>
    </div>

    <p>
        This code expires at {{ $expiresAt->timezone(config('app.timezone'))->format('g:i A T') }}.
    </p>

    <div class="note-box">
        If the code does not work, request a new one from the same verification page and use the newest email.
    </div>

    <div class="note-box warning">
        Never share this code with anyone. SymetriHealth support will never ask for it.
    </div>
@endsection
