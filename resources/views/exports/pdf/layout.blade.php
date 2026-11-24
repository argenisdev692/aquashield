@php
    $company = $company ?? \Shared\Infrastructure\Company\CompanyProfile::pdfBranding();
    $brand = \Shared\Infrastructure\Branding\BrandPalette::class;
    $documentTitle = $title ?? 'Document';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $documentTitle }} — {{ $company['name'] }}</title>
    <style>
        /* dompdf supports a CSS subset: no grid, no flex, no custom properties. */
        @page {
            margin: 18mm 12mm 20mm 12mm;
        }
        body {
            margin: 0;
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 8.5pt;
            color: {{ $brand::EMAIL_TEXT }};
        }
        .brand-band {
            width: 100%;
            border-collapse: collapse;
            background: {{ $brand::SURFACE }};
        }
        .brand-band td {
            padding: 14px 16px;
            vertical-align: middle;
        }
        .brand-name {
            color: #ffffff;
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: -0.2pt;
        }
        .brand-sub {
            color: {{ $brand::TEXT_SECONDARY }};
            font-size: 8.5pt;
            margin-top: 2px;
        }
        .accent {
            height: 3px;
            background: {{ $brand::PRIMARY_ACCENT }};
            font-size: 0;
            line-height: 0;
        }
        .meta {
            margin-top: 7px;
            color: {{ $brand::TEXT_MUTED }};
            font-size: 7.5pt;
        }
        .content {
            margin-top: 12px;
        }
        h1 {
            margin: 0 0 4mm 0;
            font-size: 13pt;
            color: {{ $brand::EMAIL_TEXT }};
        }
        h2 {
            margin: 5mm 0 2mm 0;
            font-size: 10pt;
            color: {{ $brand::SURFACE }};
        }
        p {
            line-height: 1.55;
            margin: 0 0 3mm 0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3mm;
        }
        table.data thead th {
            background: {{ $brand::SURFACE }};
            color: #ffffff;
            text-align: left;
            padding: 5px 6px;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.2pt;
        }
        table.data tbody td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2eaf1;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td {
            background: {{ $brand::EMAIL_BACKGROUND }};
        }
        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2mm;
        }
        table.detail td {
            padding: 3px 0;
            vertical-align: top;
            border-bottom: 1px solid #eef4f8;
        }
        table.detail td.label {
            width: 32mm;
            color: {{ $brand::TEXT_MUTED }};
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        table.detail td.value {
            color: {{ $brand::EMAIL_TEXT }};
            font-size: 8.5pt;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 8px;
            background: {{ $brand::PRIMARY_ACCENT }};
            color: {{ $brand::EMAIL_TEXT }};
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .note {
            margin-top: 4mm;
            padding: 3mm 3.5mm;
            background: {{ $brand::EMAIL_BACKGROUND }};
            border-left: 2px solid {{ $brand::PRIMARY_ACCENT }};
            color: {{ $brand::EMAIL_MUTED }};
            font-size: 8pt;
            line-height: 1.55;
        }
        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            border-top: 1px solid #e2eaf1;
            padding-top: 2mm;
            color: {{ $brand::TEXT_MUTED }};
            font-size: 7pt;
        }
        .footer table {
            width: 100%;
            border-collapse: collapse;
        }
        .footer td {
            vertical-align: top;
        }
    </style>
</head>
<body>
    <table class="brand-band">
        <tr>
            <td style="width: 34mm">
                @if (! empty($company['logo_data_uri']))
                    <img
                        src="{{ $company['logo_data_uri'] }}"
                        width="110"
                        alt="{{ $company['name'] }}"
                        style="display: block; border: 0"
                    />
                @endif
            </td>
            <td>
                <div class="brand-name">{{ $company['name'] }}</div>
                <div class="brand-sub">{{ $documentTitle }}</div>
            </td>
        </tr>
    </table>
    <div class="accent">&nbsp;</div>
    <div class="meta">
        Generated {{ now()->format('M j, Y g:i A') }}
        @if (! empty($company['website']))
            · {{ $company['website'] }}
        @endif
    </div>

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        <table>
            <tr>
                <td>{{ $company['address'] ?: $company['name'] }}</td>
                <td style="text-align: right">
                    @if (! empty($company['phone'])) {{ $company['phone'] }}· @endif
                    {{ $company['email'] }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
