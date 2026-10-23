@php
    /**
     * PDF de actividad de una persona (Negocios → Actividad de usuarios). Maquetado con
     * tablas y estilos simples para DomPDF; las barras y el mapa de calor son SVG
     * dibujados en el servidor ({@see \App\Support\UserActivity\Pdf\UserActivityPdfCharts}).
     *
     * @var array<string, mixed> $data  {@see \App\Support\UserActivity\Pdf\UserActivityPersonPdfData::build()}
     */
    use App\Support\UserActivity\Pdf\UserActivityPdfCharts as Charts;
    use App\Support\UserActivity\UserActivityClock as Clock;
    use App\Support\UserActivity\UserActivityState as State;
    use App\Support\UserActivity\Workday\WorkMinuteKind as Kind;

    $logoSrc = \App\Support\PdfOpaqueImage::fromPath(public_path('image/logoNewPdf.png'));
    $fmt = static fn (int $minutes): string => Clock::formatDuration($minutes);
    $clock = static fn (?int $minute): string => $minute === null ? '—' : Clock::formatMinute($minute);
    $num = static fn (int $value): string => number_format($value, 0, ',', '.');
    $usageColor = static fn (?int $usage): string => match (true) {
        $usage === null => '#64748B',
        $usage >= 70 => '#15803D',
        $usage >= 40 => '#B45309',
        default => '#B91C1C',
    };
    $totals = $data['totals'];
    $person = $data['person'];
    $workday = $data['subject'];
    $hourScale = Charts::dataUri(Charts::hourScale());
    $toneColor = ['good' => '#16A34A', 'bad' => '#EF4444', 'warn' => '#F59E0B', 'info' => '#0EA5E9'];
    $legend = $workday
        ? array_map(static fn (Kind $kind): array => [$kind->color(), $kind->label()], Kind::legend())
        : array_map(static fn (State $state): array => [$state->color(), $state->label()], [State::Active, State::Idle, State::Background]);
    $connectedDays = count(array_filter($data['days'], static fn (array $day): bool => $day['recorded'] && (int) ($day['day']['online'] ?? 0) > 0));
    $rankMax = static fn (array $ranking): int => max(1, ...array_column($ranking, 'total'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actividad de {{ $data['user']->name }}</title>
    <style>
        @page { margin: 10mm 9mm 13mm; size: A4 portrait; }
        body { margin: 0; background: #FFFFFF; font-family: Helvetica, Arial, sans-serif; color: #1E293B; font-size: 8.5pt; }
        table { border-collapse: collapse; width: 100%; }
        .muted { color: #64748B; }
        .header td { padding: 0 6px 4px; vertical-align: middle; }
        .title { font-size: 14pt; font-weight: bold; color: #1F4E9E; text-align: right; }
        .subtitle { font-size: 8pt; color: #64748B; text-align: right; }
        .band { background: #1F4E9E; color: #FFFFFF; }
        .band td { padding: 4px 10px; vertical-align: middle; }
        .band-name { font-size: 11.5pt; font-weight: bold; }
        .band-sub { font-size: 7.5pt; color: #DBEAFE; }
        .meta td { padding: 4px 10px; font-size: 7.5pt; color: #475569; background: #F1F5F9; }
        .section { margin-top: 7px; }
        .ribbon { background: #1F4E9E; color: #FFFFFF; font-weight: bold; font-size: 9pt; padding: 3px 10px; }
        .section-intro { font-size: 7.5pt; color: #64748B; padding: 3px 2px 0; }
        .kpis td { border: 2px solid #FFFFFF; background: #F1F5F9; padding: 4px 6px; vertical-align: top; }
        .kpi-label { font-size: 6.5pt; color: #64748B; font-weight: bold; letter-spacing: .3px; }
        .kpi-value { font-size: 12pt; font-weight: bold; color: #0F172A; padding-top: 1px; }
        .kpi-hint { font-size: 6.5pt; color: #64748B; padding-top: 1px; }
        .highlights td { padding: 2px 6px; border-bottom: 1px solid #E2E8F0; vertical-align: top; font-size: 7.5pt; line-height: 1.25; }
        .highlights .h-title { width: 100px; font-weight: bold; color: #0F172A; }
        .grid td { padding: 2px 5px; border-bottom: 1px solid #E2E8F0; vertical-align: middle; font-size: 7.5pt; }
        .grid th { background: #1F4E9E; color: #FFFFFF; font-size: 7pt; text-align: left; padding: 3px 5px; }
        .grid thead { display: table-header-group; }
        .grid tr { page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .dot { display: inline-block; width: 7px; height: 7px; }
        .legend td { font-size: 6.8pt; color: #475569; padding: 1px 6px 1px 0; white-space: nowrap; }
        .rank td { padding: 0.5px 4px; font-size: 7pt; }
        .rank .bar { background: #E2E8F0; height: 3px; }
        .rank .fill { background: #0EA5E9; height: 3px; }
        .sub { font-size: 6.5pt; color: #64748B; font-weight: normal; }
            </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 35%;">
                @if (filled($logoSrc))
                    <img src="{{ $logoSrc }}" alt="Tu Doctor en Casa" style="height: 32px;">
                @endif
            </td>
            <td>
                <div class="title">INFORME DE ACTIVIDAD EN EL SISTEMA</div>
                <div class="subtitle">Negocios · Actividad de usuarios · IntegraCorp</div>
            </td>
        </tr>
    </table>

    <table class="band">
        <tr>
            <td>
                <div class="band-name">{{ $data['user']->name }}</div>
                <div class="band-sub">{{ $data['profile']['label'] }}{{ $data['profile']['detail'] !== '' ? ' · '.$data['profile']['detail'] : '' }} · {{ $data['user']->email }}</div>
            </td>
            <td style="text-align: right;">
                <div style="font-size: 9.5pt; font-weight: bold;">{{ $data['range_label'] }}</div>
                <div class="band-sub">{{ count($data['days']) }} {{ count($data['days']) === 1 ? 'día' : 'días' }} · {{ $connectedDays }} con conexión</div>
            </td>
        </tr>
    </table>
    <table class="meta">
        <tr>
            <td>
                @if ($workday)
                    Jornada: <strong>{{ $data['schedule']?->label() }}</strong> · almuerzo de {{ $data['lunch_limit'] }} min
                @else
                    Sin jornada laboral medida: se muestran los estados de conexión.
                @endif
            </td>
            <td style="text-align: right;">Generado el {{ $data['generated_at'] }}{{ $data['generated_by'] ? ' por '.$data['generated_by'] : '' }}</td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 0; font-size: 6.6pt; color: #64748B;">Mide el uso de IntegraCorp, no toda la jornada: llamadas o trabajo fuera del sistema no se ven. «Activo» = teclado, mouse, toque o desplazamiento en los últimos minutos.</td>
        </tr>
    </table>

    {{-- ===================== CIFRAS DEL PERÍODO ===================== --}}
    <div class="section">
        <div class="ribbon">Cifras del período</div>
        @if ($workday)
            <table class="kpis">
                <tr>
                    <td style="width: 20%;"><div class="kpi-label">USO DE JORNADA</div><div class="kpi-value" style="color: {{ $usageColor($totals['work_usage']) }};">{{ $totals['work_usage'] !== null ? $totals['work_usage'].' %' : '—' }}</div><div class="kpi-hint">{{ $fmt((int) $totals['work_active'] + (int) $totals['work_meeting']) }} de {{ $fmt((int) $totals['work_base']) }} de jornada</div></td>
                    <td style="width: 20%;"><div class="kpi-label" style="color: {{ Kind::WorkActive->color() }};">ACTIVO EN EL SISTEMA</div><div class="kpi-value">{{ $fmt((int) $totals['work_active']) }}</div><div class="kpi-hint">En su horario</div></td>
                    <td style="width: 20%;"><div class="kpi-label" style="color: {{ Kind::WorkAbsent->color() }};">AUSENTE EN JORNADA</div><div class="kpi-value">{{ $fmt((int) $totals['work_absent']) }}</div><div class="kpi-hint">En su horario sin el sistema abierto</div></td>
                    <td style="width: 20%;"><div class="kpi-label" style="color: #B45309;">EXCESO DE ALMUERZO</div><div class="kpi-value">{{ $fmt((int) $totals['lunch_excess']) }}</div><div class="kpi-hint">Por encima de {{ $data['lunch_limit'] }} min</div></td>
                    <td style="width: 20%;"><div class="kpi-label" style="color: {{ Kind::Overtime->color() }};">ACTIVO PLUS</div><div class="kpi-value">{{ $fmt((int) $totals['overtime']) }}</div><div class="kpi-hint">Horas extra: fuera de horario, fin de semana o feriado</div></td>
                </tr>
            </table>
        @endif
        <table class="kpis">
            <tr>
                <td style="width: 20%;"><div class="kpi-label" style="color: {{ Kind::WorkActive->color() }};">TIEMPO ACTIVO</div><div class="kpi-value">{{ $fmt((int) $totals['active']) }}</div><div class="kpi-hint">Usando el sistema</div></td>
                <td style="width: 20%;"><div class="kpi-label" style="color: #A16207;">ABIERTO SIN USAR</div><div class="kpi-value">{{ $fmt((int) $totals['idle']) }}</div><div class="kpi-hint">Inactivo con el sistema a la vista</div></td>
                <td style="width: 20%;"><div class="kpi-label" style="color: #A16207;">EN OTRA PESTAÑA</div><div class="kpi-value">{{ $fmt((int) $totals['background']) }}</div><div class="kpi-hint">Sistema minimizado u oculto</div></td>
                <td style="width: 20%;"><div class="kpi-label">USO REAL</div><div class="kpi-value" style="color: {{ $usageColor((int) $totals['usage']) }};">{{ $totals['usage'] }} %</div><div class="kpi-hint">Activo ÷ conectado ({{ $fmt((int) $totals['online']) }})</div></td>
                <td style="width: 20%;"><div class="kpi-label">ACCIONES · PANTALLAS</div><div class="kpi-value">{{ $num((int) $totals['actions']) }} · {{ $num((int) $totals['pages']) }}</div><div class="kpi-hint">Llega {{ $clock($totals['avg_first']) }} · se va {{ $clock($totals['avg_last']) }} (promedio)</div></td>
            </tr>
        </table>
    </div>

    {{-- ===================== RESUMEN ===================== --}}
    <div class="section">
        <div class="ribbon">Resumen de la actividad</div>
        <table class="highlights">
            @foreach ($data['highlights'] as $item)
                <tr>
                    <td class="h-title" style="border-left: 3px solid {{ $toneColor[$item['tone']] ?? '#0EA5E9' }};">{{ $item['title'] }}</td>
                    <td>{{ $item['text'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    {{-- ===================== HÁBITOS ===================== --}}
    {{-- Título, mapa y nota en una sola celda: viajan juntos sin empujar el resto (es un bloque chico). --}}
    <table class="section">
        <tr>
            <td style="padding: 0;">
                <div class="ribbon">¿A qué horas usa el sistema? (minutos activos del período)</div>
                <div style="padding-top: 4px; text-align: center;">
                    <img src="{{ Charts::dataUri(Charts::heatmap($person['heatmap'], (int) $person['heat_max'])) }}" style="width: 128mm;">
                </div>
                <div class="section-intro" style="text-align: center;">Cada cuadro suma los minutos activos de esa hora en todos los días del período con ese día de la semana. Más verde = más uso.</div>
            </td>
        </tr>
    </table>

    @if ($person['top_pages'] !== [] || $person['top_panels'] !== [])
        <div class="section">
            <table>
                <tr>
                    @foreach (['Pantallas que más abre' => $person['top_pages'], 'Paneles donde trabaja' => $person['top_panels']] as $title => $ranking)
                        <td style="width: 50%; vertical-align: top; padding: 0 {{ $loop->first ? '6px' : '0' }} 0 {{ $loop->first ? '0' : '6px' }};">
                            <div class="ribbon">{{ $title }}</div>
                            @if ($ranking === [])
                                <div class="section-intro">Sin datos en el período.</div>
                            @else
                                @php $max = $rankMax($ranking); @endphp
                                <table class="rank">
                                    @foreach ($ranking as $item)
                                        <tr>
                                            <td>{{ $item['label'] }}<table><tr><td class="bar" style="padding: 0;"><div class="fill" style="width: {{ max(1, round($item['total'] * 100 / $max)) }}%;"></div></td></tr></table></td>
                                            <td class="num" style="width: 40px; font-weight: bold;">{{ $num((int) $item['total']) }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    {{-- La tabla de días va al final: se parte entre páginas por renglón y rellena lo que sobre, sin dejar huecos. --}}
    {{-- ===================== DÍAS DEL RANGO ===================== --}}
    <div class="section">
        <div class="ribbon">Días del rango · su día minuto a minuto</div>
        <table class="grid">
            <thead>
                <tr><th style="width: 70px;">Día</th><th>Barra del día (12a a 12a)</th><th class="num" style="width: 52px;">Activo</th><th class="num" style="width: 40px;">Uso</th>@if ($workday)<th class="num" style="width: 52px;">Jornada</th>@endif</tr>
            </thead>
            <tbody>
                @foreach ($data['days'] as $day)
                    @php $record = $day['day']; @endphp
                    <tr>
                        <td><strong>{{ $day['short'] }}</strong>@if ($day['plan_kind'] && $day['plan_kind'] !== \App\Support\UserActivity\Workday\WorkDayKind::Workday)<br><span class="sub">{{ $day['plan_kind']->label() }}</span>@endif</td>
                        <td>
                            @if ($record === null || (int) $record['online'] === 0)
                                <span class="sub">{{ $day['plan_kind'] === \App\Support\UserActivity\Workday\WorkDayKind::Workday ? 'Día laboral sin conexión' : 'Sin conexión' }}</span>
                            @elseif (! $day['detail_available'])
                                <span class="sub">Solo cifras del resumen (detalle de más de 90 días)</span>
                            @else
                                <img src="{{ Charts::dataUri(Charts::dayline($record['segments'], $record['bands'] ?? [])) }}" style="width: 100%; height: 9px;">
                            @endif
                        </td>
                        <td class="num" style="color: #15803D; font-weight: bold;">{{ $fmt((int) ($record['active'] ?? 0)) }}</td>
                        <td class="num">{{ $record !== null ? $record['usage'].' %' : '—' }}</td>
                        @if ($workday)
                            @php $dayUsage = $record['workday']['usage'] ?? null; @endphp
                            <td class="num" style="color: {{ $usageColor($dayUsage) }}; font-weight: bold;">{{ $dayUsage !== null ? $dayUsage.' %' : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr><td style="border-bottom: 0;"></td><td style="border-bottom: 0; padding-top: 0;"><img src="{{ $hourScale }}" style="width: 100%;"></td><td colspan="{{ $workday ? 3 : 2 }}" style="border-bottom: 0;"></td></tr>
            </tbody>
        </table>
        <table class="legend" style="margin-top: 4px; width: auto;">
            <tr>
                @foreach ($legend as $index => [$color, $label])
                    <td><span class="dot" style="background: {{ $color }};"></span> {{ $label }}</td>
                    @if ($index % 6 === 5)</tr><tr>@endif
                @endforeach
                @if ($workday)
                    <td><span class="dot" style="background: #FFFFFF; border-top: 2px solid #475569; border-left: 2px solid #475569; border-right: 2px solid #475569; height: 5px;"></span> Franja de jornada</td>
                    <td><span class="dot" style="background: #FFFFFF; border-top: 2px solid #6366F1; border-left: 2px solid #6366F1; border-right: 2px solid #6366F1; height: 5px;"></span> Franja de guardia</td>
                @endif
            </tr>
        </table>
    </div>

</body>
</html>
