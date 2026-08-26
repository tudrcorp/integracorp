@php
    /**
     * Órdenes de laboratorio, imagenología y referencia a especialista.
     *
     * @var array<string, mixed> $data
     * @var string $logoDataUri
     * @var string $docType  laboratorios|imagenologia|especialista
     */
    $data = is_array($data ?? null) ? $data : [];
    $docType = $docType ?? 'laboratorios';
    $brandCyan = '#00ADEF';
    $logoDataUri = $logoDataUri ?? '';
    if ($logoDataUri === '') {
        $logoPath = public_path('image/logoNewPdf.png');
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }
    }
    $val = static fn (mixed $value): string => filled($value) ? (string) $value : '—';

    $title = match ($docType) {
        'imagenologia' => 'Orden de estudios / imagenología',
        'especialista' => 'Referencia a especialistas',
        default => 'Orden de laboratorios',
    };
    $sectionTitle = match ($docType) {
        'imagenologia' => 'Estudios indicados',
        'especialista' => 'Especialistas referidos',
        default => 'Laboratorios indicados',
    };
    $columnTitle = match ($docType) {
        'imagenologia' => 'Estudio / examen',
        'especialista' => 'Especialidad',
        default => 'Laboratorio',
    };
    $emptyText = match ($docType) {
        'imagenologia' => 'Sin estudios indicados.',
        'especialista' => 'Sin especialistas referidos.',
        default => 'Sin laboratorios indicados.',
    };
    $rawItems = match ($docType) {
        'imagenologia' => $data['studies'] ?? ($data['studiesArr'] ?? []),
        'especialista' => $data['consultSpecialistArr'] ?? [],
        default => $data['labs'] ?? ($data['labsArr'] ?? []),
    };
    $items = [];
    foreach (is_array($rawItems) ? $rawItems : [] as $item) {
        $label = is_array($item)
            ? trim((string) ($item['name'] ?? $item['specialty'] ?? $item['study'] ?? $item['laboratory'] ?? ''))
            : trim((string) $item);

        if ($label !== '') {
            $items[] = $label;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 0; size: A4 portrait; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #ffffff; }
        body {
            margin: 18mm 20mm;
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
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 0 7px 0;
            border-bottom: 1.5px solid {{ $brandCyan }};
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
        }
        table.items th {
            background-color: {{ $brandCyan }};
            color: #ffffff;
            padding: 4px;
            text-align: left;
            font-size: 6.5pt;
            border: 1px solid #0090c7;
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
        .keep-together { page-break-inside: avoid; }
        .doc-content { padding: 0 0 26mm 0; }
        .footer-fixed {
            position: fixed;
            bottom: 18mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            padding-top: 6px;
            border-top: 1px solid #e5e7eb;
            font-size: 6.5pt;
            color: #9ca3af;
            background: #ffffff;
        }
        .footer-brand { font-weight: bold; color: {{ $brandCyan }}; }
    </style>
</head>
<body>
<div class="doc-root">
@if($logoDataUri !== '')
    <div class="watermark" aria-hidden="true"><img src="{{ $logoDataUri }}" alt=""></div>
@endif
<div class="doc-content">
    <table class="header-bar">
        <tr>
            <td class="col-logo">
                @if($logoDataUri !== '')
                    <img class="header-logo" src="{{ $logoDataUri }}" width="110" alt="Tu Doctor en Casa">
                @else
                    <span style="font-weight:bold;color:{{ $brandCyan }};font-size:8pt;">Tu Doctor en Casa</span>
                @endif
            </td>
            <td class="col-title">
                <p class="doc-title">{{ $title }}</p>
                <p class="doc-sub">Clave del servicio: <strong>{{ $val($data['code_reference'] ?? null) }}</strong></p>
                <p class="doc-sub">Fecha: <strong>{{ $val($data['fecha'] ?? now()->format('d/m/Y')) }}</strong></p>
                <span class="badge">Telemedicina</span>
            </td>
        </tr>
        <tr>
            <td class="header-rule-space" colspan="2">&nbsp;</td>
        </tr>
    </table>

    <div class="section-title">Datos del paciente</div>
    <table class="grid">
        <tr>
            <td>
                <div class="label">Paciente</div>
                <div class="value">{{ $val($data['name_patiente'] ?? ($data['name_patient'] ?? null)) }}</div>
            </td>
            <td>
                <div class="label">Cédula</div>
                <div class="value-muted">{{ $val($data['ci_patiente'] ?? ($data['ci_patient'] ?? null)) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Edad</div>
                <div class="value-muted">{{ $val($data['age_patiente'] ?? ($data['age_patient'] ?? null)) }} años</div>
            </td>
            <td>
                <div class="label">Tipo de servicio</div>
                <div class="value-muted">Telemedicina</div>
            </td>
        </tr>
    </table>

    <div class="keep-together">
        <div class="section-title">{{ $sectionTitle }}</div>
        @if($items === [])
            <p class="items-empty">{{ $emptyText }}</p>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:100%">{{ $columnTitle }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="width:100%">{{ $val($item) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="keep-together">
        <div class="section-title">Médico tratante</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Colegio médico</div>
                    <div class="value-muted">{{ $val($data['code_cm'] ?? null) }}</div>
                </td>
                <td>
                    <div class="label">MPPS</div>
                    <div class="value-muted">{{ $val($data['code_mpps'] ?? null) }}</div>
                </td>
            </tr>
        </table>
    </div>
</div>
</div>
<div class="footer-fixed">
    Documento generado por el <span class="footer-brand">departamento de telemedicina de Tu Doctor en Casa</span>.<br>
    Uso clínico; la reproducción no autorizada puede estar restringida según políticas de la organización.
</div>
</body>
</html>
