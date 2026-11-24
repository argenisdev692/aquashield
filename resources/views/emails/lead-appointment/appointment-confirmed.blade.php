@extends('emails.layout')

@php
    $start = \Carbon\CarbonImmutable::parse($appointment->startsAtLocal);
    $end = \Carbon\CarbonImmutable::parse($appointment->endsAtLocal);
@endphp

@section('title', 'Your appointment is scheduled')
@section('preheader', 'Your appointment details and a PDF summary are inside.')
@section('heading', 'Your appointment is scheduled')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $appointment->customerName }},</p>

    <p style="margin:0 0 18px 0;">
        Your <strong>inspection</strong> with
        {{ config('app.name') }} is scheduled for
        <strong>{{ $start->format('l, F j, Y \a\t g:i A') }}</strong>
        ({{ $appointment->timezone }}).
    </p>

    @include('emails.partials.detail', ['label' => 'When', 'value' => $start->format('l, F j, Y g:i A').' – '.$end->format('g:i A').' '.$appointment->timezone])
    @include('emails.partials.detail', ['label' => 'Property', 'value' => $appointment->addressLine ?: '—'])

    <p style="margin:18px 0 0 0;padding:12px 14px;border-radius:8px;background:#f1f6fa;color:#244166;font-size:14px;line-height:1.6;">
        <strong>Please have your insurance policy available.</strong> The inspector will document the
        damage and explain the next step. A PDF summary is attached to this email.
    </p>

    @include('emails.partials.button', [
        'url' => 'mailto:'.config('mail.from.address'),
        'label' => 'Contact our office',
    ])

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        Need to reschedule or cancel? Contact us as soon as possible so we can release the slot for
        another customer.
    </p>
@endsection
