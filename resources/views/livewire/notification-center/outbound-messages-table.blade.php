@php
    $stats = $this->todayStats;
    $cards = [
        ['id' => 'telegram:internal', 'label' => 'Telegram interno', 'value' => $stats['telegram_internal'], 'icon' => 'heroicon-m-paper-airplane', 'tone' => 'tg'],
        ['id' => 'whatsapp:internal', 'label' => 'WhatsApp interno', 'value' => $stats['whatsapp_internal'], 'icon' => 'heroicon-m-chat-bubble-left-right', 'tone' => 'wa'],
        ['id' => 'whatsapp:external', 'label' => 'WhatsApp externo', 'value' => $stats['whatsapp_external'], 'icon' => 'heroicon-m-globe-alt', 'tone' => 'wa'],
        ['id' => 'mail:internal', 'label' => 'Correo interno', 'value' => $stats['mail_internal'], 'icon' => 'heroicon-m-envelope', 'tone' => 'mail'],
        ['id' => 'mail:external', 'label' => 'Correo externo', 'value' => $stats['mail_external'], 'icon' => 'heroicon-m-envelope-open', 'tone' => 'mail'],
        ['id' => 'failed', 'label' => 'Fallidos', 'value' => $stats['failed'], 'icon' => 'heroicon-m-x-circle', 'tone' => 'bad'],
        ['id' => 'blocked', 'label' => 'Bloqueados', 'value' => $stats['blocked'], 'icon' => 'heroicon-m-no-symbol', 'tone' => 'off'],
    ];
@endphp

<div class="ncl">
    <div class="ncl-head">
        <span class="ncl-title">
            Hoy · {{ number_format($stats['total'], 0, ',', '.') }} {{ $stats['total'] === 1 ? 'mensaje' : 'mensajes' }}
            <span>· pulse un contador para ver solo esos; púlselo de nuevo para quitarlo</span>
        </span>
        <div class="ncl-head-actions">
            @if ($this->catalogName)
                <span class="ncl-chip">
                    <x-filament::icon icon="heroicon-m-funnel" />
                    {{ $this->catalogName }}
                    <button type="button" wire:click="clearCatalogFilter" aria-label="Quitar filtro de notificación" title="Ver todas las notificaciones">
                        <x-filament::icon icon="heroicon-m-x-mark" />
                    </button>
                </span>
            @endif
            @if ($quick)
                <x-filament::button size="sm" color="gray" icon="heroicon-m-x-mark" wire:click="applyQuick(null)" wire:loading.attr="disabled" wire:target="applyQuick">
                    Quitar contador
                </x-filament::button>
            @endif
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="refreshLog" wire:loading.attr="disabled" wire:target="refreshLog">
                <span wire:loading.remove wire:target="refreshLog">Actualizar</span>
                <span wire:loading wire:target="refreshLog">Actualizando…</span>
            </x-filament::button>
        </div>
    </div>

    <div class="ncl-cards" role="group" aria-label="Contadores de hoy">
        @foreach ($cards as $card)
            <button
                type="button"
                wire:key="ncl-card-{{ $card['id'] }}"
                wire:click="applyQuick('{{ $card['id'] }}')"
                wire:loading.attr="disabled"
                wire:target="applyQuick"
                class="ncl-card tone-{{ $card['tone'] }} {{ $quick === $card['id'] ? 'is-on' : '' }} {{ $card['value'] === 0 ? 'is-zero' : '' }}"
                aria-pressed="{{ $quick === $card['id'] ? 'true' : 'false' }}"
            >
                @if ($quick === $card['id'])
                    <x-filament::icon icon="heroicon-m-check-circle" class="ncl-card-check" />
                @endif
                <span class="ncl-card-top">
                    <x-filament::icon :icon="$card['icon']" />
                    {{ $card['label'] }}
                </span>
                <span class="ncl-card-value">{{ number_format($card['value'], 0, ',', '.') }}</span>
            </button>
        @endforeach
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
