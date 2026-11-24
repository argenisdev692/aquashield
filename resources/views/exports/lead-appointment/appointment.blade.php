@extends('exports.pdf.layout')

@php
    $start = \Carbon\CarbonImmutable::parse($appointment->startsAtLocal);
    $end = \Carbon\CarbonImmutable::parse($appointment->endsAtLocal);
    $status = ucfirst(str_replace('_', ' ', $appointment->status));
@endphp

@section('content')
    <h1>Appointment summary</h1>
    <p style="margin-bottom: 4mm">
        <span class="badge">{{ $status }}</span>
        &nbsp; {{ $appointment->serviceName ?? 'Roof inspection' }}
    </p>

    <h2>Prepared for</h2>
    <table class="detail">
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $appointment->customerName }}</td>
        </tr>
        <tr>
            <td class="label">Email</td>
            <td class="value">{{ $appointment->customerEmail ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td>
            <td class="value">{{ $appointment->customerPhone ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Property</td>
            <td class="value">{{ $appointment->addressLine ?: '—' }}</td>
        </tr>
    </table>

    <h2>Visit details</h2>
    <table class="detail">
        <tr>
            <td class="label">Service</td>
            <td class="value">{{ $appointment->serviceName ?? 'Roof inspection' }}</td>
        </tr>
        <tr>
            <td class="label">Inspection</td>
            <td class="value">{{ $appointment->kind }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $start->format('l, F j, Y') }}</td>
        </tr>
        <tr>
            <td class="label">Time</td>
            <td class="value">
                {{ $start->format('g:i A') }} – {{ $end->format('g:i A') }} ({{ $appointment->timezone }})
            </td>
        </tr>
        <tr>
            <td class="label">Kind</td>
            <td class="value">{{ ucfirst(str_replace('_', ' ', $appointment->kind)) }}</td>
        </tr>
        @if (! empty($appointment->notes))
            <tr>
                <td class="label">Notes</td>
                <td class="value">{{ $appointment->notes }}</td>
            </tr>
        @endif
        @if (! empty($appointment->cancelReason))
            <tr>
                <td class="label">Cancellation</td>
                <td class="value">{{ $appointment->cancelReason }}</td>
            </tr>
        @endif
    </table>

    <div class="note">
        Please have your insurance policy available. The inspector will document the damage and explain the next step.
        If you need to reschedule, contact our office as soon as possible.
    </div>
@endsection
