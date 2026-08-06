@php
    /** @var list<array{rank: int, patient: string, document: string, code: string, type_affiliation: string, business_unit: string, claims_count: int, total_bill_price: float}> $rows */
    /** @var list<array{rank: int, patient: string, document: string, code: string, type_affiliation: string, business_unit: string, claims_count: int, total_bill_price: float}> $topRows */
    /** @var array{patients: int, claims: int, bill_price: float} $totals */
    /** @var array{top_n: int, date_from: string|null, date_to: string|null} $params */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $logoDataUri */
    $brandCyan = '#00ADEF';
    $periodLabel = match (true) {
        filled($params['date_from'] ?? null) && filled($params['date_to'] ?? null) => $params['date_from'].' → '.$params['date_to'],
        filled($params['date_from'] ?? null) => 'Desde '.$params['date_from'],
        filled($params['date_to'] ?? null) => 'Hasta '.$params['date_to'],
        default => 'Sin filtro de fechas',
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de siniestralidad por paciente</title>
    <style>
        @page { margin: 8mm 6mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            line-height: 1.35;
            color: #1f2937;
        }
        .logo-row { text-align: center; margin-bottom: 6px; }
        .logo-row img { max-height: 32px; width: auto; }
        .doc-title-wrap { text-align: center; margin-bottom: 8px; }
        .doc-heading {
            font-size: 12pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 0 0 4px 0;
            padding-bottom: 4px;
            border-bottom: 2px solid {{ $brandCyan }};
            display: inline-block;
        }
        .doc-meta {
            font-size: 7pt;
            color: #475569;
            margin: 0 0 8px 0;
        }
        .summary {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            background: #f8fafc;
            font-size: 7.5pt;
        }
        .summary strong { color: #0f172a; }
        h2.section {
            font-size: 9.5pt;
            color: #0c4a6e;
            margin: 12px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #7dd3fc;
        }
        table.grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }
        table.grid th,
        table.grid td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.grid th {
            background: #e0f2fe;
            color: #0c4a6e;
            font-size: 7pt;
            text-align: left;
        }
        table.grid td.num,
        table.grid th.num {
            text-align: right;
        }
        table.grid td.center,
        table.grid th.center {
            text-align: center;
        }
        .top-block {
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .top-banner {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            font-weight: bold;
            padding: 6px 8px;
            margin-bottom: 4px;
            font-size: 8.5pt;
        }
        table.top-grid th {
            background: #fde68a;
            color: #78350f;
        }
        .money { font-weight: bold; color: #065f46; }
        .empty {
            padding: 16px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
        }
        .col-rank { width: 5%; }
        .col-patient { width: 24%; }
        .col-doc { width: 12%; }
        .col-code { width: 10%; }
        .col-aff { width: 12%; }
        .col-unit { width: 15%; }
        .col-count { width: 10%; }
        .col-amount { width: 12%; }
    </style>
</head>
<body>
    <div class="logo-row">
        @if ($logoDataUri !== '')
            <img src="{{ $logoDataUri }}" alt="Logo">
        @endif
    </div>

    <div class="doc-title-wrap">
        <p class="doc-heading">Reporte de siniestralidad por paciente</p>
        <p class="doc-meta">
            Generado: {{ $generatedAt->format('d/m/Y H:i') }}
            · Criterio Ranking: mayor cantidad de servicios FINALIZADO
            · Costo: suma de Precio de Factura (bill_price)
            · Período: {{ $periodLabel }}
        </p>
    </div>

    <table class="summary">
        <tr>
            <td><strong>Pacientes con siniestros:</strong> {{ number_format($totals['patients'], 0, ',', '.') }}</td>
            <td><strong>Total siniestros FINALIZADO:</strong> {{ number_format($totals['claims'], 0, ',', '.') }}</td>
            <td><strong>Costo total empresa (USD):</strong> {{ number_format($totals['bill_price'], 2, ',', '.') }}</td>
            <td><strong>Top ranking:</strong> {{ (int) $params['top_n'] }}</td>
        </tr>
    </table>

    <h2 class="section">1. Siniestralidad por paciente (ordenados por cantidad de siniestros)</h2>

    @if ($rows === [])
        <div class="empty">No hay servicios FINALIZADO con paciente asociado para el período seleccionado.</div>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="col-rank center">#</th>
                    <th class="col-patient">Paciente</th>
                    <th class="col-doc">Identificación</th>
                    <th class="col-code">Código</th>
                    <th class="col-aff">Afiliación</th>
                    <th class="col-unit">Unidad</th>
                    <th class="col-count num">Siniestros</th>
                    <th class="col-amount num">Monto total (USD)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="center">{{ $row['rank'] }}</td>
                        <td>{{ $row['patient'] }}</td>
                        <td>{{ $row['document'] }}</td>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['type_affiliation'] }}</td>
                        <td>{{ $row['business_unit'] }}</td>
                        <td class="num">{{ number_format($row['claims_count'], 0, ',', '.') }}</td>
                        <td class="num money">{{ number_format($row['total_bill_price'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="top-block">
        <div class="top-banner">
            2. Record — Top {{ (int) $params['top_n'] }} pacientes más siniestrosos
            (mayor cantidad de servicios FINALIZADO; el monto muestra el costo para la empresa)
        </div>

        @if ($topRows === [])
            <div class="empty">Sin pacientes para el ranking.</div>
        @else
            <table class="grid top-grid">
                <thead>
                    <tr>
                        <th class="col-rank center">#</th>
                        <th class="col-patient">Paciente</th>
                        <th class="col-doc">Identificación</th>
                        <th class="col-code">Código</th>
                        <th class="col-aff">Afiliación</th>
                        <th class="col-unit">Unidad</th>
                        <th class="col-count num">Siniestros</th>
                        <th class="col-amount num">Monto total (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topRows as $row)
                        <tr>
                            <td class="center">{{ $row['rank'] }}</td>
                            <td>{{ $row['patient'] }}</td>
                            <td>{{ $row['document'] }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['type_affiliation'] }}</td>
                            <td>{{ $row['business_unit'] }}</td>
                            <td class="num">{{ number_format($row['claims_count'], 0, ',', '.') }}</td>
                            <td class="num money">{{ number_format($row['total_bill_price'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
