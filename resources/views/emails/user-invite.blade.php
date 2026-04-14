@extends('emails.layouts.luxury-dark')

@section('title', "You're invited to " . $tenant->name)

@section('content')
    <h1 style="color: #F5F0E8; font-size: 22px; margin-bottom: 12px; font-weight: 500;">Welcome, {{ $user->name }}!</h1>
    <p>
        You've been invited to join <strong>{{ $tenant->name }}</strong> on SymetriHealth.
        Your account has been created with the following credentials:
    </p>

    <div class="card">
        <div class="metric-row">
            <span class="metric-label">Login URL</span>
            <span class="metric-value" style="color: #C9A84C;"><a href="{{ $loginUrl }}" style="color: #C9A84C; text-decoration: none;">{{ $loginUrl }}</a></span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Email</span>
            <span class="metric-value">{{ $user->email }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Temporary Password</span>
            <span class="metric-value" style="font-family: monospace;">{{ $temporaryPassword }}</span>
        </div>
        <div class="metric-row">
            <span class="metric-label">Role</span>
            <span class="metric-value">{{ ucfirst($user->role) }}</span>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="{{ $loginUrl }}" class="cta-btn">Log In to Your Dashboard →</a>
    </div>

    <div class="note-box" style="margin-top: 24px;">
        ⚠️ For security, please change your password immediately after your first login
        via <strong>Settings → Security</strong>.
    </div>
@endsection

@section('footer-note')
    This invitation was sent by the SymetriHealth platform.<br>
    If you did not expect this email, please ignore it.
@endsection
