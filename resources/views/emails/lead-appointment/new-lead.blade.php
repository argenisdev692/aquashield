@extends('emails.layout')

@section('title', 'New lead')
@section('preheader', 'A new lead is waiting for first contact.')
@section('heading', 'New lead')

@section('content')
    <p style="margin:0 0 14px 0;">
        A new lead just arrived from <strong>{{ ucfirst($lead->source) }}</strong>.
        Contact them within 15 minutes during business hours.
    </p>

    @include('emails.partials.detail', ['label' => 'Name', 'value' => $lead->fullName])
    @include('emails.partials.detail', ['label' => 'Company', 'value' => $lead->companyName ?: '—'])
    @include('emails.partials.detail', ['label' => 'Email', 'value' => $lead->email ?: '—'])
    @include('emails.partials.detail', ['label' => 'Phone', 'value' => $lead->phone ?: '—'])
    @include('emails.partials.detail', ['label' => 'Property', 'value' => $lead->addressLine ?: '—'])
    @include('emails.partials.detail', ['label' => 'Region', 'value' => ucfirst($lead->region)])
    @include('emails.partials.detail', ['label' => 'SMS consent', 'value' => $lead->smsConsent ? 'Yes' : 'No'])

    @if ($lead->isEmergency)
        <p style="margin:14px 0 0 0;padding:12px 14px;border-radius:8px;background:#fff4e5;color:#7a4a00;font-size:14px;line-height:1.6;">
            <strong>Emergency.</strong> This lead flagged active water intrusion.
        </p>
    @endif

    @if ($lead->isSpam)
        <p style="margin:14px 0 0 0;padding:12px 14px;border-radius:8px;background:#fdecec;color:#7a1f1f;font-size:14px;line-height:1.6;">
            <strong>Flagged by the spam scorer ({{ $lead->spamScore }}).</strong>
            Review before calling; do not discard automatically.
        </p>
    @endif

    @include('emails.partials.button', [
        'url' => rtrim(config('app.url'), '/').'/leads/'.$lead->uuid,
        'label' => 'Open the lead',
    ])
@endsection
