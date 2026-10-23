{{--
    Leyenda de la barra «Su día». Con jornada, una pastilla por categoría (con su
    explicación al pasar el mouse) y las marcas de jornada y guardia; sin jornada,
    los estados de conexión de siempre.
--}}
<div class="uam-legend">
    @if ($workday ?? false)
        @foreach (\App\Support\UserActivity\Workday\WorkMinuteKind::legend() as $kind)
            <span title="{{ $kind->description() }}"><i style="background: {{ $kind->color() }};"></i>{{ $kind->label() }}</span>
        @endforeach
        <span title="Su horario laboral: marcas finas de inicio y fin; lo que falta del día se ve vacío entre ellas"><i class="band work"></i>Franja de jornada</span>
        <span title="Guardia asignada en el Calendario TDG u Operaciones: marcas finas de inicio y fin"><i class="band guard"></i>Franja de guardia</span>
    @else
        @foreach ([\App\Support\UserActivity\UserActivityState::Active, \App\Support\UserActivity\UserActivityState::Idle, \App\Support\UserActivity\UserActivityState::Background] as $state)
            <span title="{{ $state->description() }}"><i style="background: {{ $state->color() }};"></i>{{ $state->label() }}</span>
        @endforeach
        <span><i style="background: var(--uam-track); border: 1px solid var(--uam-border);"></i>Desconectado</span>
    @endif
    @if ($showNow ?? false)
        <span><i style="background: #0ea5e9;"></i>Ahora</span>
    @endif
</div>
