@php
    /** @var \App\Models\PlanGenerator $planGenerator */
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    /** @var array<string, array<string, mixed>> $rateRows */
    /** @var list<array{page_number: int, is_plan_page: bool, image_data_uri: string}> $quotationPages */
    /** @var \Illuminate\Support\Carbon $generatedAt */

    /** @var \Illuminate\Support\Carbon $generatedAt */
    /** @var string $brandColor */
    /** @var string $brandColorBorder */

    $brandColor = $brandColor ?? '#1d4ed8';
    $brandColorBorder = $brandColorBorder ?? '#1e40af';
    $columnCount = count($columns);
    $useQuotationBody = (bool) ($useQuotationBody ?? false);
    $quotationPageTotal = count($quotationPages);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $planGenerator->name ?? 'Plan generado' }}</title>
    <style>
        /*
         * DomPDF: márgenes con padding en <td> (no @page ni padding en div al 100% del ancho).
         */
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #1f2937;
        }

        .pdf-page-break {
            page-break-after: always;
        }

        .pdf-plan-margin-frame,
        .pdf-image-frame {
            width: 100%;
            border-collapse: collapse;
            border: 0;
        }

        .pdf-plan-margin-cell {
            padding: 20mm;
            vertical-align: top;
        }

        .pdf-plan-margin-cell .header {
            margin-bottom: 4px;
            padding-bottom: 4px;
        }

        .pdf-plan-margin-cell .proposal-table {
            margin-bottom: 12px;
        }

        .pdf-plan-margin-cell .proposal-block {
            margin-bottom: 12px;
        }

        .pdf-plan-margin-cell .section-title {
            margin: 12px 0 4px 0;
            font-size: 7pt;
        }

        .pdf-plan-margin-cell .matrix-table {
            margin-bottom: 12px;
        }

        .pdf-plan-margin-cell .matrix-table th,
        .pdf-plan-margin-cell .matrix-table td {
            font-size: 6.5pt;
            padding: 2px 2px;
        }

        .pdf-plan-margin-cell .matrix-section {
            padding-bottom: 2px;
        }

        .pdf-plan-margin-cell .matrix-section + .matrix-section {
            padding-top: 0;
        }

        .pdf-plan-margin-cell .footer {
            margin-top: 4px;
        }

        .pdf-image-frame td {
            width: 100%;
            height: 297mm;
            padding: 0;
            margin: 0;
            text-align: center;
            vertical-align: middle;
        }

        .pdf-image-frame img {
            display: inline-block;
            max-width: 210mm;
            max-height: 297mm;
            width: auto;
            height: auto;
        }

        .header {
            border-bottom: 2px solid {{ $brandColor }};
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .header table {
            width: 100%;
            border-collapse: collapse;
        }

        .logo {
            width: 64px;
        }

        .title {
            text-align: right;
        }

        .title h1 {
            margin: 0;
            font-size: 11pt;
            color: {{ $brandColor }};
        }

        .title p {
            margin: 1px 0 0 0;
            font-size: 7pt;
            color: #6b7280;
        }

        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }

        .matrix-table th,
        .matrix-table td {
            border: 1px solid #cbd5e1;
            padding: 3px 2px;
            vertical-align: middle;
            font-size: 7pt;
            word-wrap: break-word;
        }

        .matrix-table thead th {
            background: {{ $brandColor }};
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }

        .matrix-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .benefit-col {
            text-align: left;
        }

        .check {
            color: #16a34a;
            font-weight: bold;
            font-size: 8pt;
        }

        .amount {
            font-weight: 600;
            color: #0f172a;
            font-size: 6.5pt;
        }

        .dash {
            color: #9ca3af;
        }

        .section-title {
            margin: 12px 0 4px 0;
            font-size: 7.5pt;
            font-weight: bold;
            color: {{ $brandColor }};
            text-transform: uppercase;
        }

        .matrix-section + .matrix-section .section-title {
            margin-top: 14px;
        }

        .rate-value {
            font-weight: bold;
        }

        .group-total-bold {
            font-weight: bold;
        }

        .proposal-title {
            margin: 0 0 6px 0;
            font-size: 9pt;
            font-weight: bold;
            font-style: italic;
            color: {{ $brandColor }};
            text-align: left;
        }

        .proposal-block {
            text-align: left;
            margin-bottom: 14px;
        }

        .proposal-table {
            width: auto;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .proposal-table td {
            padding: 3px 8px 3px 0;
            font-size: 7pt;
            vertical-align: middle;
        }

        .proposal-label {
            width: auto;
            text-align: left;
            color: #374151;
            font-weight: 600;
            white-space: nowrap;
            padding-right: 10px;
        }

        .proposal-value {
            background: #f3f4f6;
            border-radius: 4px;
            padding: 4px 8px;
            color: #111827;
            font-weight: 600;
        }

        .footer {
            margin-top: 8px;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            font-size: 6.5pt;
            text-align: center;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    @if ($useQuotationBody)
        @foreach ($quotationPages as $index => $page)
            @if ($page['is_plan_page'])
                <table
                    class="pdf-plan-margin-frame {{ $index < $quotationPageTotal - 1 ? 'pdf-page-break' : '' }}"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        <td class="pdf-plan-margin-cell">
                            @include('documents.partials.plan-generator-plan-body')
                        </td>
                    </tr>
                </table>
            @elseif ($page['image_data_uri'] !== '')
                <table
                    class="pdf-image-frame {{ $index < $quotationPageTotal - 1 ? 'pdf-page-break' : '' }}"
                    cellpadding="0"
                    cellspacing="0"
                >
                    <tr>
                        <td>
                            <img src="{{ $page['image_data_uri'] }}" alt="Página {{ $page['page_number'] }}">
                        </td>
                    </tr>
                </table>
            @endif
        @endforeach
    @else
        <table class="pdf-plan-margin-frame" cellpadding="0" cellspacing="0">
            <tr>
                <td class="pdf-plan-margin-cell">
                    @include('documents.partials.plan-generator-plan-body')
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
