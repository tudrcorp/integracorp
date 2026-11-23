<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Expediente del caso</title>
    <style>
        @page { margin: {{ $part === 'cover' ? '0' : '14mm 16mm 12mm 16mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 10px; }
        table { border-collapse: collapse; }

        /* Portada: una sola celda del alto de la hoja, centrada en ambos ejes. */
        .cover { width: 210mm; height: 296mm; table-layout: fixed; }
        .cover-cell { height: 296mm; vertical-align: middle; text-align: center; padding: 0 24mm; }
        .cover-logo { width: 72mm; }
        .cover-kicker { margin-top: 9mm; font-size: 9px; letter-spacing: 3px; color: #64748b; font-weight: bold; }
        .cover-title { margin-top: 2mm; font-size: 22px; font-weight: bold; color: #052F60; }
        .cover-case { margin-top: 1.5mm; font-size: 12px; color: #334155; }
        .details { width: 100%; table-layout: fixed; margin-top: 9mm; border: 1px solid #cbd5e1; }
        .details td { padding: 2.4mm 4mm; border-bottom: 1px solid #e2e8f0; text-align: left; font-size: 10px; }
        .details tr:last-child td { border-bottom: 0; }
        .details .label { width: 38%; color: #64748b; font-weight: bold; background: #f1f5f9; }
        .details .value { color: #0f172a; }
        .cover-foot { margin-top: 7mm; font-size: 8.5px; color: #64748b; }

        /* Índice */
        .index-head { width: 100%; table-layout: fixed; border-bottom: 2px solid #052F60; }
        .index-head td { padding: 0 0 3mm 0; vertical-align: bottom; }
        .index-logo { width: 38mm; }
        .index-title { font-size: 15px; font-weight: bold; color: #052F60; text-align: right; }
        .index-sub { font-size: 9px; color: #475569; text-align: right; margin-top: 1mm; }
        .list { width: 100%; table-layout: fixed; margin-top: 5mm; }
        .list th { padding: 2mm 2.5mm; background: #052F60; color: #ffffff; font-size: 8.5px; text-align: left; }
        .list td { padding: 2mm 2.5mm; border-bottom: 1px solid #e2e8f0; font-size: 9px; vertical-align: top; word-wrap: break-word; }
        .list tr { page-break-inside: avoid; }
        .num { width: 8%; text-align: center; color: #64748b; }
        .doc { width: 54%; }
        .date { width: 22%; }
        .page { width: 16%; text-align: right; font-weight: bold; }
        .doc-meta { margin-top: 0.8mm; font-size: 7.5px; color: #64748b; }
        .section-title { margin-top: 8mm; font-size: 11px; font-weight: bold; color: #b45309; }
        .section-note { margin-top: 1mm; font-size: 8.5px; color: #64748b; }
        .skipped th { background: #b45309; }
        .reason { width: 46%; color: #92400e; }
        .empty { padding: 4mm 2.5mm; color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
@if ($part === 'cover')
    <table class="cover">
        <tr>
            <td class="cover-cell">
                @if ($logo)
                    <img class="cover-logo" src="{{ $logo }}" alt="Tu Dr. Group">
                @endif
                <div class="cover-kicker">EXPEDIENTE DEL CASO</div>
                <div class="cover-title">{{ $details[0][1] }}</div>
                <div class="cover-case">{{ $details[8][1] }}</div>

                <table class="details">
                    @foreach ($details as [$label, $value])
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td class="value">{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>

                <div class="cover-foot">
                    {{ $documentCount }} {{ $documentCount === 1 ? 'documento incluido' : 'documentos incluidos' }}
                    · Generado el {{ $generatedAt->format('d/m/Y h:i A') }}@if (filled($generatedBy)) por {{ $generatedBy }}@endif
                </div>
            </td>
        </tr>
    </table>
@else
                <table class="index-head">
                    <tr>
                        <td>
                            @if ($logo)
                                <img class="index-logo" src="{{ $logo }}" alt="Tu Dr. Group">
                            @endif
                        </td>
                        <td>
                            <div class="index-title">Índice del expediente</div>
                            <div class="index-sub">{{ $patientName }} · {{ filled($case->code) ? $case->code : 'Caso #'.$case->getKey() }}</div>
                        </td>
                    </tr>
                </table>

                <table class="list">
                    <thead>
                        <tr>
                            <th class="num">N°</th>
                            <th class="doc">Documento</th>
                            <th class="date">Fecha</th>
                            <th class="page">Página</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="num">{{ $loop->iteration }}</td>
                                <td class="doc">
                                    {{ $row['name'] }}
                                    <div class="doc-meta">{{ $row['category'] }}@if (filled($row['reference'])) · {{ $row['reference'] }}@endif @if ((int) $row['pages'] > 1) · {{ $row['pages'] }} páginas @endif</div>
                                </td>
                                <td class="date">{{ $row['date'] !== '' ? $row['date'] : '—' }}</td>
                                <td class="page">{{ $row['start_page'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty" colspan="4">El caso no tiene documentos que se puedan unir.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($skipped !== [])
                    <div class="section-title">Documentos no incluidos ({{ count($skipped) }})</div>
                    <div class="section-note">Están registrados en el caso, pero no se pudieron unir a este expediente. Descárguelos desde el Centro de documentos del caso.</div>

                    <table class="list skipped">
                        <thead>
                            <tr>
                                <th class="doc">Documento</th>
                                <th class="reason">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($skipped as $item)
                                <tr>
                                    <td class="doc">
                                        {{ $item['name'] }}
                                        <div class="doc-meta">{{ $item['category'] }}</div>
                                    </td>
                                    <td class="reason">{{ $item['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
@endif
</body>
</html>
