{{-- Informe de Quality Control de un listado filtrado. Determinista dentro del día (la huella del HTML decide si se regenera). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe de Quality Control</title>
    <style>
        @page { margin: 24px 0 28px 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; }
        table.page { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td.page-cell { padding: 0 30px 12px 30px; vertical-align: top; }
        td.page-cell-first { padding: 6px 30px 12px 30px; vertical-align: top; }
        .brand { width: 100%; border-collapse: collapse; table-layout: fixed; border-bottom: 2px solid #052F60; }
        .brand td { padding: 0 0 8px 0; vertical-align: middle; }
        .title { font-size: 15px; font-weight: bold; color: #052F60; }
        .muted { color: #6b7280; }
        .section { font-size: 10px; font-weight: bold; color: #052F60; text-transform: uppercase; letter-spacing: .5px; padding: 0 0 5px 0; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th { background: #052F60; color: #ffffff; font-weight: bold; padding: 5px 6px; text-align: left; border: 1px solid #052F60; }
        table.grid td { border: 1px solid #e5e7eb; padding: 4px 6px; vertical-align: top; word-wrap: break-word; }
        table.kpis { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.kpis td { border: 1px solid #e5e7eb; padding: 8px; text-align: center; }
        .kpi { font-size: 16px; font-weight: bold; color: #052F60; }
        .ok { color: #047857; font-weight: bold; }
        .mid { color: #b45309; font-weight: bold; }
        .bad { color: #b91c1c; font-weight: bold; }
        .center { text-align: center; }
    </style>
</head>
<body>
<table class="page">
    <tr>
        <td class="page-cell-first">
            <table class="brand">
                <tr>
                    <td style="width: 60%;">
                        <div class="title">Informe de Quality Control {{ $typeLabel }}</div>
                        <div class="muted">Generado el {{ $generatedOn }} · Respuestas del {{ $range }}</div>
                        <div class="muted">Filtros: {{ $filtersLabel !== '' ? $filtersLabel : 'ninguno (todas las encuestas)' }}</div>
                    </td>
                    <td style="width: 40%; text-align: right;">
                        @if ($logoDataUri !== '')
                            <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" style="height: 40px;">
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <table class="kpis">
                <tr>
                    <td><div class="kpi">{{ number_format($total, 0, ',', '.') }}</div><div class="muted">Encuestas</div></td>
                    <td><div class="kpi">{{ $questions[3]['rate'] !== null ? $questions[3]['rate'].' %' : '—' }}</div><div class="muted">Satisfacción general</div></td>
                    <td><div class="kpi">{{ number_format($lowTotal, 0, ',', '.') }}</div><div class="muted">Con alguna calificación neutra o insatisfecha</div></td>
                    <td><div class="kpi">{{ number_format($withFollowUp, 0, ',', '.') }}</div><div class="muted">Con nota de seguimiento</div></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Calificación por pregunta</div>
            <table class="grid">
                <tr>
                    <th style="width: 32%;">Pregunta</th>
                    @foreach ($ratingLabels as $label)
                        <th style="width: 13%;" class="center">{{ $label }}</th>
                    @endforeach
                    <th style="width: 16%;" class="center">% satisfechos</th>
                </tr>
                @foreach ($questions as $question)
                    <tr>
                        <td>{{ $question['label'] }}</td>
                        @foreach ($question['counts'] as $value => $count)
                            <td class="center">{{ $count }}</td>
                        @endforeach
                        <td class="center {{ $question['rate'] === null ? '' : ($question['rate'] >= 80 ? 'ok' : ($question['rate'] >= 60 ? 'mid' : 'bad')) }}">{{ $question['rate'] !== null ? $question['rate'].' %' : '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Servicio recibido</div>
            <table class="grid">
                <tr>
                    <th style="width: 70%;">Servicio</th>
                    <th style="width: 30%;" class="center">Encuestas</th>
                </tr>
                @foreach ($services as $label => $count)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="center">{{ $count }}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Neutros e insatisfechos ({{ $lowTotal }}){{ $lowTotal > $low->count() ? ' · se muestran los '.$low->count().' más recientes' : '' }}</div>
            @if ($low->isEmpty())
                <span class="muted">Ninguna encuesta con calificación neutra o insatisfecha.</span>
            @endif
        </td>
    </tr>
    @foreach ($low->chunk(15) as $chunk)
        <tr>
            <td class="page-cell">
                <table class="grid">
                    <tr>
                        <th style="width: 11%;">Respuesta</th>
                        <th style="width: 22%;">Paciente</th>
                        <th style="width: 27%;">Observación</th>
                        <th style="width: 40%;">Último seguimiento</th>
                    </tr>
                    @foreach ($chunk as $survey)
                        @php($note = $latestNotes->get($survey->id))
                        <tr>
                            <td>{{ $survey->responded_at?->format('d/m/Y') }}</td>
                            <td>{{ $survey->patient_name }}<br><span class="muted">C.I. {{ $survey->patient_document }}</span></td>
                            <td>{{ \Illuminate\Support\Str::limit((string) $survey->observations, 160) ?: '—' }}</td>
                            <td>
                                @if ($note)
                                    <span class="muted">{{ $note->created_at?->format('d/m/Y') }} · {{ $note->created_by_name }}</span><br>{{ \Illuminate\Support\Str::limit((string) $note->note, 220) }}
                                @else
                                    <span class="bad">Sin seguimiento</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endforeach
    <tr>
        <td class="page-cell">
            <div class="section">Encuestas ({{ $total }}){{ $rowsLimited ? ' · se muestran las '.$rows->count().' más recientes' : '' }}</div>
        </td>
    </tr>
    @foreach ($rows->chunk(25) as $chunk)
        <tr>
            <td class="page-cell">
                <table class="grid">
                    <tr>
                        <th style="width: 11%;">Respuesta</th>
                        <th style="width: 25%;">Paciente</th>
                        <th style="width: 13%;">Canales</th>
                        <th style="width: 13%;">Tiempo</th>
                        <th style="width: 13%;">Servicio médico</th>
                        <th style="width: 13%;">General</th>
                        <th style="width: 12%;" class="center">Seguimiento</th>
                    </tr>
                    @foreach ($chunk as $survey)
                        <tr>
                            <td>{{ $survey->responded_at?->format('d/m/Y') }}</td>
                            <td>{{ $survey->patient_name }}</td>
                            @foreach (['rating_channels', 'rating_response_time', 'rating_medical_service', 'rating_overall'] as $field)
                                @php($rating = $survey->getAttribute($field))
                                <td style="color: {{ \App\Services\QualityControlPdfService::ratingHex($rating) }};">{{ $rating?->label() ?? '—' }}</td>
                            @endforeach
                            <td class="center">{{ $survey->notes_count }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    @endforeach
</table>
</body>
</html>
