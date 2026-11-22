@php
    $descriptions = [
        'whatsapp' => 'Mensajes por UltraMsg: avisos a clientes, red comercial, proveedores, pacientes y al personal que no usa Telegram.',
        'mail' => 'Todos los correos del sistema: propuestas, recibos, documentos, avisos internos y respaldos.',
        'telegram' => 'Avisos internos al personal vinculado y a los grupos aprobados. Si se apaga, salen por su respaldo.',
    ];
@endphp

<div class="ncc">
    <p class="ncc-note">
        <x-filament::icon icon="heroicon-m-information-circle" />
        <span>
            Cada interruptor detiene un canal completo. Los {{ $this->criticalCount }} tipos críticos (claves OTP, alertas de seguridad y de colas, acceso al sistema) siguen saliendo aunque el canal esté apagado.
            @unless ($this->canManage())
                <strong>Solo un SUPERADMIN puede apagarlos o encenderlos.</strong>
            @endunless
        </span>
    </p>

    <div class="ncc-grid">
        @foreach ($this->channels as $key => $item)
            @php
                $total = $item['sent'] + $item['failed'] + $item['blocked'];
                $share = fn (int $value): string => $total > 0 ? round($value * 100 / $total, 2).'%' : '0%';
            @endphp
            <section class="ncc-card tone-{{ $key }} {{ $item['enabled'] ? '' : 'is-off' }}" wire:key="ncc-{{ $key }}-{{ $item['enabled'] ? 'on' : 'off' }}" aria-label="{{ $item['channel']->label() }}">
                <div class="ncc-top">
                    <span class="ncc-icon"><x-filament::icon :icon="$item['channel']->icon()" /></span>
                    <div style="flex: 1; min-width: 0;">
                        <p class="ncc-name">{{ $item['channel']->label() }}</p>
                        <span class="ncc-state {{ $item['enabled'] ? 'is-on' : 'is-off' }}">
                            <i aria-hidden="true"></i>
                            {{ $item['enabled'] ? 'Encendido' : 'Apagado en todo el sistema' }}
                        </span>
                    </div>
                </div>

                <p class="ncc-desc">{{ $descriptions[$key] ?? '' }}</p>

                <div>
                    <div class="ncc-stats">
                        <div class="ncc-stat"><b>{{ number_format($item['sent'], 0, ',', '.') }}</b><span>Enviados 24 h</span></div>
                        <div class="ncc-stat {{ $item['failed'] > 0 ? 'is-bad' : '' }}"><b>{{ number_format($item['failed'], 0, ',', '.') }}</b><span>Fallidos</span></div>
                        <div class="ncc-stat"><b>{{ number_format($item['blocked'], 0, ',', '.') }}</b><span>Bloqueados</span></div>
                    </div>
                    @if ($total > 0)
                        <div class="ncc-bar" role="img" aria-label="{{ $item['sent'] }} enviados, {{ $item['failed'] }} fallidos y {{ $item['blocked'] }} bloqueados en 24 horas">
                            <span style="width: {{ $share($item['sent']) }}; background: var(--can-wa);"></span>
                            <span style="width: {{ $share($item['failed']) }}; background: var(--can-off);"></span>
                            <span style="width: {{ $share($item['blocked']) }}; background: var(--can-muted);"></span>
                        </div>
                    @endif
                    @if ($key !== 'telegram')
                        <p class="ncc-split">{{ number_format($item['internal'], 0, ',', '.') }} internos · {{ number_format($item['external'], 0, ',', '.') }} externos</p>
                    @endif
                </div>

                @if (isset($health[$key]))
                    <div class="ncc-health {{ $health[$key]['ok'] ? 'is-ok' : 'is-bad' }}" role="status">
                        <x-filament::icon :icon="$health[$key]['ok'] ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle'" />
                        <span>{{ $health[$key]['message'] }} <small>({{ $health[$key]['at'] }})</small></span>
                    </div>
                @endif

                <div class="ncc-actions">
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-signal" wire:click="checkHealth('{{ $key }}')" wire:loading.attr="disabled" wire:target="checkHealth('{{ $key }}')">
                        <span wire:loading.remove wire:target="checkHealth('{{ $key }}')">Comprobar conexión</span>
                        <span wire:loading wire:target="checkHealth('{{ $key }}')">Comprobando…</span>
                    </x-filament::button>
                    {{ ($this->toggleChannelAction)(['channel' => $key]) }}
                </div>

                @if ($item['updated_by'])
                    <span class="ncc-meta">Último cambio: {{ $item['updated_by'] }} · {{ $item['updated_at'] }}</span>
                @endif
            </section>
        @endforeach
    </div>

    <x-filament-actions::modals />
</div>
