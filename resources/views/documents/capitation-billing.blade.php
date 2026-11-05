@php
    /**
     * Pre-facturación de capitado (Centro de reportes de Administración).
     *
     * @var \App\Support\Administration\CapitationBilling\CapitationBillingSnapshot $snapshot
     * @var list<array{group: string, rows: list<array<string, mixed>>, count: int, amount: float}> $groups
     * @var list<array{label: string, amount: float, kind: string}> $paymentLines
     * @var list<list<list<scalar|null>>> $monthChunks
     * @var list<list<list<scalar|null>>> $prorateChunks
     * @var list<list<list<scalar|null>>> $retirementChunks
     * @var string $logoDataUri
     */
    $brandBlue = '#052F60';
    $brandCyan = '#00ADEF';
    $money = static fn (mixed $value): string => $value === null || $value === '' ? '' : number_format((float) $value, 2, ',', '.');
    $text = static fn (mixed $value): string => $value === null ? '' : (string) $value;
    /** Columnas del detalle del mes que caben en A4 horizontal: tomador, afiliado, documento, sexo, nacimiento, parentesco, planes, tarifa, prorrata, observaciones. */
    $monthColumns = [3 => 15, 6 => 17, 8 => 9, 9 => 7, 10 => 7, 11 => 7, 12 => 8, 13 => 9, 14 => 6, 15 => 6, 16 => 9];
    $prorateColumns = [0 => 8, 3 => 18, 6 => 20, 8 => 10, 10 => 8, 13 => 8, 14 => 8, 15 => 7, 16 => 7, 17 => 6];
    $retirementColumns = [0 => 18, 3 => 20, 5 => 10, 7 => 8, 8 => 8, 9 => 8, 11 => 8, 12 => 20];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pre-facturación de capitado · {{ $snapshot->supplierName }} · {{ $snapshot->periodLabel() }}</title>
    <style>
        @page { margin: 8mm 6mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; font-size: 6pt; line-height: 1.3; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }
        .header td { padding: 0 0 6px 0; vertical-align: middle; }
        .header img { max-height: 30px; width: auto; }
        .title { font-size: 12pt; font-weight: bold; color: {{ $brandBlue }}; text-align: right; }
        .meta { font-size: 6.5pt; color: #475569; text-align: right; }
        .status td { padding: 4px 6px; font-size: 7pt; font-weight: bold; }
        .status.closed td { background: #dcfce7; color: #166534; border-left: 3px solid #16a34a; }
        .status.draft td { background: #fef3c7; color: #78350f; border-left: 3px solid #f59e0b; }
        .note td { padding: 2px 6px; background: #f1f5f9; border-left: 3px solid {{ $brandCyan }}; font-size: 6.5pt; color: #334155; }
        .spacer td { padding: 0; height: 6px; }
        .section td { padding: 6px 0 3px 0; font-size: 8pt; font-weight: bold; color: {{ $brandBlue }}; }
        table.data { table-layout: fixed; }
        table.data th, table.data td { border: 1px solid #cbd5e1; padding: 2px 3px; font-size: 5.5pt; vertical-align: top; word-wrap: break-word; }
        table.data thead th { background: {{ $brandBlue }}; color: #ffffff; font-weight: bold; text-align: center; }
        table.data td.num { text-align: right; white-space: nowrap; }
        table.data tr.subtotal td { background: #e2e8f0; font-weight: bold; }
        table.data tr.total td { background: {{ $brandBlue }}; color: #ffffff; font-weight: bold; }
        table.data tr.discount td { color: #991b1b; }
        .summary-wrap td.col { padding: 0 6px 0 0; vertical-align: top; }
        .summary-wrap td.col-last { padding: 0 0 0 6px; vertical-align: top; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 40%;">@if ($logoDataUri !== '')<img src="{{ $logoDataUri }}" alt="Tu Dr. Group">@endif</td>
            <td style="width: 60%;">
                <div class="title">Pre-facturación de capitado · {{ $snapshot->periodLabel() }}</div>
                <div class="meta">{{ $snapshot->supplierName }} · Generado el {{ $snapshot->generatedAt->format('d/m/Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <table class="status {{ $snapshot->isClosed() ? 'closed' : 'draft' }}">
        <tr><td>{{ $snapshot->isClosed() ? 'MES CERRADO · este documento reproduce el cierre guardado' : 'PRELIMINAR · el mes no está cerrado; las cifras son las de hoy' }}</td></tr>
    </table>

    @foreach (array_filter($snapshot->notes, fn (string $note): bool => ! str_starts_with($note, 'PRELIMINAR')) as $note)
        <table class="note"><tr><td>{{ $note }}</td></tr></table>
    @endforeach

    <table class="spacer"><tr><td></td></tr></table>

    <table class="summary-wrap">
        <tr>
            <td class="col" style="width: 58%;">
                <table class="section"><tr><td>Resumen por tomador</td></tr></table>
                <table class="data">
                    <colgroup><col style="width: 64%;"><col style="width: 14%;"><col style="width: 22%;"></colgroup>
                    <thead><tr><th>TOMADOR</th><th>AFILIADOS</th><th>TARIFA MENSUAL (US$)</th></tr></thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr><td>{{ $group['group'] }}</td><td class="num">{{ number_format($group['count'], 0, ',', '.') }}</td><td class="num">{{ $money($group['amount']) }}</td></tr>
                        @endforeach
                        <tr class="total"><td>Total general</td><td class="num">{{ number_format(count($snapshot->monthRows), 0, ',', '.') }}</td><td class="num">{{ $money($snapshot->monthAmount()) }}</td></tr>
                    </tbody>
                </table>
            </td>
            <td class="col-last" style="width: 42%;">
                <table class="section"><tr><td>Resumen de pago</td></tr></table>
                <table class="data">
                    <colgroup><col style="width: 72%;"><col style="width: 28%;"></colgroup>
                    <thead><tr><th>CONCEPTO</th><th>MONTO (US$)</th></tr></thead>
                    <tbody>
                        @foreach ($paymentLines as $line)
                            <tr class="{{ $line['kind'] === 'total' ? 'total' : ($line['kind'] === 'discount' ? 'discount' : '') }}"><td>{{ $line['label'] }}</td><td class="num">{{ $money($line['amount']) }}</td></tr>
                        @endforeach
                        @if ($snapshot->bcvRate !== null)
                            <tr class="subtotal"><td>TC BCV {{ number_format($snapshot->bcvRate, 4, ',', '.') }}</td><td class="num">Bs. {{ $money($snapshot->totalVes()) }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @foreach ([
        ['title' => 'Afiliados del mes · '.$snapshot->periodLabel(), 'chunks' => $monthChunks, 'columns' => $monthColumns, 'headings' => \App\Support\Administration\CapitationBilling\CapitationBillingWriter::MONTH_HEADINGS, 'total' => $snapshot->monthAmount(), 'count' => count($snapshot->monthRows)],
        ['title' => 'Prorrateo '.$snapshot->previousPeriodLabel().' · nuevos afiliados', 'chunks' => $prorateChunks, 'columns' => $prorateColumns, 'headings' => \App\Support\Administration\CapitationBilling\CapitationBillingWriter::PRORATE_HEADINGS, 'total' => $snapshot->prorateAmount(), 'count' => count($snapshot->prorateRows)],
        ['title' => 'Retiros', 'chunks' => $retirementChunks, 'columns' => $retirementColumns, 'headings' => \App\Support\Administration\CapitationBilling\CapitationBillingWriter::RETIREMENT_HEADINGS, 'total' => null, 'count' => count($snapshot->retirementRows)],
    ] as $section)
        <table class="section"><tr><td>{{ $section['title'] }} ({{ number_format($section['count'], 0, ',', '.') }})</td></tr></table>

        @if ($section['chunks'] === [])
            <table class="note"><tr><td>Sin filas en esta sección.</td></tr></table>
        @endif

        @foreach ($section['chunks'] as $chunkIndex => $chunk)
            <table class="data">
                <colgroup>
                    @foreach ($section['columns'] as $width)
                        <col style="width: {{ $width }}%;">
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        @foreach (array_keys($section['columns']) as $index)
                            <th>{{ $section['headings'][$index] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chunk as $values)
                        <tr>
                            @foreach (array_keys($section['columns']) as $index)
                                @php($isNumeric = is_float($values[$index] ?? null) || (is_int($values[$index] ?? null) && $index === 17))
                                <td class="{{ $isNumeric ? 'num' : '' }}">{{ $isNumeric ? (is_int($values[$index]) ? $values[$index] : $money($values[$index])) : $text($values[$index] ?? null) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    @if ($section['total'] !== null && $loop->last)
                        <tr class="total">
                            <td colspan="{{ count($section['columns']) - 1 }}">TOTAL: {{ number_format($section['count'], 0, ',', '.') }} {{ $section['count'] === 1 ? 'afiliado' : 'afiliados' }}</td>
                            <td class="num">{{ $money($section['total']) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @endforeach
</body>
</html>
