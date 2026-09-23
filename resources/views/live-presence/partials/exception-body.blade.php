{{--
    Cuerpo común del detalle de un error: diagnóstico, dónde corregir, traza
    de nuestro código y traza completa plegable.
    Espera: $exception (ExceptionFingerprint), $diagnosis (FailureDiagnosis), $fullTrace (string|null).
--}}
@php
    $categoryColors = ['provider' => '#d97706', 'network' => '#d97706', 'code' => '#dc2626', 'resources' => '#dc2626', 'config' => '#0284c7', 'data' => '#64748b', 'unknown' => '#64748b'];
    $color = $categoryColors[$diagnosis['category']] ?? '#64748b';
@endphp

<div class="lxd-box" style="border-left: 4px solid {{ $color }};">
    <div class="lxd-kicker" style="color: {{ $color }};">{{ $diagnosis['category_label'] }} · recomendado: {{ $diagnosis['action_label'] }}</div>
    <div class="lxd-title">{{ $diagnosis['title'] }}</div>
    <div class="lxd-text">{{ $diagnosis['advice'] }}</div>
</div>

<div class="lxd-box">
    <div class="lxd-kicker">Error</div>
    <div class="lxd-mono" style="font-weight: 700;">{{ $exception['class'] }}</div>
    <div class="lxd-message">{{ $exception['message'] }}</div>
</div>

<div class="lxd-grid">
    <div class="lxd-box">
        <div class="lxd-kicker">Dónde corregir (nuestro código)</div>
        <div class="lxd-mono lxd-origin">{{ $exception['origin'] ?: 'No se encontró una línea del proyecto en la traza.' }}</div>
    </div>
    <div class="lxd-box">
        <div class="lxd-kicker">Dónde se lanzó</div>
        <div class="lxd-mono">{{ $exception['location'] ?: '—' }}</div>
    </div>
</div>

<div class="lxd-box">
    <div class="lxd-kicker">Traza de nuestro código ({{ count($exception['app_frames']) }})</div>
    @forelse ($exception['app_frames'] as $frame)
        <div class="lxd-frame">
            <span class="lxd-mono lxd-file">{{ $frame['file'] }}{{ $frame['line'] !== null ? ':'.$frame['line'] : '' }}</span>
            <span class="lxd-mono lxd-call">{{ $frame['call'] }}</span>
        </div>
    @empty
        <div class="lxd-text">El error ocurrió por completo dentro de librerías (vendor). Revise la traza completa.</div>
    @endforelse
</div>

@if (! empty($fullTrace))
    <details class="lxd-box">
        <summary class="lxd-kicker" style="cursor: pointer;">Traza completa (incluye librerías)</summary>
        <pre class="lxd-pre">{{ $fullTrace }}</pre>
    </details>
@endif
