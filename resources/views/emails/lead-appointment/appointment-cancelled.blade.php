@extends('emails.layout')

@section('title', 'Your appointment was cancelled')
@section('preheader', 'The appointment has been cancelled. Contact us to rebook.')
@section('heading', 'Your appointment was cancelled')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $appointment->customerName }},</p>

    <p style="margin:0 0 18px 0;">
        Your <strong>inspection</strong> has been cancelled.
    </p>

    @include('emails.partials.detail', ['label' => 'Property', 'value' => $appointment->addressLine ?: '—'])
    @include('emails.partials.detail', ['label' => 'Reason', 'value' => $reason])

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If you still need the inspection, reply to this email or call our office and we will book a
        new slot. If the property is actively taking water, call us immediately.
    </p>

    @include('emails.partials.button', [
        'url' => 'mailto:'.config('mail.from.address'),
        'label' => 'Rebook with our office',
    ])
@endsection
