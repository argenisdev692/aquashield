@extends('emails.layout')

@php
    $start = \Carbon\CarbonImmutable::parse($appointment->startsAtLocal);
    $end = \Carbon\CarbonImmutable::parse($appointment->endsAtLocal);
@endphp

@section('title', 'Your appointment was rescheduled')
@section('preheader', 'The new date and time are inside.')
@section('heading', 'Your appointment was rescheduled')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $appointment->customerName }},</p>

    <p style="margin:0 0 18px 0;">
        Your <strong>inspection</strong> has been moved to
        <strong>{{ $start->format('l, F j, Y \a\t g:i A') }}</strong>
        ({{ $appointment->timezone }}).
    </p>

    @include('emails.partials.detail', ['label' => 'New date', 'value' => $start->format('l, F j, Y g:i A')])
    @include('emails.partials.detail', ['label' => 'Ends', 'value' => $end->format('g:i A').' '.$appointment->timezone])
    @include('emails.partials.detail', ['label' => 'Property', 'value' => $appointment->addressLine ?: '—'])
    @include('emails.partials.detail', ['label' => 'Reason', 'value' => $reason])

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If the new time does not work, contact our office and we will find another slot.
    </p>

    @include('emails.partials.button', [
        'url' => 'mailto:'.config('mail.from.address'),
        'label' => 'Contact our office',
    ])
@endsection
