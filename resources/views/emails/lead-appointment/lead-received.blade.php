@extends('emails.layout')

@section('title', 'We received your request')
@section('preheader', 'Our team will contact you within one business day.')
@section('heading', 'We received your request')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $lead->firstName }},</p>

    <p style="margin:0 0 18px 0;">
        Thank you for contacting {{ config('app.name') }}. We received your request for
        <strong>a roof inspection</strong>
        @if ($lead->addressLine !== '')
            at {{ $lead->addressLine }}
        @endif
        and our team will contact you within one business day.
    </p>

    @include('emails.partials.detail', ['label' => 'Property', 'value' => $lead->addressLine ?: 'Not provided'])
    @include('emails.partials.detail', ['label' => 'Phone', 'value' => $lead->phone ?: 'Not provided'])
    @include('emails.partials.detail', ['label' => 'Region', 'value' => ucfirst($lead->region)])

    @if ($lead->isEmergency)
        <p style="margin:18px 0 0 0;padding:12px 14px;border-radius:8px;background:#fff4e5;color:#7a4a00;font-size:14px;line-height:1.6;">
            <strong>Emergency request.</strong> If water is actively entering the property, call us now
            instead of waiting for the email reply.
        </p>
    @endif

    @include('emails.partials.button', [
        'url' => config('app.url'),
        'label' => 'Visit our website',
    ])

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If you need to add photos, an insurance claim number or a gate code, reply to this email and
        our office will attach it to your file.
    </p>
@endsection
