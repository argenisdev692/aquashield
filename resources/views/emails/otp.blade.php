@extends('emails.layout')

@section('title', 'Your AquaShield sign-in code')
@section('preheader', 'Your one-time sign-in code expires in ' . $expiresInMinutes . ' minutes.')
@section('heading', 'Your sign-in code')

@section('content')
    <p style="margin:0 0 14px 0;">Hi {{ $recipientName }},</p>

    <p style="margin:0 0 18px 0;">
        Use the code below to finish signing in. It can be used once and expires in
        {{ $expiresInMinutes }} minutes.
    </p>

    <div style="margin:22px 0;padding:18px 24px;border:1px solid #c9f0f9;border-radius:10px;background:#e8f8fd;text-align:center;">
        <span style="display:block;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;font-size:34px;line-height:1.2;letter-spacing:0.28em;font-weight:700;color:{{ \Shared\Infrastructure\Branding\BrandPalette::EMAIL_TEXT }};">{{ $code }}</span>
    </div>

    <p style="margin:0;font-size:14px;line-height:1.65;color:#6b87a8;">
        If you did not request this code, you can ignore this email. Do not forward it:
        anyone with the code can sign in to your account.
    </p>
@endsection
