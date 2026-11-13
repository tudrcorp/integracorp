@php
    use App\Enums\FiscalBook;
    use App\Support\Administration\FiscalBooks\FiscalAmount;
    use App\Support\Administration\FiscalBooks\FiscalBookReport;

    /** @var array<string, mixed> $report */
    /** @var list<array{rows: list<array<string, mixed>>, carried: array<string, string>|null, carry: array<string, string>, last: bool}> $pages */
    $book = $report['book'];
    $money = fn ($value) => FiscalAmount::isZero($value) ? '—' : FiscalAmount::format($value);
    $widths = [2.4, 4.4, 2.8, 5.6, 5.6, 17.2, 6.4, 2.4, 6.6, 6.6, 4.6, 5.8, 4.9, 4.9, 4.1, 2.9, 4.4, 4.8, 4.2];
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $book->label() }} · {{ $report['month'] }} {{ $report['period']->year }}</title>
    <style>
        @page { margin: 22px 20px 26px 20px; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 6.4pt; color: #0f172a; }
        table { border-collapse: collapse; }
        .sheet { width: 100%; table-layout: fixed; }
        .page { page-break-after: always; }
        .page-last { page-break-after: auto; }
        .head td { padding: 0 0 6px 0; vertical-align: bottom; }
        .company { font-size: 8pt; font-weight: bold; }
        .title { font-size: 9.5pt; font-weight: bold; color: #052f60; padding-top: 2px; }
        .meta { text-align: right; font-size: 7pt; color: #475569; }
        .grid { width: 100%; table-layout: fixed; }
        .grid th { background: #052f60; color: #ffffff; font-weight: bold; font-size: 5.8pt; padding: 3px 2px; text-align: center; vertical-align: middle; border: 0.5px solid #052f60; }
        .grid th.group { background: #0b4a8f; }
        .grid td { padding: 2.5px 2px; border-bottom: 0.5px solid #cbd5e1; vertical-align: top; overflow: hidden; }
        .grid tr.sizer td { padding: 0; height: 0; border: 0; line-height: 0; font-size: 0; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .name { white-space: nowrap; overflow: hidden; }
        .annulled { color: #b91c1c; font-weight: bold; }
        .carry td { background: #eef2f7; font-weight: bold; border-bottom: 0.8px solid #94a3b8; }
        .total td { background: #052f60; color: #ffffff; font-weight: bold; }
        .summary { width: 80%; margin-top: 10px; table-layout: fixed; }
        .summary tr.sizer td { padding: 0; height: 0; border: 0; line-height: 0; font-size: 0; }
        .totals-box { width: 50%; margin-top: 14px; }
        .summary th { background: #052f60; color: #ffffff; font-size: 6.4pt; padding: 4px; text-align: center; }
        .summary td { padding: 4px; border-bottom: 0.5px solid #cbd5e1; font-size: 6.8pt; }
        .summary .strong td { font-weight: bold; background: #eef2f7; }
        .footer { font-size: 6pt; color: #64748b; padding-top: 4px; }
    </style>
</head>
<body>
@php $pageCount = count($pages) + 1; @endphp
@foreach ($pages as $pageIndex => $page)
    <div class="page">
        <table class="sheet head">
            <tr>
                <td style="width: 70%;">
                    <div class="company">{{ FiscalBookReport::COMPANY_NAME }} · RIF {{ FiscalBookReport::COMPANY_RIF }}</div>
                    <div class="title">{{ $report['title'] }}</div>
                </td>
                <td style="width: 30%;" class="meta">
                    Página {{ $pageIndex + 1 }} de {{ $pageCount }}<br>
                    {{ $report['closed'] ? 'Mes cerrado' : 'Mes abierto · documento de trabajo' }}
                </td>
            </tr>
        </table>

        <table class="grid">
            <thead>
                {{-- DomPDF toma los anchos de la primera fila: esta fila invisible los fija. --}}
                <tr class="sizer">
                    @foreach ($widths as $width)
                        <td style="width: {{ $width }}%;"></td>
                    @endforeach
                </tr>
                <tr>
                    <th colspan="8" class="group">Documento</th>
                    <th colspan="3" class="group">Total {{ mb_strtolower($book->operationsLabel()) }}</th>
                    <th colspan="2" class="group">Contribuyentes</th>
                    <th colspan="2" class="group">No contribuyentes</th>
                    <th rowspan="2">Alíc.</th>
                    <th colspan="3" class="group">Retención de IVA</th>
                </tr>
                <tr>
                    <th>N°</th><th>Fecha</th><th>Tipo</th><th>N° documento</th><th>N° control</th>
                    <th>{{ $book->counterpartyLabel() }}</th><th>RIF / C.I.</th><th>Tipo</th>
                    <th>Total con IVA y exentas</th><th>Exentas o no sujetas</th><th>{{ $book === FiscalBook::Sales ? 'Exportación' : 'Importación' }}</th>
                    <th>Base imponible</th><th>IVA</th><th>Base imponible</th><th>IVA</th>
                    <th>Fecha</th><th>Comprobante</th><th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @if ($page['carried'] !== null)
                    <tr class="carry">
                        <td colspan="8" class="num">VIENEN…</td>
                        @foreach (['total', 'exempt', 'export', 'taxpayer_base', 'taxpayer_tax', 'non_taxpayer_base', 'non_taxpayer_tax'] as $column)
                            <td class="num">{{ $money($page['carried'][$column]) }}</td>
                        @endforeach
                        <td></td><td></td><td></td>
                        <td class="num">{{ $money($page['carried']['withholding']) }}</td>
                    </tr>
                @endif

                @foreach ($page['rows'] as $row)
                    <tr>
                        <td class="center">{{ $row['number'] }}</td>
                        <td class="center">{{ $row['date']?->format('d/m/Y') }}</td>
                        <td>{{ $row['type'] === 'Factura' ? 'FACT' : ($row['type'] === 'Nota de crédito' ? 'N/C' : 'N/D') }}</td>
                        <td>{{ $row['document_number'] }}@if ($row['affected']) <br>Afecta: {{ $row['affected'] }}@endif</td>
                        <td>{{ $row['control_number'] }}</td>
                        <td class="name {{ $row['annulled'] ? 'annulled' : '' }}">{{ \Illuminate\Support\Str::limit($row['name'], 40) }}</td>
                        <td>{{ $row['rif'] }}</td>
                        <td class="center">{{ $row['taxpayer'] }}</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                        <td class="num">{{ $money($row['exempt']) }}</td>
                        <td class="num">{{ $money($row['export']) }}</td>
                        <td class="num">{{ $money($row['taxpayer_base']) }}</td>
                        <td class="num">{{ $money($row['taxpayer_tax']) }}</td>
                        <td class="num">{{ $money($row['non_taxpayer_base']) }}</td>
                        <td class="num">{{ $money($row['non_taxpayer_tax']) }}</td>
                        <td class="center">{{ rtrim(rtrim($row['rate'], '0'), '.') }}%</td>
                        <td class="center">{{ $row['withholding_date']?->format('d/m/Y') }}</td>
                        <td>{{ $row['withholding_number'] }}</td>
                        <td class="num">{{ $money($row['withholding']) }}</td>
                    </tr>
                @endforeach

                @if (! $page['last'])
                    <tr class="carry">
                        <td colspan="8" class="num">VAN…</td>
                        @foreach (['total', 'exempt', 'export', 'taxpayer_base', 'taxpayer_tax', 'non_taxpayer_base', 'non_taxpayer_tax'] as $column)
                            <td class="num">{{ $money($page['carry'][$column]) }}</td>
                        @endforeach
                        <td></td><td></td><td></td>
                        <td class="num">{{ $money($page['carry']['withholding']) }}</td>
                    </tr>
                @else
                    <tr class="total">
                        <td colspan="8" class="num">TOTAL DEL MES · {{ $report['totals']['documents'] }} documentos{{ $report['totals']['annulled'] > 0 ? ' ('.$report['totals']['annulled'].' anulados)' : '' }}</td>
                        @foreach (['total', 'exempt', 'export', 'taxpayer_base', 'taxpayer_tax', 'non_taxpayer_base', 'non_taxpayer_tax'] as $column)
                            <td class="num">{{ FiscalAmount::format($report['totals'][$column]) }}</td>
                        @endforeach
                        <td></td><td></td><td></td>
                        <td class="num">{{ FiscalAmount::format($report['totals']['withholding']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

    </div>
@endforeach

{{-- El resumen va siempre en su propia página: nunca se parte entre dos hojas. --}}
<div class="page-last">
    <table class="sheet head">
        <tr>
            <td style="width: 70%;">
                <div class="company">{{ FiscalBookReport::COMPANY_NAME }} · RIF {{ FiscalBookReport::COMPANY_RIF }}</div>
                <div class="title">RESUMEN DE {{ $book === FiscalBook::Sales ? 'DÉBITOS' : 'CRÉDITOS' }} FISCALES · {{ $report['month'] }} {{ $report['period']->year }}</div>
            </td>
            <td style="width: 30%;" class="meta">
                Página {{ $pageCount }} de {{ $pageCount }}<br>
                {{ $report['closed'] ? 'Mes cerrado' : 'Mes abierto · documento de trabajo' }}
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr class="sizer">
            <td style="width: 52%;"></td><td style="width: 9%;"></td><td style="width: 13%;"></td><td style="width: 13%;"></td><td style="width: 13%;"></td>
        </tr>
        <tr>
            <th style="text-align: left;">Concepto</th>
            <th>Ítem</th><th>Base imponible</th><th>{{ $book === FiscalBook::Sales ? 'Débito fiscal' : 'Crédito fiscal' }}</th><th>IVA retenido</th>
        </tr>
        @foreach ($report['summary'] as $line)
            <tr class="{{ $line['strong'] ? 'strong' : '' }}">
                <td>{{ $line['label'] }}</td>
                <td class="center">{{ $line['item'] }}</td>
                <td class="num">{{ FiscalAmount::format($line['base']) }}</td>
                <td class="num">{{ $line['tax'] === null ? '' : FiscalAmount::format($line['tax']) }}</td>
                <td class="num">{{ $line['withholding'] === null ? '' : FiscalAmount::format($line['withholding']) }}</td>
            </tr>
        @endforeach
    </table>

    <table class="summary totals-box">
        <tr class="sizer"><td style="width: 50%;"></td><td style="width: 50%;"></td></tr>
        <tr><td>Documentos del mes</td><td class="num">{{ $report['totals']['documents'] }}{{ $report['totals']['annulled'] > 0 ? ' ('.$report['totals']['annulled'].' anulados)' : '' }}</td></tr>
        <tr><td>Total {{ mb_strtolower($book->operationsLabel()) }} con IVA y exentas</td><td class="num">{{ FiscalAmount::format($report['totals']['total']) }}</td></tr>
    </table>
    <div class="footer">Montos en bolívares. Generado el {{ now()->format('d/m/Y h:i a') }}.</div>
</div>
</body>
</html>
