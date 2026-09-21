@php
    /**
     * Bitácora de caso — mismo lenguaje visual que informes, órdenes y recipes.
     *
     * @var array<string, mixed> $dossier
     */
    $dossier = is_array($dossier ?? null) ? $dossier : [];
    $brandCyan = '#00ADEF';
    $logoDataUri = '';
    $logoPath = public_path('image/logoNewPdf.png');
    if (is_file($logoPath)) {
        $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
    }

    $code = (string) ($dossier['code'] ?? '—');
    $status = (string) ($dossier['status'] ?? '—');
    $patientName = (string) ($dossier['patient']['Nombre'] ?? 'Paciente');
    $generatedAt = now()->format('d/m/Y');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora de caso {{ $code }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #ffffff; }
        body {
            margin: 44mm 20mm 32mm 20mm;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            line-height: 1.28;
            color: #374151;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 20%;
            width: 60%;
            opacity: 0.052;
            z-index: 0;
            transform: translateY(-50%) rotate(-14deg);
        }
        .watermark img { width: 100%; height: auto; display: block; }
        .doc-root { position: relative; z-index: 1; }
        .header-bar {
            position: fixed;
            top: 12mm;
            left: 20mm;
            right: 20mm;
            width: auto;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            border-bottom: 1.5px solid {{ $brandCyan }};
            background: #ffffff;
            z-index: 12;
        }
        .header-bar td { vertical-align: top; padding: 0 8px 0 0; }
        .header-bar td:last-child { padding-right: 0; }
        .header-bar .col-logo { width: 32%; }
        .header-bar .col-title { width: 68%; text-align: right; }
        .header-bar td.header-rule-space {
            height: 10px;
            line-height: 10px;
            font-size: 10px;
            padding: 0;
            color: #ffffff;
        }
        img.header-logo { width: 110px; height: auto; display: block; }
        .doc-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: {{ $brandCyan }};
            margin: 0 0 2px 0;
        }
        .doc-sub { font-size: 7.25pt; color: #6b7280; margin: 0 0 2px 0; }
        .badge {
            display: inline-block;
            margin-top: 4px;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 6.5pt;
            font-weight: bold;
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .section-title {
            font-size: 7.5pt;
            font-weight: bold;
            color: #0c4a6e;
            margin: 8px 0 3px 0;
            padding: 2px 5px 2px 6px;
            border-left: 2.5px solid {{ $brandCyan }};
            background: #f0fdff;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        .item-kicker {
            font-size: 6.5pt;
            font-weight: bold;
            color: {{ $brandCyan }};
            margin: 6px 0 2px 0;
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        table.flow {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }
        table.flow > tbody > tr > td {
            padding: 0;
            vertical-align: top;
        }
        tr.stick {
            page-break-after: avoid;
            page-break-inside: avoid;
        }
        .prose-box {
            width: 100%;
            margin: 0 0 3px 0;
            padding: 4px 6px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            font-size: 7.35pt;
            line-height: 1.4;
            color: #374151;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .grid { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .grid td { width: 50%; padding: 2px 8px 3px 0; vertical-align: top; word-wrap: break-word; }
        .label {
            font-size: 6.25pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .value { font-size: 7.5pt; color: #111827; font-weight: 600; }
        .value-muted { font-size: 7.35pt; color: #4b5563; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            table-layout: fixed;
            page-break-inside: auto;
        }
        table.items thead {
            display: table-header-group;
        }
        table.items tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        table.items th {
            background-color: {{ $brandCyan }};
            color: #ffffff;
            padding: 4px;
            text-align: left;
            font-size: 6.5pt;
            border: 1px solid #0090c7;
        }
        table.items th.section-head {
            background: #f0fdff;
            color: #0c4a6e;
            border: 1px solid #e5e7eb;
            border-left: 2.5px solid {{ $brandCyan }};
            font-size: 7.5pt;
            padding: 2px 5px 2px 6px;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 4px;
            vertical-align: top;
            font-size: 7.25pt;
            word-wrap: break-word;
        }
        table.items tr:nth-child(even) td { background: #fafafa; }
        .items-empty {
            font-size: 7.25pt;
            color: #6b7280;
            margin: 3px 0 0 0;
            padding: 5px 8px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
        }
        .doc-content { padding: 0; }
        .footer-fixed {
            position: fixed;
            bottom: 10mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 6.5pt;
            color: #9ca3af;
            background: #ffffff;
            z-index: 10;
        }
        .footer-brand { font-weight: bold; color: {{ $brandCyan }}; }
    </style>
</head>
<body>
<div class="doc-root">
@if ($logoDataUri !== '')
    <div class="watermark" aria-hidden="true"><img src="{{ $logoDataUri }}" alt=""></div>
@endif
<table class="header-bar">
    <tr>
        <td class="col-logo">
            @if ($logoDataUri !== '')
                <img class="header-logo" src="{{ $logoDataUri }}" width="110" alt="Tu Doctor en Casa">
            @else
                <span style="font-weight:bold;color:{{ $brandCyan }};font-size:8pt;">Tu Doctor en Casa</span>
            @endif
        </td>
        <td class="col-title">
            <p class="doc-title">Bitácora de caso</p>
            <p class="doc-sub">Clave del servicio: <strong>{{ $code }}</strong></p>
            <p class="doc-sub">Paciente: <strong>{{ $patientName }}</strong></p>
            <p class="doc-sub">Fecha: <strong>{{ $generatedAt }}</strong></p>
            <span class="badge">{{ $status }}</span>
            @if (! empty($dossier['is_discharge']))
                <span class="badge">Alta médica</span>
            @endif
        </td>
    </tr>
    <tr>
        <td class="header-rule-space" colspan="2">&nbsp;</td>
    </tr>
</table>
<div class="doc-content">
    @include('documents.partials.bitacora-caso-section', ['title' => 'Identificación del caso', 'map' => $dossier['header'] ?? []])
    @include('documents.partials.bitacora-caso-section', ['title' => 'Paciente', 'map' => $dossier['patient'] ?? []])

    @if (! empty($dossier['discharge']))
        @include('documents.partials.bitacora-caso-section', ['title' => 'Alta médica', 'map' => $dossier['discharge']])
    @endif

    @include('documents.partials.bitacora-caso-entries', ['title' => 'Consultas y notas médicas', 'entries' => $dossier['consultations'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Seguimientos', 'entries' => $dossier['follow_ups'] ?? []])
    @include('documents.partials.bitacora-caso-amd', ['entries' => $dossier['amd_reports'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Observaciones del caso', 'entries' => $dossier['observations'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Bitácora operativa', 'entries' => $dossier['operation_logs'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Laboratorios', 'entries' => $dossier['labs'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Medicamentos', 'entries' => $dossier['medications'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Estudios / imagenología', 'entries' => $dossier['studies'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Especialistas', 'entries' => $dossier['specialties'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Coordinaciones', 'entries' => $dossier['coordinations'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Órdenes de servicio', 'entries' => $dossier['service_orders'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Citas médicas', 'entries' => $dossier['appointments'] ?? []])
    @include('documents.partials.bitacora-caso-documents', ['entries' => $dossier['documents'] ?? []])
    @include('documents.partials.bitacora-caso-entries', ['title' => 'Mensajería de seguimiento', 'entries' => $dossier['messages'] ?? []])
</div>
</div>
<div class="footer-fixed">
    <strong>TU DOCTOR EN CASA, C. A. RIF.: J-50358368-1</strong><br>
    Dirección Comercial: Av. Francisco de Miranda, Centro Lido, Torre A, Piso 12, Oficina 124. El Rosal, Caracas.<br>
    Teléfono MediChat atención 24 horas: (0424) 213 21 12- Celular Coordinación de servicios: (0414) 901 03 52<br>
    Correo: 24H@tudrencasa.com IG: @tudrencasa WEB: https://tudrencasa.com/
</div>
</body>
</html>
