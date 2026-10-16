@php
    /** @var string $title */
    /** @var list<string> $headings */
    /** @var list<list<array{kind: string, label: string, values: list<string|int|float|null>}>> $lineChunks */
    /** @var list<int> $numericColumns */
    /** @var list<string> $criteria */
    /** @var string|null $notice */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $logoDataUri */
    $brandBlue = '#052F60';
    $brandCyan = '#00ADEF';
    $columnCount = max(1, count($headings));
    $widths = $columnCount === 14
        ? [11, 7, 10, 8, 10, 9, 6, 6, 5, 6, 6, 6, 4, 6]
        : array_fill(0, $columnCount, round(100 / $columnCount, 3));
    $labelSpan = $numericColumns === [] ? $columnCount : max(1, min($numericColumns));
    $format = static function (mixed $value, bool $numeric): string {
        if ($value === null || $value === '') {
            return '';
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
            font-size: 6pt;
            line-height: 1.3;
            color: #1f2937;
        }
        table { border-collapse: collapse; width: 100%; }
        .header td { padding: 0 0 6px 0; vertical-align: middle; }
        .header img { max-height: 30px; width: auto; }
        .title { font-size: 12pt; font-weight: bold; color: {{ $brandBlue }}; text-align: right; }
        .meta { font-size: 6.5pt; color: #475569; text-align: right; }
        .criteria td {
            padding: 3px 6px;
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
            padding: 2px 3px;
            font-size: 5.5pt;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data thead th { background: {{ $brandBlue }}; color: #ffffff; font-weight: bold; text-align: center; }
        table.data td.num { text-align: right; white-space: nowrap; }
        table.data tr.agency td { background: #d6e4f5; color: {{ $brandBlue }}; font-weight: bold; font-size: 6.5pt; }
        table.data tr.agent td { background: #eef2f7; font-weight: bold; font-style: italic; }
        table.data tr.subtotal td { background: #e2e8f0; font-weight: bold; }
        table.data tr.total td { background: {{ $brandBlue }}; color: #ffffff; font-weight: bold; font-size: 6.5pt; }
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
                <div class="meta">Administración · Generado el {{ $generatedAt->format('d/m/Y h:i A') }}</div>
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

    @if ($lineChunks === [])
        <table class="empty"><tr><td>No hay cuentas por cobrar con el periodo y los filtros seleccionados.</td></tr></table>
    @else
        {{-- Una tabla por bloque: DomPDF no soporta una sola tabla de cientos de filas. --}}
        @foreach ($lineChunks as $chunk)
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($headings as $index => $heading)
                            <th style="width: {{ $widths[$index] ?? 5 }}%;">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chunk as $line)
                        @if ($line['kind'] === 'agency' || $line['kind'] === 'agent')
                            <tr class="{{ $line['kind'] }}"><td colspan="{{ $columnCount }}">{{ $line['label'] }}</td></tr>
                        @elseif ($line['kind'] === 'subtotal' || $line['kind'] === 'total')
                            <tr class="{{ $line['kind'] }}">
                                <td colspan="{{ $labelSpan }}">{{ $line['label'] }}</td>
                                @foreach ($line['values'] as $index => $value)
                                    @continue($index < $labelSpan)
                                    @php($numeric = in_array($index, $numericColumns, true))
                                    <td @class(['num' => $numeric])>{{ $format($value, $numeric) }}</td>
                                @endforeach
                            </tr>
                        @else
                            <tr>
                                @foreach ($line['values'] as $index => $value)
                                    @php($numeric = in_array($index, $numericColumns, true))
                                    <td @class(['num' => $numeric])>{{ $format($value, $numeric) }}</td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>
