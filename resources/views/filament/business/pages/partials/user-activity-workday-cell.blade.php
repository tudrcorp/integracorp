{{--
    Uso de jornada de una persona en un día: barra, «activo de base» y las
    insignias que el administrador debe ver de un vistazo (exceso de almuerzo,
    almuerzo no declarado, horas extra, guardia). Recibe $work (resumen de
    WorkDayClassifier), $presence, $nowMinute e $isToday.
--}}
@php
    $workdayKind = $work['kind'];
    $isWorkday = $workdayKind === \App\Support\UserActivity\Workday\WorkDayKind::Workday;
    $lunchClosed = ! $isToday || $nowMinute >= \App\Support\UserActivity\Workday\WorkdayConfig::lunchLatest();
@endphp
@if ($work['usage'] !== null)
    <span class="uam-usage {{ $usageClass($work['usage']) }}" title="(Activo + reuniones, en jornada) ÷ jornada transcurrida, sin contar el almuerzo declarado">
        <span class="track"><span class="fill" style="width: {{ $work['usage'] }}%; display: block;"></span></span>{{ $work['usage'] }}%
    </span>
    <div class="uam-sub">{{ $fmt((int) $work['work_active'] + (int) $work['work_meeting']) }} de {{ $fmt((int) $work['base']) }}</div>
@elseif ($isWorkday)
    <span class="uam-usage none" title="La jornada empieza a las {{ \App\Support\UserActivity\UserActivityClock::formatMinute($work['work_start']) }}">⏳ Aún no empieza</span>
@else
    <span class="uam-usage none" title="{{ $work['kind_label'] }}">{{ $workdayKind->emoji() }} {{ $workdayKind->label() }}</span>
@endif
<div class="uam-badges">
    @if ($work['lunch_excess'] > 0 && ! in_array($presence ?? null, ['lunch', 'lunch_over'], true))
        <span class="uam-badge over" title="{{ ($work['lunch_reason'] ?? null) === 'auto' ? 'No marcó el regreso del almuerzo: se cerró al terminar la jornada' : 'Minutos de almuerzo por encima de la hora' }}">⏰ +{{ $fmt((int) $work['lunch_excess']) }} almuerzo</span>
    @endif
    @if ($isWorkday && ! $work['lunch_declared'] && $lunchClosed && $work['base'] > 0)
        <span class="uam-badge absent" title="No declaró almuerzo: esa hora cuenta como jornada y, si no trabajó, como ausencia">Sin almuerzo declarado</span>
    @endif
    @if ($work['meeting'] > 0)
        <span class="uam-badge meeting" title="Tiempo en reuniones declaradas">👥 {{ $fmt((int) $work['meeting']) }} en reuniones</span>
    @endif
    @if ($work['overtime'] > 0)
        <span class="uam-badge extra" title="Uso del sistema fuera de su horario, en fin de semana o feriado">🌙 +{{ $fmt((int) $work['overtime']) }} extra</span>
    @endif
    @if ($work['guard'] > 0)
        <span class="uam-badge guard" title="Tiempo de guardia con el sistema abierto (no se penaliza la inactividad)">🛡️ Guardia {{ $work['guard_presence'] }}% presente</span>
    @endif
</div>
