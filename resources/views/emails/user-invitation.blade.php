@extends('emails.layout')

@section('title', 'Activate your account')
@section('preheader', 'Set your password and activate your account. This link expires in 48 hours.')
@section('heading', 'Activate your account')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $recipientName }},</p>

    <p style="margin:0 0 6px 0;">
        An administrator created a {{ config('app.name') }} account for you. Choose your password
        with the button below to activate it. The link is single-use and expires in
        {{ $expiresInHours }} hours.
    </p>

    @include('emails.partials.button', [
        'url' => $url,
        'label' => 'Activate my account',
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
        If you were not expecting this invitation, you can ignore it. The account stays inactive
        until the link is used.
    </p>
@endsection
