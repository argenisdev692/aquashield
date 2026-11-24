@extends('emails.layout')

@php
    $start = \Carbon\CarbonImmutable::parse($appointment->startsAtLocal);
    $end = \Carbon\CarbonImmutable::parse($appointment->endsAtLocal);
    $previous = $appointment->previousStartsAt !== null && $appointment->previousStartsAt !== ''
        ? \Carbon\CarbonImmutable::parse($appointment->previousStartsAt)->setTimezone($appointment->timezone)
        : null;
@endphp

@section('title', $event->heading())
@section('preheader', $event->preheader())
@section('heading', $event->heading())

@section('content')
    <p style="margin:0 0 14px 0;">{{ $event->intro() }}</p>

    @include('emails.partials.detail', ['label' => 'Customer', 'value' => $appointment->customerName])
    @include('emails.partials.detail', ['label' => 'Email', 'value' => $appointment->customerEmail ?: '—'])
    @include('emails.partials.detail', ['label' => 'Phone', 'value' => $appointment->customerPhone ?: '—'])
    @include('emails.partials.detail', ['label' => 'Property', 'value' => $appointment->addressLine ?: '—'])
    @include('emails.partials.detail', ['label' => 'When', 'value' => $start->format('l, F j, Y g:i A').' – '.$end->format('g:i A').' '.$appointment->timezone])

    @if ($previous !== null)
        @include('emails.partials.detail', ['label' => 'Previous time', 'value' => $previous->format('l, F j, Y g:i A')])
    @endif

    @include('emails.partials.detail', ['label' => 'Kind', 'value' => ucfirst($appointment->kind)])
    @include('emails.partials.detail', ['label' => 'Status', 'value' => ucfirst($appointment->status)])
    @include('emails.partials.detail', ['label' => 'Region', 'value' => ucfirst($appointment->region)])

    @if ($reason !== null && $reason !== '')
        @include('emails.partials.detail', ['label' => 'Reason', 'value' => $reason])
    @endif

    @if ($appointment->notes !== null && $appointment->notes !== '')
        @include('emails.partials.detail', ['label' => 'Notes', 'value' => $appointment->notes])
    @endif

    @include('emails.partials.button', [
        'url' => rtrim(config('app.url'), '/').'/appointments/'.$appointment->uuid,
        'label' => 'Open the appointment',
    ])
@endsection
