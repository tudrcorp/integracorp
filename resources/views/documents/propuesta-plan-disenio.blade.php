@php
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
    use App\Support\QuotePdfCoverageTable;
    use App\Support\Quotes\Design\QuoteDesignPage;

    /** @var \App\Support\Quotes\Design\QuoteDesignTheme $theme */
    $c = static fn (string $key): string => $theme->color($key);
    $sans = $fonts['sans'];
    $mono = $fonts['mono'];
    $logo = $theme->absolutePath($theme->logoPath) ?? public_path('image/logoNewPdf.png');

    $columnCount = count($benefitColumns);
    $labelWidth = match (true) {
        $columnCount <= 1 => 70,
        $columnCount <= 2 => 60,
        $columnCount <= 4 => 46,
        default => 34,
    };
    $valueWidth = $columnCount > 0 ? (100 - $labelWidth) / $columnCount : 0;
    $benefitFont = $isDense ? '6.5pt' : '8pt';
    $hasUpgrades = ($upgrades['lines'] ?? []) !== [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $planName }}</title>
    <style>
        {{-- Fuentes subidas por el analista (TTF). Las rutas son locales: DomPDF no sale a internet. --}}
        @if ($fonts['face_regular'])
            @font-face { font-family: 'QdSans'; font-style: normal; font-weight: normal; src: url('{{ $fonts['face_regular'] }}') format('truetype'); }
        @endif
        @if ($fonts['face_bold'])
            @font-face { font-family: 'QdSans'; font-style: normal; font-weight: bold; src: url('{{ $fonts['face_bold'] }}') format('truetype'); }
        @endif
        @if ($fonts['face_mono'])
            @font-face { font-family: 'QdMono'; font-style: normal; font-weight: normal; src: url('{{ $fonts['face_mono'] }}') format('truetype'); }
            @font-face { font-family: 'QdMono'; font-style: normal; font-weight: bold; src: url('{{ $fonts['face_mono'] }}') format('truetype'); }
        @endif

        /*
         * Reglas DomPDF del proyecto (CLAUDE.md §4.2.2): márgenes con padding en celdas, un bloque
         * por fila de tabla, table-layout fijo con anchos explícitos y el ✓ con DejaVu en línea.
         * Nada de flex ni grid: DomPDF no los dibuja.
         */
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background-color: {{ $c('page_background') }}; }
        body { font-family: {!! $sans !!}, sans-serif; font-size: 8.5pt; color: {{ $c('text') }}; }

        .frame { width: 100%; border-collapse: collapse; }
        .cell { padding: 0 16mm; vertical-align: top; }
        .cell-first { padding-top: 13mm; }
        /* Reserva del pie fijo: el contenido nunca se monta encima. */
        .cell-last { padding-bottom: 24mm; }

        .header { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .header td { vertical-align: bottom; padding: 0; }
        .logo { max-height: 62px; max-width: 230px; }
        .label { font-size: 6.5pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: {{ $c('muted') }}; }
        .overline { font-size: 7.5pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; color: {{ $c('muted') }}; }
        .control { font-family: {!! $mono !!}, monospace; font-size: 12pt; color: {{ $c('primary') }}; }
        .rule { height: 1px; background-color: {{ $c('accent') }}; margin: 14px 0 16px 0; font-size: 1px; line-height: 1px; }
        h1 { margin: 6px 0 0 0; font-size: 21pt; font-weight: bold; line-height: 1.15; color: {{ $c('primary') }}; }

        .band { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 14px; background-color: {{ $c('band_background') }}; }
        .band td { padding: 9px 12px; vertical-align: top; }
        .band td + td { border-left: 1px solid {{ $c('border') }}; }
        .band .value { font-size: 9.5pt; margin-top: 4px; color: {{ $c('text') }}; }
        .band .mono { font-family: {!! $mono !!}, monospace; }
        .validity { margin: 8px 0 0 0; font-size: 8pt; font-style: italic; color: {{ $c('muted') }}; }

        .grid { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 16px; }
        .grid th { background-color: {{ $c('primary') }}; color: {{ $c('header_text') }}; font-size: 6.5pt; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; padding: 7px 10px; text-align: center; border: 1px solid {{ $c('primary') }}; }
        .grid th.left, .grid td.left { text-align: left; }
        .grid td { background-color: {{ $c('surface') }}; border-bottom: 1px solid {{ $c('border') }}; padding: 6px 10px; text-align: center; vertical-align: middle; word-wrap: break-word; }
        .grid td.amount { font-weight: bold; color: {{ $c('primary') }}; }
        .grid td.total-label { background-color: {{ $c('primary') }}; color: {{ $c('header_text') }}; font-weight: bold; font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; text-align: left; border-bottom: 1px solid {{ $c('primary') }}; }
        .grid td.gap { background-color: transparent; border: 0; padding: 0; height: 6px; font-size: 1px; line-height: 1px; }
        .muted-dash { color: {{ $c('border') }}; }

        h2 { margin: 16px 0 3px 0; font-size: 11pt; font-weight: bold; color: {{ $c('primary') }}; }
        .note { margin: 0; font-size: 8.5pt; line-height: 1.5; color: {{ $c('muted') }}; }

        /* Celdas separadoras en vez de border-spacing: así la fila llega justo a los bordes de la tabla de arriba. */
        .cards { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; }
        .cards td.card { width: 32%; background-color: {{ $c('surface') }}; border: 1px solid {{ $c('border') }}; padding: 9px 11px; vertical-align: top; }
        .cards td.card.featured { border-top: 2px solid {{ $c('accent') }}; }
        .cards td.spacer { width: 2%; border: 0; padding: 0; }
        .figure { font-size: 19pt; color: {{ $c('primary') }}; line-height: 1.1; margin-top: 6px; }
        .figure small { font-size: 8.5pt; font-weight: bold; }

        .conditions { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .conditions td { padding: 0 0 4px 0; vertical-align: top; font-size: 8pt; line-height: 1.4; color: {{ $c('text') }}; }
        .conditions td.bullet { width: 12px; color: {{ $c('accent') }}; font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; padding-top: 1px; }

        .upgrade-note { margin: 4px 0 0 0; font-size: 7pt; color: {{ $c('muted') }}; }

        .footer { position: fixed; left: 0; right: 0; bottom: 0; height: 17mm; }
        .footer-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .footer-table td { padding: 0 16mm 0 16mm; vertical-align: middle; height: 12mm; }
        .footer-line { border-top: 1px solid {{ $c('border') }}; padding-top: 7px; }
        .footer-left { font-family: {!! $mono !!}, monospace; font-size: 7.5pt; color: {{ $c('muted') }}; }
        .footer-right { text-align: right; font-size: 7.5pt; color: {{ $c('muted') }}; }
        .footer-brand { font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: {{ $c('text') }}; font-size: 8pt; }

        /* Una sola hoja: si la versión normal no cabe, se vuelve a dibujar más apretada. */
        .is-fit .cell-first { padding-top: 9mm; }
        .is-fit h1 { font-size: 16pt; }
        .is-fit .rule { margin: 8px 0 10px 0; }
        .is-fit .band td { padding: 6px 10px; }
        .is-fit .grid { margin-top: 10px; }
        .is-fit .grid th { padding: 4px 6px; }
        .is-fit .grid td { padding: 3px 6px; font-size: 7pt; }
        .is-fit h2 { margin-top: 10px; font-size: 9.5pt; }
        .is-fit .note { font-size: 7.5pt; line-height: 1.3; }
        .is-fit .figure { font-size: 14pt; }
        .is-fit .conditions td { font-size: 7pt; padding-bottom: 2px; }
    </style>
</head>
<body>
<div class="{{ $fitToPage ? 'is-fit' : '' }}">
    {{-- Pie fijo antes del contenido: DomPDF lo repite en cada hoja si la página se parte. --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td>
                    <table class="footer-table" style="table-layout: fixed;">
                        <tr>
                            <td class="footer-line footer-left" style="padding: 7px 0 0 0;">{{ $theme->footerLeft }}</td>
                            <td class="footer-line footer-right" style="padding: 7px 0 0 0;">
                                @if ($theme->footerRightPrefix !== '')
                                    {{ $theme->footerRightPrefix }}&nbsp;
                                @endif
                                <span class="footer-brand">{{ $theme->footerRightBrand }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <table class="frame">
        <tr>
            <td class="cell cell-first">
                <table class="header">
                    <tr>
                        <td style="width: 62%;">
                            <img class="logo" src="{{ $logo }}" alt="">
                        </td>
                        <td style="width: 38%; text-align: right;">
                            <div class="label">Nro. control</div>
                            <div class="control">{{ $controlNumber }}</div>
                        </td>
                    </tr>
                </table>

                <div class="rule">&nbsp;</div>

                @if ($theme->overline !== '')
                    <div class="overline">{{ $theme->overline }}</div>
                @endif
                <h1>{{ $planName }}</h1>

                <table class="band">
                    <tr>
                        <td style="width: 42%;">
                            <div class="label">{{ $clientLabel }}</div>
                            <div class="value" style="font-weight: bold;">{{ $clientName }}</div>
                        </td>
                        <td style="width: 33%;">
                            <div class="label">Agente</div>
                            <div class="value">{{ $agentName !== '' ? $agentName : '—' }}</div>
                        </td>
                        <td style="width: 25%;">
                            <div class="label">Fecha de emisión</div>
                            <div class="value mono">{{ $issuedAt }}</div>
                        </td>
                    </tr>
                </table>
                <p class="validity">{{ $theme->validityNote }}</p>
            </td>
        </tr>

        <tr>
            <td class="cell">
                @if ($benefitColumns === [] || $benefitRows === [])
                    <p class="note" style="margin-top: 14px;">Este plan todavía no tiene beneficios y coberturas configurados.</p>
                @else
                    <table class="grid">
                        <thead>
                            <tr>
                                <th class="left" style="width: {{ $labelWidth }}%;">{{ $theme->benefitsHeading }}</th>
                                @foreach ($benefitColumns as $benefitColumn)
                                    <th style="width: {{ $valueWidth }}%;">{{ $benefitColumn['header_label'] ?? '—' }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($benefitRows as $benefitRow)
                                <tr>
                                    <td class="left" style="font-size: {{ $benefitFont }};">{{ $loop->iteration }}. {{ $benefitRow['benefit_label'] ?? '—' }}</td>
                                    @foreach ($benefitColumns as $benefitColumn)
                                        @php
                                            $cell = (array) data_get($benefitRow, 'cells.'.($benefitColumn['column_key'] ?? ''), []);
                                            $coverageAmount = $cell['coverage_amount'] ?? null;
                                            $display = PlanGeneratorPreviewBuilder::benefitCellDisplay((bool) ($cell['is_selected'] ?? false), $coverageAmount);
                                        @endphp
                                        <td style="font-size: {{ $benefitFont }};">
                                            @if ($display === 'amount')
                                                <span style="font-weight: bold; color: {{ $c('primary') }};">US$ {{ PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverageAmount) }}</span>
                                            @elseif ($display === 'check')
                                                {{-- DejaVu en línea: es la única fuente con el glifo ✓ (CLAUDE.md §4.2.2). --}}
                                                <span style="font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 10pt; line-height: 1; color: {{ $c('check') }};">✓</span>
                                            @else
                                                <span class="muted-dash">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($theme->showNote && ($theme->noteTitle !== '' || $theme->noteBody !== ''))
                    @if ($theme->noteTitle !== '')
                        <h2>{{ $theme->noteTitle }}</h2>
                    @endif
                    <p class="note">{{ $theme->noteBody }}</p>
                @endif
            </td>
        </tr>

        <tr>
            <td class="cell">
                @if ($summary !== null)
                    {{-- Una sola columna de precio: población por rango y el total del grupo en tarjetas. --}}
                    <table class="grid">
                        <thead>
                            <tr>
                                <th class="left" style="width: 40%;">Rango de edad</th>
                                <th style="width: 25%;">Población</th>
                                <th style="width: 35%; text-align: right;">
                                    Tarifa anual
                                    @if ($summary['coverage_label'] !== null)
                                        <br><span style="letter-spacing: 0; font-weight: normal;">Cobertura US$ {{ $summary['coverage_label'] }}</span>
                                    @endif
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['rows'] as $row)
                                <tr>
                                    <td class="left" style="font-weight: bold;">{{ $row['age_range'] }} años</td>
                                    <td>{{ $row['persons'] }} persona(s)</td>
                                    <td class="amount" style="text-align: right;">{{ QuoteDesignPage::money($row['annual']) }} US$</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <table class="cards">
                        <tr>
                            @foreach ([['Tarifa grupal anual', $summary['annual'], true], ['Tarifa grupal semestral', $summary['biannual'], false], ['Tarifa grupal trimestral', $summary['quarterly'], false]] as [$cardLabel, $cardAmount, $featured])
                                @if (! $loop->first)
                                    <td class="spacer"></td>
                                @endif
                                <td class="card{{ $featured ? ' featured' : '' }}">
                                    <div class="label">{{ $cardLabel }}</div>
                                    <div class="figure">{{ QuoteDesignPage::money($cardAmount) }}<small> US$</small></div>
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @else
                    {{-- Varias coberturas: la matriz completa, con los totales en la misma tabla para que alineen. --}}
                    <table class="grid">
                        <thead>
                            <tr>
                                <th class="left" style="width: 24%;">Rango de edad</th>
                                <th style="width: 16%;">Población</th>
                                @foreach ($coverageColumns as $coverage)
                                    <th>Tarifa anual<br>US$ {{ QuotePdfCoverageTable::formatLabel($coverage) }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tableRows as $row)
                                <tr>
                                    <td class="left" style="font-weight: bold;">{{ $row['age_range'] }} años</td>
                                    <td>{{ $row['total_persons'] }} persona(s)</td>
                                    @foreach ($coverageColumns as $coverage)
                                        @php $amount = $row['amounts'][QuotePdfCoverageTable::coverageKey($coverage)] ?? null; @endphp
                                        <td class="amount">{{ $amount !== null ? QuoteDesignPage::money((float) $amount).' US$' : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr><td class="gap" colspan="{{ 2 + count($coverageColumns) }}"></td></tr>
                            @foreach ([['Tarifa grupal anual', 1], ['Tarifa grupal semestral', 2], ['Tarifa grupal trimestral', 4]] as [$totalLabel, $divisor])
                                <tr>
                                    <td class="total-label" colspan="2">{{ $totalLabel }}</td>
                                    @foreach ($coverageColumns as $coverage)
                                        @php $total = $totals[QuotePdfCoverageTable::coverageKey($coverage)] ?? null; @endphp
                                        <td class="amount">{{ $total !== null ? QuoteDesignPage::money($total / $divisor).' US$' : '-' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </td>
        </tr>

        @if ($hasUpgrades)
            <tr>
                <td class="cell">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th class="left" style="width: 55%;">Upgrades opcionales</th>
                                <th style="width: 20%;">Personas</th>
                                <th style="width: 25%;">Pago único anual</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($upgrades['lines'] as $upgradeLine)
                                <tr>
                                    <td class="left"><strong>{{ $upgradeLine['name'] ?? '' }}</strong><br>{{ $upgradeLine['coverage'] ?? '' }}</td>
                                    <td>{{ (int) ($upgradeLine['persons'] ?? 0) }} persona(s)</td>
                                    <td class="amount">{{ number_format((float) ($upgradeLine['annual'] ?? 0), 2, ',', '.') }} US$</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="upgrade-note">{{ \App\Support\Upgrades\UpgradeInstallments::RULE }} Las demás cuotas llevan solo el plan.</p>
                </td>
            </tr>
        @endif

        <tr>
            <td class="cell cell-last">
                @if ($theme->conditions !== [])
                    <table class="conditions">
                        @foreach ($theme->conditions as $condition)
                            <tr>
                                <td class="bullet">▸</td>
                                <td>{{ $condition }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
