@extends('emails.layout')

@section('title', 'Set your password')
@section('preheader', 'Use this single-use link to set your password. It expires shortly.')
@section('heading', 'Set your password')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $recipientName }},</p>

    <p style="margin:0 0 6px 0;">
        Use the button below to set a new password for your {{ config('app.name') }} account.
        This link is single-use and expires in {{ $expiresIn }} minutes.
    </p>

    @include('emails.partials.button', [
        'url' => $url,
        'label' => 'Set my password',
    ])

    <p style="margin:6px 0 18px 0;font-size:13px;line-height:1.6;color:#6b87a8;word-break:break-word;">
        If the button does not work, paste this address into your browser:<br />
        <span style="color:{{ \Shared\Infrastructure\Branding\BrandPalette::EMAIL_TEXT }};">{{ $url }}</span>
    </p>

    <p style="margin:0 0 14px 0;">
        For your security, {{ config('app.name') }} never sends passwords by email, and nobody on
        our team can see the password you choose. Do not forward or share this link.
    </p>

    <p style="margin:18px 0 0 0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If you were not expecting this email, you can ignore it. Your account stays unchanged
        unless someone uses the link to set a password.
    </p>
@endsection
