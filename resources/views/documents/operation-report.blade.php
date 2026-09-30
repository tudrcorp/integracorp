@php
    /** @var string $title */
    /** @var list<string> $headings */
    /** @var list<list<list<string|int|float|null>>> $rowChunks */
    /** @var list<string|int|float|null>|null $totals */
    /** @var list<int> $numericColumns */
    /** @var list<string> $criteria */
    /** @var string|null $notice */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $logoDataUri */
    $brandBlue = '#052F60';
    $brandCyan = '#00ADEF';
    $columnCount = max(1, count($headings));
    $columnWidth = round(100 / $columnCount, 3);
    $isWide = $columnCount > 10;
    $lastChunk = count($rowChunks) - 1;
    $format = static function (mixed $value, bool $numeric): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($numeric && (is_int($value) || is_float($value))) {
            return number_format((float) $value, is_int($value) ? 0 : 2, ',', '.');
        }

        return (string) $value;
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 8mm 6mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            line-height: 1.3;
            color: #1f2937;
        }
        table { border-collapse: collapse; width: 100%; }
        .header td { padding: 0 0 6px 0; vertical-align: middle; }
        .header img { max-height: 30px; width: auto; }
        .title {
            font-size: 12pt;
            font-weight: bold;
            color: {{ $brandBlue }};
            text-align: right;
        }
        .meta { font-size: 6.5pt; color: #475569; text-align: right; }
        .criteria td {
            padding: 4px 6px;
            background: #f1f5f9;
            border-left: 3px solid {{ $brandCyan }};
            font-size: 6.5pt;
            color: #334155;
        }
        .notice td {
            padding: 4px 6px;
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
            font-size: 6.5pt;
            color: #78350f;
        }
        .spacer td { padding: 0; height: 6px; }
        table.data { table-layout: fixed; }
        table.data th,
        table.data td {
            border: 1px solid #cbd5e1;
            padding: {{ $isWide ? '2px 3px' : '4px 5px' }};
            font-size: {{ $isWide ? '5.5pt' : '7pt' }};
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead th {
            background: {{ $brandBlue }};
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        table.data td.num { text-align: right; white-space: nowrap; }
        table.data tfoot td {
            background: #e2e8f0;
            font-weight: bold;
            border-top: 2px solid {{ $brandBlue }};
        }
        .empty td { padding: 16px; text-align: center; color: #64748b; font-size: 8pt; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 40%;">
                @if ($logoDataUri !== '')
                    <img src="{{ $logoDataUri }}" alt="Tu Dr. Group">
                @endif
            </td>
            <td style="width: 60%;">
                <div class="title">{{ $title }}</div>
                <div class="meta">Operaciones · Generado el {{ $generatedAt->format('d/m/Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table class="criteria">
        @foreach ($criteria as $line)
            <tr><td>{{ $line }}</td></tr>
        @endforeach
    </table>

    @if (filled($notice))
        <table class="spacer"><tr><td></td></tr></table>
        <table class="notice"><tr><td>{{ $notice }}</td></tr></table>
    @endif

    <table class="spacer"><tr><td></td></tr></table>

    @if ($rowChunks === [])
        <table class="empty"><tr><td>No hay servicios para el periodo y los filtros seleccionados.</td></tr></table>
    @else
        {{-- Una tabla por bloque: DomPDF no soporta una sola tabla de cientos de filas. --}}
        @foreach ($rowChunks as $chunkIndex => $chunk)
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($headings as $heading)
                            <th style="width: {{ $columnWidth }}%;">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chunk as $row)
                        <tr>
                            @foreach ($row as $index => $value)
                                @php($numeric = in_array($index, $numericColumns, true))
                                <td @class(['num' => $numeric])>{{ $format($value, $numeric) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($chunkIndex === $lastChunk && $totals !== null)
                    <tfoot>
                        <tr>
                            @foreach ($totals as $index => $value)
                                @php($numeric = in_array($index, $numericColumns, true))
                                <td @class(['num' => $numeric])>{{ $value === '' ? '' : $format($value, $numeric) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endforeach
    @endif
</body>
</html>
