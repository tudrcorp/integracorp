@php
    /**
     * Cuerpo del Informe administrativo en PDF. Maquetado con tablas y estilos simples
     * para DomPDF; los gráficos son SVG dibujados en el servidor, embebidos como imagen.
     *
     * @var array{title: string, period: string, scope: string, generated_at: string, sections: list<array<string, mixed>>} $report
     * @var string $logoSrc
     * @var array<int, float> $chartScales  Escala por bloque que fija el acomodo en dos pasadas ({@see \App\Support\Administration\Insights\AdministrativeReportPdf}).
     * @var array<string, float> $blockSpacing  Espacio extra (mm) sobre un bloque, para que su página llegue al pie.
     */
    $chartScales = $chartScales ?? [];
    $blockSpacing = $blockSpacing ?? [];
    $spacing = static fn (string $id): string => isset($blockSpacing[$id]) ? ' style="margin-top:'.round($blockSpacing[$id], 2).'mm;"' : '';
    $unit = 0;
    $chart = static function (?string $svg, float $widthMm, int $unit) use ($chartScales): string {
        if ($svg === null || $svg === '') {
            return '';
        }

        $width = round($widthMm * ($chartScales[$unit] ?? 1.0), 2);

        return '<img id="adm-chart-'.$unit.'" src="'.\App\Support\Administration\Insights\SvgChart::dataUri($svg).'" style="width:'.$width.'mm;">';
    };
@endphp
<div class="adm-report">
    <table class="adm-header">
        <tr>
            <td class="adm-header-logo">
                @if (filled($logoSrc ?? ''))
                    <img src="{{ $logoSrc }}" alt="Tu Doctor en Casa" style="height:34px;">
                @endif
            </td>
            <td class="adm-header-title">
                <div class="adm-title">{{ mb_strtoupper($report['title']) }}</div>
                <div class="adm-subtitle">Administración · IntegraCorp</div>
            </td>
        </tr>
    </table>
    <table class="adm-band">
        <tr>
            <td><span class="adm-pill">{{ $report['period'] }}</span></td>
            <td style="text-align:right;">{{ $report['scope'] }}</td>
        </tr>
    </table>

    <div class="adm-card adm-intro">
        @php($titles = array_map(static fn (array $section): string => \Illuminate\Support\Str::lower($section['title']), $report['sections']))
        A continuación se presenta el informe administrativo del periodo con {{ count($titles) === 1 ? 'la sección' : 'las secciones' }}:
        {{ count($titles) > 1 ? implode(', ', array_slice($titles, 0, -1)).' y '.end($titles) : ($titles[0] ?? '') }}.
        Generado el {{ $report['generated_at'] }}.
    </div>

    @foreach ($report['sections'] as $section)
        {{--
            Sin saltos de página forzados: el contenido fluye. Solo viajan juntos el encabezado
            de la sección con sus cifras y cada título con su gráfico; las tablas pueden seguir
            en la página siguiente. Así no quedan huecos ni títulos sueltos al pie.
        --}}
        <div class="adm-section">
            {{-- El encabezado y sus cifras van juntos: pueden aprovechar el espacio al pie de una página. --}}
            <div class="adm-keep" id="adm-head-{{ $loop->index }}"{!! $spacing('adm-head-'.$loop->index) !!}>
                <div class="adm-ribbon">{{ mb_strtoupper($section['title']) }}</div>
                <p class="adm-section-intro">{{ $section['intro'] }}</p>
                <table class="adm-kpis">
                    <tr>
                        @foreach ($section['kpis'] as $kpi)
                            <td>
                                <div class="adm-kpi-label">{{ mb_strtoupper($kpi['label']) }}</div>
                                <div class="adm-kpi-value">{{ $kpi['value'] }}</div>
                                @if (filled($kpi['detail'] ?? null))
                                    <div class="adm-kpi-detail">{{ $kpi['detail'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>

            @foreach ($section['blocks'] as $block)
                @php($side = $block['layout'] === \App\Support\Administration\Insights\AdministrativeReportBuilder::LAYOUT_SIDE)
                {{--
                    Un bloque con tabla corta (dona con su tabla, o tablas por mes de hasta 4 filas)
                    viaja entero: título, gráfico, tabla y lectura. Así el gráfico nunca queda solo al
                    pie con su tabla en la página siguiente. Solo las tablas largas (rankings) siguen
                    en la otra página, con su encabezado repetido.
                --}}
                @php($whole = $side || count($block['table']['rows'] ?? []) <= \App\Support\Administration\Insights\AdministrativeReportPdf::WHOLE_BLOCK_MAX_ROWS)
                @php($unit++)
                <div class="adm-keep" id="adm-keep-{{ $unit }}"{!! $spacing('adm-keep-'.$unit) !!}>
                    <div @class(['adm-card', 'adm-card-top' => ! $whole])>
                        <div class="adm-block-title">{{ mb_strtoupper($block['title']) }}</div>
                        @if ($side)
                            <table class="adm-side">
                                <tr>
                                    <td class="adm-side-chart">{!! $chart($block['chart'], \App\Support\Administration\Insights\AdministrativeReportPdf::DONUT_WIDTH_MM, $unit) !!}</td>
                                    <td class="adm-side-table">@include('documents.partials.administrative-report-table', ['table' => $block['table']])</td>
                                </tr>
                            </table>
                        @elseif (filled($block['chart']))
                            <div class="adm-chart">{!! $chart($block['chart'], \App\Support\Administration\Insights\AdministrativeReportPdf::CHART_WIDTH_MM, $unit) !!}</div>
                        @endif
                        @if ($whole)
                            @unless ($side)
                                @include('documents.partials.administrative-report-table', ['table' => $block['table']])
                            @endunless
                            @if (filled($block['note']))
                                <p class="adm-note">{{ $block['note'] }}</p>
                            @endif
                        @endif
                    </div>
                </div>
                @unless ($whole)
                    <div class="adm-card adm-card-bottom">
                        @include('documents.partials.administrative-report-table', ['table' => $block['table']])
                        @if (filled($block['note']))
                            <p class="adm-note">{{ $block['note'] }}</p>
                        @endif
                    </div>
                @endunless
            @endforeach
        </div>
    @endforeach
</div>
