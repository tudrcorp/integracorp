@php
    /** @var array<string, mixed> $ficha */
    $admin = $ficha['administration'];
    $clinical = $ficha['clinical'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del afiliado · {{ $ficha['name'] }}</title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #1f2937;
            margin: 0;
        }

        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            height: 8mm;
            font-size: 6.5px;
            color: #64748b;
            border-top: 1px solid #0cb7f2;
        }

        .footer td { padding-top: 3px; }

        table { border-collapse: collapse; width: 100%; table-layout: fixed; }

        .head td { vertical-align: middle; }
        .head .title { font-size: 15px; font-weight: bold; color: #052f60; }
        .head .subtitle { font-size: 9px; color: #475569; padding-top: 2px; }
        .head .logo { text-align: right; }
        .head .logo img { height: 34px; }

        .badges td { padding-top: 6px; }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 8px;
            font-size: 7px;
            font-weight: bold;
            color: #052f60;
            background: #e0f2fe;
            margin-right: 4px;
        }
        .badge-scope { color: #065f46; background: #d1fae5; }
        .badge-clinical { color: #9a3412; background: #ffedd5; }

        .part {
            margin-top: 12px;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
            background: #052f60;
        }
        .part-clinical { background: #0f766e; }

        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #052f60;
            padding: 8px 0 3px 0;
            border-bottom: 1px solid #cbd5e1;
        }

        .pairs td { padding: 3px 4px; vertical-align: top; border-bottom: 1px solid #f1f5f9; }
        .pairs td.label { width: 17%; font-weight: bold; color: #334155; }
        .pairs td.value { width: 33%; color: #111827; }

        .grid { margin-top: 3px; }
        .grid th {
            background: #f1f5f9;
            color: #334155;
            font-size: 7px;
            text-align: left;
            padding: 3px;
            border: 1px solid #e2e8f0;
        }
        .grid td {
            font-size: 7px;
            padding: 3px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            word-wrap: break-word;
        }
        .grid tr { page-break-inside: avoid; }

        .muted { color: #64748b; font-style: italic; padding: 4px 0; }
        .note { color: #64748b; font-size: 6.5px; padding-top: 2px; }
        .confidential {
            margin-top: 6px;
            padding: 4px 6px;
            font-size: 7px;
            color: #9a3412;
            background: #fff7ed;
            border: 1px solid #fed7aa;
        }
    </style>
</head>
<body>
    <table class="footer">
        <tr>
            <td style="width: 70%;">Ficha del afiliado · {{ $ficha['name'] }} · {{ $ficha['document'] }} · Generada el {{ $generatedAt }}</td>
            <td style="width: 30%; text-align: right;">Documento interno de Tu Dr. Group</td>
        </tr>
    </table>

    <table class="head">
        <tr>
            <td style="width: 70%;">
                <div class="title">Ficha del afiliado</div>
                <div class="subtitle">{{ $ficha['name'] }} · {{ $ficha['document'] }}</div>
            </td>
            <td class="logo" style="width: 30%;">
                @if ($logoDataUri !== '')
                    <img src="{{ $logoDataUri }}" alt="Tu Dr. Group">
                @endif
            </td>
        </tr>
    </table>
    <table class="badges">
        <tr>
            <td>
                <span class="badge">{{ $ficha['kindLabel'] }} · {{ $ficha['affiliationCode'] }}</span>
                <span class="badge">Estatus: {{ $ficha['status'] }}</span>
                <span class="badge badge-scope">Información: {{ $ficha['scopeLabel'] }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Datos del afiliado</div>
    @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $ficha['affiliateRows']])

    <div class="section-title">Afiliación</div>
    @if ($ficha['affiliationRows'] === [])
        <div class="muted">No se encontró la afiliación de este afiliado.</div>
    @else
        @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $ficha['affiliationRows']])
    @endif

    @if ($admin !== null)
        <div class="part">Administración</div>

        <div class="section-title">Tarifa</div>
        @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $admin['feeRows']])

        <div class="section-title">Pagador</div>
        @if ($admin['payerRows'] === [])
            <div class="muted">Sin datos del pagador.</div>
        @else
            @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $admin['payerRows']])
        @endif

        <div class="section-title">Upgrades</div>
        @include('documents.partials.affiliate-ficha-table', ['table' => $admin['upgrades']])

        <div class="section-title">Avisos de cobro de la afiliación</div>
        @if ($admin['collectionsSummary'] !== [])
            @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $admin['collectionsSummary']])
        @endif
        @include('documents.partials.affiliate-ficha-table', ['table' => $admin['collections']])

        <div class="section-title">Pagos de la afiliación</div>
        @include('documents.partials.affiliate-ficha-table', ['table' => $admin['payments']])

        <div class="section-title">Renovaciones del afiliado</div>
        @include('documents.partials.affiliate-ficha-table', ['table' => $admin['renovations']])
    @endif

    @if ($clinical !== null)
        <div class="part part-clinical">Operaciones y telemedicina</div>
        <div class="confidential">Contiene información médica confidencial. Uso exclusivo del personal autorizado de Tu Dr. Group.</div>

        <div class="section-title">Paciente</div>
        @include('documents.partials.affiliate-ficha-pairs', ['pairs' => [...$clinical['patientRows'], ...$clinical['healthRows']]])

        <div class="section-title">Cupos del plan</div>
        @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['entitlements']])

        @if ($clinical['linked'])
            <div class="section-title">Antecedentes</div>
            @include('documents.partials.affiliate-ficha-pairs', ['pairs' => $clinical['historyRows']])

            <div class="section-title">Casos de telemedicina</div>
            @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['cases']])

            <div class="section-title">Consultas</div>
            @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['consultations']])

            <div class="section-title">Medicamentos indicados</div>
            @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['medications']])

            <div class="section-title">Laboratorios, estudios y especialistas</div>
            @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['orders']])
        @endif

        <div class="section-title">Servicios coordinados por Operaciones</div>
        @include('documents.partials.affiliate-ficha-table', ['table' => $clinical['coordinations']])
    @endif
</body>
</html>
