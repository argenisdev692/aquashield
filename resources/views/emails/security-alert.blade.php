@extends('emails.layout')

@section('title', $event->subject())
@section('preheader', $event->message())
@section('heading', $event->heading())

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $recipientName }},</p>

    <p style="margin:0 0 18px 0;">{{ $event->message() }}</p>

    @if (! empty($context['occurred_at']))
        @include('emails.partials.detail', ['label' => 'When', 'value' => $context['occurred_at']])
    @endif

    @if (! empty($context['location']))
        @include('emails.partials.detail', ['label' => 'Approximate location', 'value' => $context['location']])
    @endif

    @if (! empty($context['ip_address']))
        @include('emails.partials.detail', ['label' => 'IP address', 'value' => $context['ip_address']])
    @endif

    @if (! empty($context['user_agent']))
        @include('emails.partials.detail', ['label' => 'Device', 'value' => $context['user_agent']])
    @endif

    @if (! empty($context['failed_attempts']))
        @include('emails.partials.detail', ['label' => 'Failed attempts', 'value' => $context['failed_attempts']])
    @endif

    @if (! empty($context['locked_until']))
        @include('emails.partials.detail', ['label' => 'Lock lifts at', 'value' => $context['locked_until']])
    @endif

    @php($lockedOut = $event === \App\Enums\SecurityEvent::AccountLocked)

    @include('emails.partials.button', [
        'url' => $lockedOut ? route('password.request') : route('settings.profile.edit'),
        'label' => $lockedOut ? 'Reset your password' : 'Review security settings',
    ])

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If you did not make this change, reset your password immediately and contact
        {{ config('mail.from.address') }}.
    </p>
@endsection
