@php
    $descriptions = [
        'whatsapp' => 'Mensajes por UltraMsg: avisos a clientes, red comercial, proveedores, pacientes y al personal que no usa Telegram.',
        'mail' => 'Todos los correos del sistema: propuestas, recibos, documentos, avisos internos y respaldos.',
        'telegram' => 'Avisos internos al personal vinculado y a los grupos aprobados. Si se apaga, salen por su respaldo.',
    ];
@endphp

<div class="ncc">
    <style>
        .ncc { display: grid; gap: 1rem; }
        .ncc-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); }
        .ncc-card { display: flex; flex-direction: column; gap: .85rem; padding: 1rem; border: 1px solid var(--can-line, rgb(148 163 184 / 26%)); border-radius: 1rem; background: var(--can-surface, #fff); }
        .ncc-card.is-off { border-color: rgb(220 38 38 / 45%); box-shadow: 0 0 0 3px rgb(220 38 38 / 8%); }
        .ncc-top { display: flex; align-items: center; gap: .65rem; }
        .ncc-icon { display: grid; place-items: center; width: 2.4rem; height: 2.4rem; border-radius: .75rem; background: var(--tone-soft); color: var(--tone); }
        .ncc-card.tone-whatsapp { --tone: #16a34a; --tone-soft: rgb(22 163 74 / 12%); }
        .ncc-card.tone-mail { --tone: #d97706; --tone-soft: rgb(217 119 6 / 12%); }
        .ncc-card.tone-telegram { --tone: #0284c7; --tone-soft: rgb(14 165 233 / 12%); }
        .ncc-name { margin: 0; font-size: 1rem; font-weight: 700; color: var(--can-text, #0f172a); }
        .ncc-state { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 600; padding: .15rem .5rem; border-radius: 999px; }
        .ncc-state.is-on { background: rgb(22 163 74 / 12%); color: #15803d; }
        .ncc-state.is-off { background: rgb(220 38 38 / 12%); color: #b91c1c; }
        .dark .ncc-state.is-on { color: #4ade80; }
        .dark .ncc-state.is-off { color: #f87171; }
        .ncc-desc { margin: 0; font-size: .8rem; line-height: 1.45; color: var(--can-muted, #64748b); }
        .ncc-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
        .ncc-stat { padding: .5rem .6rem; border-radius: .7rem; background: var(--can-surface-2, #f8fafc); }
        .ncc-stat b { display: block; font-size: 1.15rem; color: var(--can-text, #0f172a); font-variant-numeric: tabular-nums; }
        .ncc-stat span { font-size: .68rem; color: var(--can-muted, #64748b); }
        .ncc-split { font-size: .74rem; color: var(--can-muted, #64748b); }
        .ncc-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-top: auto; }
        .ncc-health { font-size: .76rem; line-height: 1.4; padding: .45rem .6rem; border-radius: .6rem; }
        .ncc-health.is-ok { background: rgb(22 163 74 / 10%); color: #166534; }
        .ncc-health.is-bad { background: rgb(220 38 38 / 10%); color: #991b1b; }
        .dark .ncc-health.is-ok { color: #86efac; }
        .dark .ncc-health.is-bad { color: #fca5a5; }
        .ncc-note { margin: 0; font-size: .78rem; color: var(--can-muted, #64748b); }
        .ncc-meta { font-size: .7rem; color: var(--can-muted, #64748b); }
    </style>

    <p class="ncc-note">
        Los interruptores generales detienen un canal completo. Los {{ $this->criticalCount }} tipos críticos (claves OTP, alertas de seguridad y de colas, acceso al sistema) siguen saliendo aunque el canal esté apagado.
        @unless ($this->canManage())
            <strong>Solo un SUPERADMIN puede apagarlos o encenderlos.</strong>
        @endunless
    </p>

    <div class="ncc-grid">
        @foreach ($this->channels as $key => $item)
            <section class="ncc-card tone-{{ $key }} {{ $item['enabled'] ? '' : 'is-off' }}" wire:key="ncc-{{ $key }}-{{ $item['enabled'] ? 'on' : 'off' }}">
                <div class="ncc-top">
                    <span class="ncc-icon"><x-filament::icon :icon="$item['channel']->icon()" class="h-5 w-5" /></span>
                    <div style="flex: 1; min-width: 0;">
                        <p class="ncc-name">{{ $item['channel']->label() }}</p>
                        <span class="ncc-state {{ $item['enabled'] ? 'is-on' : 'is-off' }}">
                            <x-filament::icon :icon="$item['enabled'] ? 'heroicon-m-check-circle' : 'heroicon-m-no-symbol'" class="h-3.5 w-3.5" />
                            {{ $item['enabled'] ? 'Encendido' : 'Apagado en todo el sistema' }}
                        </span>
                    </div>
                </div>

                <p class="ncc-desc">{{ $descriptions[$key] }}</p>

                <div>
                    <div class="ncc-stats">
                        <div class="ncc-stat"><b>{{ number_format($item['sent'], 0, ',', '.') }}</b><span>Enviados 24 h</span></div>
                        <div class="ncc-stat"><b>{{ number_format($item['failed'], 0, ',', '.') }}</b><span>Fallidos</span></div>
                        <div class="ncc-stat"><b>{{ number_format($item['blocked'], 0, ',', '.') }}</b><span>Bloqueados</span></div>
                    </div>
                    @if ($key !== 'telegram')
                        <p class="ncc-split" style="margin: .4rem 0 0;">{{ number_format($item['internal'], 0, ',', '.') }} internos · {{ number_format($item['external'], 0, ',', '.') }} externos</p>
                    @endif
                </div>

                @if (isset($health[$key]))
                    <div class="ncc-health {{ $health[$key]['ok'] ? 'is-ok' : 'is-bad' }}">
                        {{ $health[$key]['message'] }} <span style="opacity: .7;">({{ $health[$key]['at'] }})</span>
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
