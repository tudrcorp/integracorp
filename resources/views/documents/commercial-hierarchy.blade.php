<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Jerarquía comercial</title>

    <style>
        /*
         * DejaVu Sans y no Helvetica: los conectores «└─» y el separador «›» de la ruta
         * solo existen en esta fuente; con Arial o Helvetica DomPDF imprimiría «?».
         */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #0f172a;
            margin: 0;
        }

        @page {
            margin: 28px 26px 34px 26px;
        }

        table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .brand {
            border-bottom: 2px solid #052f60;
            margin-bottom: 14px;
        }

        /*
         * El aire entre el logo y la línea azul va en el padding de las celdas, no en el de
         * la tabla: con `border-collapse: collapse` el padding del propio <table> se ignora.
         */
        .brand td {
            vertical-align: middle;
            border: 0;
            padding: 0 0 14px 0;
        }

        .brand img {
            width: 150px;
            height: auto;
        }

        .brand .title {
            text-align: right;
        }

        .brand h1 {
            margin: 0;
            font-size: 15px;
            color: #052f60;
        }

        .brand p {
            margin: 3px 0 0;
            font-size: 8px;
            color: #64748b;
        }

        /* Resumen: una fila por dato para que DomPDF no tenga que repartir anchos. */
        .summary {
            margin-bottom: 12px;
        }

        .summary td {
            border: 1px solid #e2e8f0;
            padding: 5px 7px;
            vertical-align: top;
        }

        .summary td.k {
            background: #f1f5f9;
            font-weight: bold;
            color: #334155;
            width: 18%;
        }

        .counters td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            text-align: center;
        }

        .counters .value {
            font-size: 14px;
            font-weight: bold;
            color: #052f60;
        }

        .counters .label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }

        /* Cabecera repetida en cada página: la jerarquía puede ocupar varias hojas. */
        .tree thead {
            display: table-header-group;
        }

        .tree th {
            background: #052f60;
            color: #ffffff;
            border: 1px solid #052f60;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
        }

        .tree tr {
            page-break-inside: avoid;
        }

        .tree td {
            border: 1px solid #e2e8f0;
            padding: 4px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .tree .level {
            text-align: center;
            color: #64748b;
        }

        .node-name {
            font-weight: bold;
        }

        .node-type {
            display: block;
            margin-top: 2px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .node-path {
            display: block;
            margin-top: 2px;
            font-size: 7px;
            color: #94a3b8;
        }

        .footnote {
            margin-top: 10px;
            font-size: 7.5px;
            color: #64748b;
            text-align: justify;
        }

        .page-number {
            position: fixed;
            bottom: -24px;
            left: 0;
            right: 0;
            text-align: right;
            font-size: 7px;
            color: #94a3b8;
        }

        .page-number:after {
            content: "Página " counter(page);
        }
    </style>
</head>

<body>
    @php
        /** Mismos tonos que el diagrama en pantalla, para que el reporte se lea igual. */
        $toneFor = fn (string $type): string => match ($type) {
            'Casa matriz' => '#1d4ed8',
            'Agencia master' => '#047857',
            'Agencia general' => '#b45309',
            'Subagente' => '#475569',
            default => '#6d28d9',
        };
    @endphp

    <div class="page-number"></div>

    <table class="brand">
        <tr>
            <td>
                <img src="{{ public_path('image/logoNewPdf.png') }}" alt="Tu Dr En Casa">
            </td>
            <td class="title">
                <h1>Jerarquía comercial</h1>
                <p>Generado: {{ $generatedAt }} · {{ config('app.name') }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            <td class="k">Agencia</td>
            <td>{{ $agency->name_corporative ?? 'Sin razón social' }}</td>
            <td class="k">Código</td>
            <td>{{ $agency->code ?? 'Sin código' }}</td>
        </tr>
    </table>

    <table class="counters">
        <tr>
            <td>
                <div class="value">{{ $totals['generals'] }}</div>
                <div class="label">Agencias generales</div>
            </td>
            <td>
                <div class="value">{{ $totals['agents'] }}</div>
                <div class="label">Agentes</div>
            </td>
            <td>
                <div class="value">{{ $totals['subagents'] }}</div>
                <div class="label">Subagentes</div>
            </td>
            <td>
                <div class="value">{{ $totals['total'] }}</div>
                <div class="label">Nodos exportados</div>
            </td>
        </tr>
    </table>

    <table class="tree" style="margin-top: 12px;">
        <thead>
            <tr>
                <th style="width: 7%;">Nivel</th>
                <th style="width: 45%;">Jerarquía</th>
                <th style="width: 15%;">Código</th>
                <th style="width: 12%;">Estatus</th>
                <th style="width: 21%;">Depende de</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="level">{{ $row['level'] }}</td>
                    <td style="padding-left: {{ 6 + (($row['level'] - 1) * 14) }}px; border-left: 3px solid {{ $toneFor($row['type']) }};">
                        <span class="node-name">{{ $row['level'] > 1 ? '└─ ' : '' }}{{ $row['name'] }}</span>
                        <span class="node-type" style="color: {{ $toneFor($row['type']) }};">{{ $row['type'] }}</span>
                        <span class="node-path">{{ $row['path'] }}</span>
                    </td>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['parent_code'] !== '' ? $row['parent_code'] : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 12px;">
                        Esta agencia todavía no tiene estructura comercial registrada.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footnote">
        Cada fila cuelga de la que indica la columna «Depende de». El nivel y la sangría marcan la profundidad
        (1 es la cabecera de la red) y la ruta bajo cada nombre muestra la cadena completa de códigos desde la
        agencia master hasta el nodo.
    </p>
</body>

</html>
