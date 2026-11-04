{{-- Ficha de una encuesta de Quality Control. Determinista: nada de now() ni valores al azar (la huella del HTML decide si se regenera). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Encuesta de Quality Control</title>
    <style>
        @page { margin: 24px 0 28px 0; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }
        table.page { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td.page-cell { padding: 0 34px 12px 34px; vertical-align: top; }
        td.page-cell-first { padding: 6px 34px 14px 34px; vertical-align: top; }
        .brand { width: 100%; border-collapse: collapse; table-layout: fixed; border-bottom: 2px solid #052F60; }
        .brand td { padding: 0 0 10px 0; vertical-align: middle; }
        .title { font-size: 15px; font-weight: bold; color: #052F60; }
        .muted { color: #6b7280; }
        .section { font-size: 11px; font-weight: bold; color: #052F60; text-transform: uppercase; letter-spacing: .5px; padding: 0 0 6px 0; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; word-wrap: break-word; }
        td.label { background: #f8fafc; color: #475569; font-weight: bold; }
        .badge { font-weight: bold; }
        .note-head { font-size: 9px; color: #6b7280; padding: 0 0 2px 0; }
        .note-body { white-space: pre-wrap; }
    </style>
</head>
<body>
<table class="page">
    <tr>
        <td class="page-cell-first">
            <table class="brand">
                <tr>
                    <td style="width: 55%;">
                        <div class="title">Encuesta de Quality Control</div>
                        <div class="muted">Quality Control {{ $typeLabel }} · Encuesta N.º {{ $survey->getKey() }}</div>
                    </td>
                    <td style="width: 45%; text-align: right;">
                        @if ($logoDataUri !== '')
                            <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" style="height: 42px;">
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Paciente</div>
            <table class="grid">
                <tr>
                    <td class="label" style="width: 22%;">Nombre</td>
                    <td style="width: 28%;">{{ $survey->patient_name }}</td>
                    <td class="label" style="width: 22%;">Cédula</td>
                    <td style="width: 28%;">{{ $survey->patient_document }}</td>
                </tr>
                <tr>
                    <td class="label">Contacto</td>
                    <td>{{ $survey->contact_phone ?: '—' }}</td>
                    <td class="label">Afiliado en el sistema</td>
                    <td>{{ $affiliateName ? $affiliateName.($affiliateStatus ? ' · '.$affiliateStatus : '') : 'No enlazado' }}</td>
                </tr>
                <tr>
                    <td class="label">Fecha de servicio</td>
                    <td>{{ $survey->service_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="label">Respuesta</td>
                    <td>{{ $survey->responded_at?->format('d/m/Y h:i A') ?? '—' }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Calificación</div>
            <table class="grid">
                @foreach ($ratings as $rating)
                    <tr>
                        <td class="label" style="width: 60%;">{{ $rating['label'] }}</td>
                        <td style="width: 40%;"><span class="badge" style="color: {{ $rating['color'] }};">{{ $rating['value'] }}</span></td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Servicio y observación</div>
            <table class="grid">
                <tr>
                    <td class="label" style="width: 22%;">Servicio recibido</td>
                    <td style="width: 78%;">{{ $services !== '' ? $services : '—' }}</td>
                </tr>
                <tr>
                    <td class="label">Sugerencia u observación</td>
                    <td class="note-body">{{ $survey->observations ?: 'Sin observaciones' }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="page-cell">
            <div class="section">Notas de seguimiento ({{ $notes->count() }})</div>
        </td>
    </tr>
    @forelse ($notes as $note)
        <tr>
            <td class="page-cell">
                <table class="grid">
                    <tr>
                        <td>
                            <div class="note-head">{{ $note->created_at?->format('d/m/Y h:i A') }} · {{ $note->created_by_name ?: '—' }}</div>
                            <div class="note-body">{{ $note->note }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    @empty
        <tr>
            <td class="page-cell"><span class="muted">Esta encuesta todavía no tiene notas de seguimiento.</span></td>
        </tr>
    @endforelse
</table>
</body>
</html>
