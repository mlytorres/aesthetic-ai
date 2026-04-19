@extends('emails.layouts.luxury-dark')

@section('title', "You're invited to " . $tenant->name)

@section('content')
    <h1>Welcome, {{ $user->name }}!</h1>
    <p>
        You've been invited to join <strong>{{ $tenant->name }}</strong> on SymetriHealth.
        Your account has been created with the following credentials:
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Login URL</span>
            <span class="metric-value"><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Email</span>
            <span class="metric-value">{{ $user->email }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Temporary Password</span>
            <span class="metric-value" style="font-family: 'Courier New', monospace; background: #F3F4F6; padding: 2px 6px; border-radius: 4px; font-size: 13px;">{{ $temporaryPassword }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Role</span>
            <span class="metric-value">{{ ucfirst($user->role) }}</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $loginUrl }}" class="cta-btn">Log In to Your Dashboard →</a>
    </div>

    <div class="note-box warning" style="margin-top: 24px;">
        ⚠️ <strong>Security reminder:</strong> please change your password immediately after your first login
        via <strong>Settings → Security</strong>.
    </div>
@endsection

@section('footer-note')
    This invitation was sent by the SymetriHealth platform.<br>
    If you did not expect this email, please ignore it.
@endsection
