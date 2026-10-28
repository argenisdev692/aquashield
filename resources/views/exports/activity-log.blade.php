{{-- Activity log export template for dompdf (barryvdh/laravel-dompdf).

     $columns: array<string, string>  machine column => header label
     $rows:    iterable<int, array<string, string|int|float>>
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>{{ $title ?? 'Activity log' }}</title>
    <style>
        /* dompdf supports a CSS subset. No grid, no flex, no custom properties. */
        @page {
            margin: 12mm 10mm;
        }
        body {
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 8.5pt;
            color: #18181b;
        }
        h1 {
            font-size: 13pt;
            margin: 0 0 6mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            text-align: left;
            padding: 4px 5px;
            background: #f4f4f5;
            border-bottom: 1px solid #d4d4d8;
            font-size: 8pt;
            text-transform: uppercase;
        }
        tbody td {
            padding: 3px 5px;
            border-bottom: 1px solid #f4f4f5;
            vertical-align: top;
        }
        tbody tr:nth-child(even) td {
            background: #fafafa;
        }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Activity log' }}</h1>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
