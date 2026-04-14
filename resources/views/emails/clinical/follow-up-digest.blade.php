@extends('emails.layouts.luxury-dark')

@section('content')
<div style="text-align: center; margin-bottom: 24px;">
    <h1 style="color: #F5F0E8; font-size: 24px; font-weight: 400; margin: 0 0 16px 0; letter-spacing: -0.02em;">
        Daily Follow-up Digest
    </h1>
    
    <div style="display: inline-block; background-color: rgba(14, 158, 142, 0.1); border: 1px solid rgba(14, 158, 142, 0.2); border-radius: 8px; padding: 12px 24px;">
        <div style="color: #0E9E8E; font-size: 20px; font-weight: 300; line-height: 1;">
            {{ $evaluations->count() }} Patient{{ $evaluations->count() === 1 ? '' : 's' }}
        </div>
        <div style="color: #9B9B8E; font-size: 13px; margin-top: 4px;">
            Scheduled for today
        </div>
    </div>
</div>

<p>Hello Coordinator,</p>

<p>Here are your patients scheduled for follow-up today at <strong>{{ $tenant->name }}</strong>. Please reach out to them and update their notes in the system.</p>

<div style="margin: 32px 0;">
    @foreach($evaluations as $eval)
    <div style="margin-bottom: 16px; padding: 20px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; background-color: #0A0A0F;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="padding-bottom: 8px;">
                    <strong style="color: #F5F0E8; font-size: 16px;">{{ $eval->patient->name_encrypted }}</strong>
                </td>
                <td align="right" style="padding-bottom: 8px;">
                    <span style="color: #9B9B8E; font-size: 12px; background: rgba(255, 255, 255, 0.05); padding: 4px 8px; border-radius: 4px;">{{ ucwords(str_replace('_', ' ', $eval->status)) }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="color: #9B9B8E; font-size: 14px; padding-bottom: 16px;">
                    Procedure: {{ $eval->procedure->label ?? 'Unknown' }}<br>
                    Phone: {{ $eval->patient->phone_encrypted ?? 'No phone' }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <a href="{{ url('/clinic/patients/evaluations/' . $eval->id) }}" style="display: inline-block; background-color: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #F5F0E8; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 13px; font-weight: 500;">
                        Open Patient Profile
                    </a>
                </td>
            </tr>
        </table>
    </div>
    @endforeach
</div>

<p style="color: #9B9B8E; font-size: 14px; text-align: center; margin-top: 40px;">
    These reminders reset when you update the Follow-up Date on the evaluation details page.
</p>
@endsection
