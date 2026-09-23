{{-- Detalle de un trabajo fallido (panel lateral). Espera: $detail (FailedJobCatalog::detail()) o null. --}}
@include('live-presence.partials.detail-styles')

<div class="lxd">
    @if ($detail === null)
        <div class="lxd-box">Este fallido ya no existe: se reintentó o se eliminó mientras tanto.</div>
    @else
        <div class="lxd-top">
            <div>
                <div class="lxd-title">{{ $detail['job'] }}</div>
                <div class="lxd-text lxd-mono">{{ $detail['job_class'] }}</div>
            </div>
            @include('live-presence.partials.copy-button', ['text' => $detail['copy_text'], 'label' => 'Copiar diagnóstico'])
        </div>

        <div class="lxd-facts">
            <div class="lxd-fact"><span class="lxd-kicker">Falló</span><strong>{{ $detail['failed_at'] }}</strong><span class="lxd-text">{{ $detail['failed_ago'] }}</span></div>
            <div class="lxd-fact"><span class="lxd-kicker">Cola</span><strong>{{ $detail['queue'] }}</strong><span class="lxd-text">conexión {{ $detail['connection'] }}</span></div>
            <div class="lxd-fact"><span class="lxd-kicker">Intentos máx.</span><strong>{{ $detail['max_tries'] ?? 'sin límite' }}</strong><span class="lxd-text">timeout {{ $detail['timeout'] !== null ? $detail['timeout'].' s' : 'por defecto' }}</span></div>
            <div class="lxd-fact"><span class="lxd-kicker">Datos</span><strong>{{ $detail['models'] === [] ? '—' : implode(', ', array_column(array_slice($detail['models'], 0, 3), 'label')) }}</strong><span class="lxd-text">{{ count($detail['models']) > 3 ? '+'.(count($detail['models']) - 3).' más' : '' }}</span></div>
        </div>

        @if ($detail['sends_messages'])
            <div class="lxd-warn">Este trabajo envía mensajes. Reintentarlo puede duplicar el envío si llegó a salir antes de fallar.</div>
        @endif

        @include('live-presence.partials.exception-body', ['exception' => $detail['exception'], 'diagnosis' => $detail['diagnosis'], 'fullTrace' => $detail['exception_text']])

        <details class="lxd-box">
            <summary class="lxd-kicker" style="cursor: pointer;">Contenido del trabajo{{ $detail['encrypted'] ? ' (cifrado)' : '' }}</summary>
            <pre class="lxd-pre">{{ $detail['command_preview'] }}</pre>
        </details>

        <div class="lxd-text lxd-mono">UUID {{ $detail['uuid'] }}</div>
    @endif
</div>
