@php
    /**
     * Plantilla homologada del informe médico (corto/largo) con el lenguaje visual
     * de órdenes de servicio y cotizaciones.
     *
     * @var array<string, mixed> $data
     * @var string $logoDataUri
     * @var string $variant  'corto' | 'largo'
     */
    $variant = $variant ?? 'corto';
    $isLong = $variant === 'largo';
    $brandCyan = '#00ADEF';
    $logoDataUri = $logoDataUri ?? '';
    if ($logoDataUri === '') {
        $logoPath = public_path('image/logoNewPdf.png');
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }
    }
    $val = static fn (mixed $value): string => filled($value) ? (string) $value : '—';

    $medications = is_array($data['medicationsArr'] ?? null) ? $data['medicationsArr'] : [];
    $labs = is_array($data['labsArr'] ?? null) ? array_values(array_filter($data['labsArr'], static fn (mixed $item): bool => filled($item))) : [];
    $studies = is_array($data['studiesArr'] ?? null) ? array_values(array_filter($data['studiesArr'], static fn (mixed $item): bool => filled($item))) : [];

    $title = $isLong
        ? 'Informe médico largo (consulta inicial)'
        : 'Informe médico (consulta inicial)';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        * {
            box-sizing: border-box;
        }
        html {
            margin: 0;
            padding: 0;
            background: #ffffff;
        }
        body {
            margin: 18mm 20mm 18mm 20mm;
            padding: 0;
            height: auto;
            min-height: 0;
            width: auto;
            max-width: 100%;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.5pt;
            line-height: 1.28;
            color: #374151;
            background: #ffffff;
        }
        .page-frame {
            padding: 0;
            margin: 0;
            width: 100%;
            max-width: 100%;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 20%;
            width: 60%;
            max-width: 60%;
            opacity: 0.052;
            z-index: 0;
            pointer-events: none;
            transform: translateY(-50%) rotate(-14deg);
            transform-origin: center center;
        }
        .watermark img {
            width: 100%;
            height: auto;
            display: block;
        }
        .doc-root {
            position: relative;
            z-index: 1;
        }
        .header-bar {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 0 7px 0;
            padding: 0 0 6px 0;
            border-bottom: 1.5px solid {{ $brandCyan }};
        }
        .header-bar td {
            vertical-align: top;
            padding: 0 8px 0 0;
            overflow: visible;
        }
        .header-bar td:last-child {
            padding-right: 0;
        }
        .header-bar .col-logo {
            width: 32%;
        }
        .header-bar .col-title {
            width: 68%;
        }
        img.header-logo {
            max-width: 110px;
            height: auto;
            display: block;
        }
        .title-cell {
            text-align: right;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .doc-title {
            font-size: 9.5pt;
            font-weight: bold;
            color: {{ $brandCyan }};
            margin: 0 0 2px 0;
            line-height: 1.2;
        }
        .doc-sub {
            font-size: 7.25pt;
            color: #6b7280;
            margin: 0 0 2px 0;
            line-height: 1.25;
        }
        .doc-sub:last-of-type {
            margin-bottom: 0;
        }
        .header-bar td.header-rule-space {
            height: 10px;
            line-height: 10px;
            font-size: 10px;
            padding: 0;
            color: #ffffff;
        }
        .badge {
            display: inline-block;
            margin-top: 3px;
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
            margin: 4px 0 2px 0;
            padding: 2px 5px 2px 6px;
            border-left: 2.5px solid {{ $brandCyan }};
            background: #f0fdff;
            line-height: 1.2;
        }
        .two-col-section:first-of-type .section-title {
            margin-top: 0;
        }
        .section-title--block {
            margin-top: 8px;
        }
        .keep-together {
            page-break-inside: avoid;
        }
        .grid {
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
        }
        .grid td {
            padding: 2px 8px 3px 0;
            vertical-align: top;
            width: 50%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .grid tr td:nth-child(2) {
            padding-right: 0;
        }
        .label {
            font-size: 6.25pt;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            line-height: 1.15;
            margin-bottom: 1px;
        }
        .value {
            font-size: 7.5pt;
            color: #111827;
            font-weight: 600;
            line-height: 1.28;
        }
        .value-muted {
            font-size: 7.35pt;
            color: #4b5563;
            line-height: 1.4;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .prose-box {
            width: 100%;
            max-width: 100%;
            margin: 0 0 2px 0;
            padding: 5px 7px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            font-size: 7.35pt;
            line-height: 1.4;
            color: #374151;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.items {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 6.75pt;
            table-layout: fixed;
        }
        table.items th {
            background-color: {{ $brandCyan }};
            color: #ffffff;
            padding: 4px 4px;
            text-align: left;
            font-size: 6.5pt;
            font-weight: bold;
            line-height: 1.15;
            border: 1px solid #0090c7;
        }
        table.items td {
            border: 1px solid #e5e7eb;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            line-height: 1.22;
            font-size: 7pt;
        }
        table.items tr:nth-child(even) td {
            background: #fafafa;
        }
        table.items td.center {
            text-align: center;
        }
        .items-empty {
            font-size: 7.25pt;
            color: #6b7280;
            margin: 3px 0 0 0;
            padding: 5px 8px;
            background: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 3px;
            line-height: 1.3;
        }
        .footer-fixed {
            position: fixed;
            bottom: 18mm;
            left: 20mm;
            right: 20mm;
            text-align: center;
            line-height: 1.4;
            padding: 6px 4px 0 4px;
            border-top: 1px solid #e5e7eb;
            font-size: 6.5pt;
            color: #9ca3af;
            background-color: #ffffff;
            z-index: 10;
        }
        .footer-brand {
            font-weight: bold;
            color: {{ $brandCyan }};
        }
        .two-col-section {
            margin-bottom: 6px;
        }
        .doc-content {
            position: relative;
            z-index: 1;
            padding: 0 0 26mm 0;
        }
    </style>
</head>
<body>
<div class="page-frame">
<div class="doc-root">
@if($logoDataUri !== '')
    <div class="watermark" aria-hidden="true">
        <img src="{{ $logoDataUri }}" alt="">
    </div>
@endif
<div class="doc-content">
    <table class="header-bar" width="100%">
        <tr>
            <td class="col-logo">
                @if($logoDataUri !== '')
                    <img class="header-logo" src="{{ $logoDataUri }}" width="110" alt="Tu Doctor en Casa">
                @else
                    <span style="font-weight:bold;color:{{ $brandCyan }};font-size:8pt;">Tu Doctor en Casa</span>
                @endif
            </td>
            <td class="col-title title-cell">
                <p class="doc-title">{{ $title }}</p>
                <p class="doc-sub">Clave del servicio: <strong>{{ $val($data['code_reference'] ?? null) }}</strong></p>
                <p class="doc-sub">Fecha: <strong>{{ $val($data['fecha'] ?? now()->format('d/m/Y')) }}</strong></p>
                <span class="badge">Consulta inicial</span>
            </td>
        </tr>
        <tr>
            <td class="header-rule-space" colspan="2">&nbsp;</td>
        </tr>
    </table>

    <div class="two-col-section">
        <div class="section-title">Datos del paciente</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Paciente</div>
                    <div class="value">{{ $val($data['name_patient'] ?? null) }}</div>
                </td>
                <td>
                    <div class="label">Cédula</div>
                    <div class="value-muted">{{ $val($data['ci_patient'] ?? null) }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Edad</div>
                    <div class="value-muted">{{ $val($data['age_patient'] ?? null) }}</div>
                </td>
                <td>
                    <div class="label">Tipo de servicio</div>
                    <div class="value-muted">Telemedicina</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title section-title--block">Motivo de consulta</div>
    <div class="prose-box">{{ $val($data['reason'] ?? null) }}</div>

    <div class="section-title section-title--block">Enfermedad actual</div>
    <div class="prose-box">{{ $val($data['actual_phatology'] ?? null) }}</div>

    <div class="section-title section-title--block">Antecedentes</div>
    <div class="prose-box">{{ $val($data['background'] ?? null) }}</div>

    @if($isLong)
        <div class="keep-together">
            <div class="section-title section-title--block">Signos vitales</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:20%">Presión arterial</th>
                        <th style="width:20%">Frecuencia cardíaca</th>
                        <th style="width:20%">Frecuencia respiratoria</th>
                        <th style="width:20%">Temperatura</th>
                        <th style="width:20%">Saturación</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">{{ $val($data['pa'] ?? null) }}</td>
                        <td class="center">{{ $val($data['fc'] ?? null) }}</td>
                        <td class="center">{{ $val($data['fr'] ?? null) }}</td>
                        <td class="center">{{ $val($data['temp'] ?? null) }}</td>
                        <td class="center">{{ $val($data['saturacion'] ?? null) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif

    <div class="keep-together">
        <div class="section-title section-title--block">Medidas antropométricas</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Peso</div>
                    <div class="value">{{ $val($data['peso'] ?? null) }} kg</div>
                </td>
                <td>
                    <div class="label">Estatura</div>
                    <div class="value">{{ $val($data['estatura'] ?? null) }} m</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">IMC</div>
                    <div class="value">{{ $val($data['imc'] ?? null) }}</div>
                </td>
                <td></td>
            </tr>
        </table>
    </div>

    <div class="section-title section-title--block">Impresión diagnóstica</div>
    <div class="prose-box">{{ $val($data['diagnostic_impression'] ?? null) }}</div>

    <div class="keep-together">
        <div class="section-title section-title--block">Plan terapéutico</div>
        @if($medications === [])
            <p class="items-empty">Sin medicamentos indicados.</p>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:42%">Medicamento</th>
                        <th style="width:58%">Indicaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($medications as $item)
                        <tr>
                            <td>{{ $val(is_array($item) ? ($item['medicines'] ?? null) : $item) }}</td>
                            <td>{{ $val(is_array($item) ? ($item['indications'] ?? null) : null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="keep-together">
        <div class="section-title section-title--block">Paraclínicos</div>
        <table class="grid">
            <tr>
                <td>
                    @if($labs === [])
                        <p class="items-empty">Sin laboratorios indicados.</p>
                    @else
                        <table class="items">
                            <thead>
                                <tr>
                                    <th>Laboratorios</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($labs as $lab)
                                    <tr>
                                        <td>{{ $val($lab) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
                <td>
                    @if($studies === [])
                        <p class="items-empty">Sin exámenes indicados.</p>
                    @else
                        <table class="items">
                            <thead>
                                <tr>
                                    <th>Exámenes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($studies as $study)
                                    <tr>
                                        <td>{{ $val($study) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
        </table>
    </div>
</div>
</div>
</div>

<div class="footer-fixed">
    Documento generado por el <span class="footer-brand">departamento de telemedicina de Tu Doctor en Casa</span>.<br>
    Uso clínico; la reproducción no autorizada puede estar restringida según políticas de la organización.
</div>
</body>
</html>
