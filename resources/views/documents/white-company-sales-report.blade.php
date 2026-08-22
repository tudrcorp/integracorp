@php
    $totals = $report['totals'];
    $money = fn ($value) => number_format((float) $value, 2, ',', '.').' US$';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de ventas — {{ $company->name }}</title>
    <style>
        @page { margin: 120px 28px 90px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; margin: 0; }

        header { position: fixed; top: -100px; left: 0; right: 0; height: 92px; }
        footer { position: fixed; bottom: -70px; left: 0; right: 0; height: 62px; }

        .brand-bar { width: 100%; border-collapse: collapse; }
        .brand-bar td { vertical-align: middle; }
        .brand-bar img { max-height: 46px; max-width: 150px; }
        .brand-title { text-align: center; }
        .brand-title h1 { margin: 0; font-size: 14px; letter-spacing: .3px; color: {{ $brandColor }}; }
        .brand-title p { margin: 3px 0 0; font-size: 9px; color: #6b7280; }
        .rule { height: 3px; background: {{ $brandColor }}; margin-top: 8px; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th {
            background: {{ $brandColor }}; color: #fff; font-size: 8px; text-transform: uppercase;
            letter-spacing: .4px; padding: 6px 5px; text-align: left; border: 1px solid {{ $brandColor }};
        }
        table.data td { padding: 5px; border: 1px solid #e5e7eb; font-size: 8.5px; }
        table.data tbody tr:nth-child(even) td { background: #f9fafb; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        tfoot td { font-weight: bold; background: #f3f4f6; border: 1px solid #d1d5db; padding: 6px 5px; }

        .empty { padding: 24px; text-align: center; color: #6b7280; border: 1px dashed #d1d5db; }

        .key-box {
            border-top: 2px solid {{ $brandColor }}; padding-top: 6px; width: 100%;
            border-collapse: collapse; font-size: 7.5px; color: #4b5563;
        }
        .key { font-family: DejaVu Sans Mono, monospace; font-size: 10px; letter-spacing: 1px;
               color: #111827; font-weight: bold; }
        .key-label { text-transform: uppercase; letter-spacing: .5px; color: #6b7280; font-size: 6.5px; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <header>
        <table class="brand-bar">
            <tr>
                <td style="width: 22%;">
                    @if ($tdgLogo !== '')
                        <img src="{{ $tdgLogo }}" alt="Tu Dr En Casa">
                    @endif
                </td>
                <td class="brand-title" style="width: 56%;">
                    <h1>REPORTE DE VENTAS</h1>
                    <p>
                        {{ $company->name }}@if (filled($company->rif)) · {{ $company->rif }}@endif
                    </p>
                    <p>Período: {{ $report['from'] }} al {{ $report['to'] }}</p>
                </td>
                <td style="width: 22%; text-align: right;">
                    @if ($partnerLogo !== '')
                        <img src="{{ $partnerLogo }}" alt="{{ $company->name }}">
                    @endif
                </td>
            </tr>
        </table>
        <div class="rule"></div>
    </header>

    <footer>
        <table class="key-box">
            <tr>
                <td style="width: 46%;">
                    <span class="key-label">Llave de verificación</span><br>
                    <span class="key">{{ $report['security_key'] }}</span>
                </td>
                <td style="width: 34%;">
                    Emitido el {{ $report['generated_at'] }} por {{ $report['generated_by'] }}.<br>
                    Cualquier modificación del archivo invalida la llave.
                </td>
                <td style="width: 20%; text-align: right;">
                    Verifique este reporte en<br>
                    <span style="color: {{ $brandColor }};">{{ $verificationUrl }}</span><br>
                    Página <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </footer>

    <main>
        @if ($report['rows'] === [])
            <p class="empty">No se registraron ventas activadas entre el {{ $report['from'] }} y el {{ $report['to'] }}.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 13%;">Afiliación</th>
                        <th style="width: 19%;">Titular</th>
                        <th style="width: 9%;">Activación</th>
                        <th style="width: 14%;">Plan</th>
                        <th style="width: 9%;" class="num">Cobertura</th>
                        <th style="width: 8%;" class="center">Frecuencia</th>
                        <th style="width: 5%;" class="center">Afil.</th>
                        <th style="width: 8%;" class="num">Recibido en cuenta</th>
                        <th style="width: 8%;" class="num">Neta TDG</th>
                        <th style="width: 8%;" class="num">Neta {{ $company->name }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['rows'] as $row)
                        <tr>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['titular'] !== '' ? $row['titular'] : '—' }}</td>
                            <td class="center">{{ $row['activated_at'] }}</td>
                            <td>{{ $row['plan'] !== '' ? $row['plan'] : '—' }}</td>
                            <td class="num">{{ $row['coverage'] !== null ? $money($row['coverage']) : '—' }}</td>
                            <td class="center">{{ $row['payment_frequency'] }}</td>
                            <td class="center">{{ $row['affiliates_count'] }}</td>
                            <td class="num">{{ $money($row['sale_price']) }}</td>
                            <td class="num">{{ $money($row['neta_tdg']) }}</td>
                            <td class="num">{{ $money($row['neta_partner']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6">TOTALES — {{ count($report['rows']) }} afiliación(es)</td>
                        <td class="center">{{ $totals['affiliates'] }}</td>
                        <td class="num">{{ $money($totals['sale_price']) }}</td>
                        <td class="num">{{ $money($totals['neta_tdg']) }}</td>
                        <td class="num">{{ $money($totals['neta_partner']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </main>
</body>
</html>
