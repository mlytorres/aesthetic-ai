@extends('emails.layouts.luxury-dark')

@section('content')
    <div style="text-align: center; margin-bottom: 28px;">
        <h1 style="margin: 0 0 12px 0;">Daily Follow-up Digest</h1>

        <div style="display: inline-block; background: #F0FDF9; border: 1px solid rgba(14, 158, 142, 0.2); border-radius: 10px; padding: 12px 28px;">
            <div style="color: #0E9E8E; font-size: 24px; font-weight: 700; line-height: 1;">
                {{ $evaluations->count() }} Patient{{ $evaluations->count() === 1 ? '' : 's' }}
            </div>
            <div style="color: #6B7280; font-size: 13px; margin-top: 4px;">
                Scheduled for today
            </div>
        </div>
    </div>

    <p>Hello Coordinator,</p>

    <p>Here are your patients scheduled for follow-up today at <strong>{{ $tenant->name }}</strong>.
    Please reach out to them and update their notes in the system.</p>

    <div style="margin: 28px 0;">
        @foreach($evaluations as $eval)
        <div style="margin-bottom: 12px; padding: 18px 20px; border: 1px solid #E5E7EB; border-radius: 10px; background: #F9FAFB;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding-bottom: 6px;">
                        <strong style="color: #111827; font-size: 15px;">{{ $eval->patient->name_encrypted }}</strong>
                    </td>
                    <td align="right" style="padding-bottom: 6px;">
                        <span style="color: #6B7280; font-size: 12px; background: #E5E7EB; padding: 3px 8px; border-radius: 4px;">
                            {{ ucwords(str_replace('_', ' ', $eval->status)) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="color: #6B7280; font-size: 14px; padding-bottom: 14px;">
                        Procedure: {{ $eval->procedure->label ?? 'Unknown' }}<br>
                        Phone: {{ $eval->patient->phone_encrypted ?? 'No phone' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <a href="{{ url('/clinic/patients/evaluations/' . $eval->id) }}"
                           style="display: inline-block; background: #FFFFFF; border: 1px solid #D1FAE5; color: #0E9E8E; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600;">
                            Open Patient Profile →
                        </a>
                    </td>
                </tr>
            </table>
        </div>
        @endforeach
    </div>

    <p style="color: #9CA3AF; font-size: 13px; text-align: center; margin-top: 32px;">
        These reminders reset when you update the Follow-up Date on the evaluation details page.
    </p>
@endsection
