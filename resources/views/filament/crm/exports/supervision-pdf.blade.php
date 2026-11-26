{{-- Resumen exportable de Supervisión WhatsApp (DomPDF). Tablas con table-layout fijo y DejaVu Sans. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Supervisión WhatsApp · {{ $report['range_label'] }}</title>
    <style>
        @page { margin: 18mm 16mm 16mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1f2937; }
        h1 { font-size: 16pt; color: #052F60; margin: 0 0 2px 0; }
        .sub { color: #6b7280; font-size: 8.5pt; margin-bottom: 12px; }
        h2 { font-size: 11pt; color: #052F60; border-bottom: 1px solid #052F60; padding-bottom: 2px; margin: 14px 0 6px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #eef2f7; font-size: 8.5pt; }
        td.num, th.num { text-align: right; }
        .insight { border-left: 3px solid #2563eb; background: #f5f8ff; padding: 5px 8px; margin: 0 0 5px 0; }
        .insight.bad { border-color: #b91c1c; background: #fef2f2; }
        .insight.warn { border-color: #d97706; background: #fffbeb; }
        .insight.ok { border-color: #047857; background: #ecfdf5; }
        .insight b { display: block; margin-bottom: 1px; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Supervisión WhatsApp</h1>
    <div class="sub">Periodo: {{ $report['range_label'] }} · Generado el {{ $generatedAt }} · Bandeja y bot Sol</div>

    <h2>Hallazgos</h2>
    @foreach ($report['insights'] as $insight)
        <div class="insight {{ $insight['tone'] }}"><b>{{ $insight['title'] }}</b>{{ $insight['text'] }}</div>
    @endforeach

    <table style="margin-top: 6px;">
        <tr>
            <td style="width: 50%; border: 0; padding: 0 6px 0 0;">
                <h2>Bandeja</h2>
                <table>
                    @foreach ($inboxKpis as [$label, $value])
                        <tr><td style="width: 68%;">{{ $label }}</td><td class="num">{{ $value }}</td></tr>
                    @endforeach
                </table>
            </td>
            <td style="width: 50%; border: 0; padding: 0 0 0 6px;">
                <h2>Bot Sol</h2>
                @if ($botKpis === [])
                    <p class="muted">Sin datos del bot: n8n no respondió al generar el informe.</p>
                @else
                    <table>
                        @foreach ($botKpis as [$label, $value])
                            <tr><td style="width: 62%;">{{ $label }}</td><td class="num">{{ $value }}</td></tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>

    <h2>Analistas</h2>
    @if ($report['data']['analysts'] === [])
        <p class="muted">No hay analistas activos en este departamento.</p>
    @else
        <table>
            <tr>
                <th style="width: 24%;">Analista</th>
                <th class="num">Tomados</th>
                <th class="num">1.ª resp.</th>
                <th class="num">Finalizadas</th>
                <th class="num">Atención</th>
                <th class="num">Al bot</th>
                <th class="num">Cerrados</th>
                <th class="num">Ventas</th>
            </tr>
            @foreach ($report['data']['analysts'] as $analyst)
                <tr>
                    <td>{{ $analyst['name'] }}</td>
                    <td class="num">{{ $analyst['attended'] }}</td>
                    <td class="num">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($analyst['reply_median']) }}</td>
                    <td class="num">{{ $analyst['finished'] }}</td>
                    <td class="num">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($analyst['handle_median']) }}</td>
                    <td class="num">{{ $analyst['released'] }}</td>
                    <td class="num">{{ $analyst['closed'] }}</td>
                    <td class="num">{{ $analyst['won'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Espera por área</h2>
    @if (($report['data']['trends']['areas'] ?? []) === [])
        <p class="muted">Sin casos en el periodo.</p>
    @else
        <table>
            <tr><th style="width: 50%;">Área</th><th class="num">Casos</th><th class="num">Espera mediana</th></tr>
            @foreach ($report['data']['trends']['areas'] as $area)
                <tr><td>{{ $area['label'] }}</td><td class="num">{{ $area['incoming'] }}</td><td class="num">{{ \App\Support\CrmInbox\CrmInboxDashboard::duration($area['wait_median']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if ($report['bot'] !== null && $report['bot']['reasons'] !== [])
        <h2>Motivos recientes de derivación</h2>
        <table>
            <tr><th style="width: 72%;">Motivo</th><th>Área</th></tr>
            @foreach ($report['bot']['reasons'] as $reason)
                <tr><td>{{ $reason['motivo'] }}</td><td>{{ \App\Support\CrmInbox\CrmInboxAreas::label($reason['area'] !== '' ? $reason['area'] : null) }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
