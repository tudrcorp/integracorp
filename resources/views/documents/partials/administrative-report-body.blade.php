@php
    /**
     * Cuerpo del Informe administrativo en PDF. Maquetado con tablas y estilos simples
     * para DomPDF; los gráficos son SVG dibujados en el servidor, embebidos como imagen.
     *
     * @var array{title: string, period: string, scope: string, generated_at: string, sections: list<array<string, mixed>>} $report
     * @var string $logoSrc
     */
    $chart = static fn (?string $svg, string $width = '100%'): string => $svg === null || $svg === ''
        ? ''
        : '<img src="'.\App\Support\Administration\Insights\SvgChart::dataUri($svg).'" style="width:'.$width.';">';
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
        A continuación se presenta el informe administrativo del periodo: lo cobrado, las afiliaciones nuevas, las ventas por tipo,
        plan, agencia y agente, la cartera por cobrar y las bajas. Generado el {{ $report['generated_at'] }}.
    </div>

    @foreach ($report['sections'] as $section)
        {{--
            Sin saltos de página forzados: el contenido fluye. Solo viajan juntos el encabezado
            de la sección con sus cifras y cada título con su gráfico; las tablas pueden seguir
            en la página siguiente. Así no quedan huecos ni títulos sueltos al pie.
        --}}
        <div class="adm-section">
            {{-- El encabezado y sus cifras van juntos: pueden aprovechar el espacio al pie de una página. --}}
            <div class="adm-keep">
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
                <div class="adm-keep">
                    <div class="adm-card adm-card-top">
                        <div class="adm-block-title">{{ mb_strtoupper($block['title']) }}</div>
                        @if ($side)
                            {{-- Dona y su tabla son cortas: van juntas. --}}
                            <table class="adm-side">
                                <tr>
                                    <td class="adm-side-chart">{!! $chart($block['chart'], '46mm') !!}</td>
                                    <td class="adm-side-table">@include('documents.partials.administrative-report-table', ['table' => $block['table']])</td>
                                </tr>
                            </table>
                        @elseif (filled($block['chart']))
                            <div class="adm-chart">{!! $chart($block['chart']) !!}</div>
                        @endif
                    </div>
                </div>
                <div class="adm-card adm-card-bottom">
                    @unless ($side)
                        @include('documents.partials.administrative-report-table', ['table' => $block['table']])
                    @endunless
                    @if (filled($block['note']))
                        <p class="adm-note">{{ $block['note'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
