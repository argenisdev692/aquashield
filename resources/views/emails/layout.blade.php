@php
    $company = \Shared\Infrastructure\Company\CompanyProfile::data();
    $palette = \Shared\Infrastructure\Branding\BrandPalette::class;
    $brandName = $company['name'] ?? config('app.name');
    $addressLine = collect([
        $company['address'] ?? null,
        $company['city'] ?? null,
        $company['state'] ?? null,
        $company['zip_code'] ?? null,
    ])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta name="color-scheme" content="light" />
    <meta name="supported-color-schemes" content="light" />
    <title>@yield('title', $brandName)</title>
    <style>
        @media only screen and (max-width: 600px) {
            .aq-shell { width: 100% !important; }
            .aq-pad { padding-left: 20px !important; padding-right: 20px !important; }
            .aq-heading { font-size: 24px !important; line-height: 1.25 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:{{ $palette::EMAIL_BACKGROUND }};color:{{ $palette::EMAIL_TEXT }};font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">
        @yield('preheader')
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:{{ $palette::EMAIL_BACKGROUND }};">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="aq-shell" style="width:600px;max-width:600px;border-collapse:collapse;">
                    <tr>
                        <td style="background:{{ $palette::EMAIL_CARD }};border:1px solid #e2eaf1;border-radius:14px;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="height:4px;background:{{ $palette::PRIMARY_ACCENT }};font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td class="aq-pad" style="padding:30px 36px 10px 36px;">
                                        <img
                                            src="{{ $company['logo_email_url'] }}"
                                            width="160"
                                            alt="{{ $brandName }}"
                                            style="display:block;width:160px;max-width:160px;height:auto;border:0;outline:none;text-decoration:none;"
                                        />
                                    </td>
                                </tr>
                                <tr>
                                    <td class="aq-pad" style="padding:8px 36px 0 36px;">
                                        <h1 class="aq-heading" style="margin:0;font-size:28px;line-height:1.2;letter-spacing:-0.02em;color:{{ $palette::EMAIL_TEXT }};font-weight:700;">
                                            @yield('heading')
                                        </h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="aq-pad" style="padding:18px 36px 34px 36px;font-size:16px;line-height:1.65;color:{{ $palette::EMAIL_MUTED }};">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:22px 12px 0 12px;text-align:center;font-size:12px;line-height:1.7;color:#6b87a8;">
                            <p style="margin:0 0 4px 0;font-weight:700;color:{{ $palette::EMAIL_MUTED }};">{{ $brandName }}</p>
                            @if ($addressLine !== '')
                                <p style="margin:0;">{{ $addressLine }}</p>
                            @endif
                            <p style="margin:0;">
                                @if (! empty($company['phone']))
                                    {{ $company['phone'] }} ·
                                @endif
                                <a href="mailto:{{ $company['support_email'] }}" style="color:{{ $palette::PRIMARY_ACCENT }};text-decoration:none;">{{ $company['support_email'] }}</a>
                            </p>
                            <p style="margin:4px 0 0 0;">
                                <a href="{{ $company['url'] }}" style="color:{{ $palette::PRIMARY_ACCENT }};text-decoration:none;">{{ parse_url($company['url'], PHP_URL_HOST) ?: $company['url'] }}</a>
                            </p>
                            <p style="margin:12px 0 0 0;color:#7a8fa8;">© {{ date('Y') }} {{ $brandName }}. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
