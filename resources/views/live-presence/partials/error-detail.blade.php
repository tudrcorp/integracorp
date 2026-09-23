{{-- Detalle de un error del sistema (panel lateral). Espera: $error (ErrorTracker::group()) o null, $copyText. --}}
@include('live-presence.partials.detail-styles')

<div class="lxd">
    @if ($error === null)
        <div class="lxd-box">Este error ya no está en el registro (se descartó o venció).</div>
    @else
        <div class="lxd-top">
            <div>
                <div class="lxd-title">{{ $error['short_class'] }} · {{ $error['status_label'] }}</div>
                <div class="lxd-text">{{ $error['count'] }} {{ $error['count'] === 1 ? 'vez' : 'veces' }} · {{ $error['users'] }} {{ $error['users'] === 1 ? 'usuario afectado' : 'usuarios afectados' }}</div>
            </div>
            @include('live-presence.partials.copy-button', ['text' => $copyText, 'label' => 'Copiar diagnóstico'])
        </div>

        <div class="lxd-facts">
            <div class="lxd-fact"><span class="lxd-kicker">Primera vez</span><strong>{{ $error['first_at_label'] }}</strong></div>
            <div class="lxd-fact"><span class="lxd-kicker">Última vez</span><strong>{{ $error['last_at_label'] }}</strong><span class="lxd-text">{{ $error['last_ago'] }}</span></div>
            <div class="lxd-fact"><span class="lxd-kicker">Último usuario</span><strong>{{ $error['last_context']['user_name'] ?? '—' }}</strong><span class="lxd-text">{{ ! empty($error['last_context']['user_id']) ? 'ID '.$error['last_context']['user_id'] : '' }}</span></div>
            <div class="lxd-fact"><span class="lxd-kicker">Estado</span><strong>{{ $error['status_label'] }}</strong><span class="lxd-text">{{ $error['resolved_by'] ? 'resuelto por '.$error['resolved_by'].' · '.$error['resolved_at'] : '' }}</span></div>
        </div>

        @include('live-presence.partials.exception-body', ['exception' => [
            'class' => $error['class'], 'message' => $error['message'], 'origin' => $error['origin'], 'location' => $error['location'], 'app_frames' => $error['app_frames'],
        ], 'diagnosis' => $error['diagnosis'], 'fullTrace' => implode("\n", array_map(static fn (array $frame): string => $frame['file'].($frame['line'] !== null ? ':'.$frame['line'] : '').'  '.$frame['call'], $error['frames']))])

        <div class="lxd-box">
            <div class="lxd-kicker">Dónde ocurrió (últimos contextos)</div>
            @foreach ($error['contexts'] as $context)
                <div class="lxd-mono" style="padding: 3px 0;">{{ $context }}</div>
            @endforeach
        </div>

        <div class="lxd-text lxd-mono">Huella {{ $error['fingerprint'] }}</div>
    @endif
</div>
