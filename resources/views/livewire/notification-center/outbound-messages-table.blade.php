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
    <style>
        .ncl { display: grid; gap: 1rem; }
        .ncl-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
        .ncl-title { font-size: .8rem; font-weight: 600; color: var(--can-muted, #64748b); }
        .ncl-cards { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); }
        .ncl-card { display: flex; flex-direction: column; gap: .2rem; padding: .7rem .8rem; text-align: left; border: 1px solid var(--can-line, rgb(148 163 184 / 26%)); border-radius: .85rem; background: var(--can-surface, #fff); cursor: pointer; transition: border-color 120ms ease, box-shadow 120ms ease, transform 120ms ease; }
        .ncl-card:hover { transform: translateY(-1px); box-shadow: 0 6px 18px -12px rgb(15 23 42 / 45%); }
        .ncl-card.is-on { border-color: var(--tone); box-shadow: 0 0 0 3px var(--tone-soft); }
        .ncl-card-top { display: flex; align-items: center; gap: .4rem; font-size: .72rem; font-weight: 600; color: var(--can-muted, #64748b); }
        .ncl-card-top svg { color: var(--tone); }
        .ncl-card-value { font-size: 1.45rem; font-weight: 700; line-height: 1.1; color: var(--can-text, #0f172a); font-variant-numeric: tabular-nums; }
        .ncl-card.tone-tg { --tone: #0284c7; --tone-soft: rgb(14 165 233 / 14%); }
        .ncl-card.tone-wa { --tone: #16a34a; --tone-soft: rgb(22 163 74 / 14%); }
        .ncl-card.tone-mail { --tone: #d97706; --tone-soft: rgb(217 119 6 / 14%); }
        .ncl-card.tone-bad { --tone: #dc2626; --tone-soft: rgb(220 38 38 / 14%); }
        .ncl-card.tone-off { --tone: #64748b; --tone-soft: rgb(100 116 139 / 16%); }
        .ncl-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; background: rgb(14 165 233 / 12%); color: #0369a1; }
        .dark .ncl-chip { color: #7dd3fc; }
        .ncl-chip button { display: inline-flex; opacity: .7; }
        .ncl-chip button:hover { opacity: 1; }
    </style>

    <div class="ncl-head">
        <span class="ncl-title">
            Hoy · {{ number_format($stats['total'], 0, ',', '.') }} {{ $stats['total'] === 1 ? 'mensaje' : 'mensajes' }}
            <span style="font-weight: 400;">· pulse un contador para ver solo esos</span>
        </span>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;">
            @if ($this->catalogName)
                <span class="ncl-chip">
                    <x-filament::icon icon="heroicon-m-funnel" class="h-3.5 w-3.5" />
                    {{ $this->catalogName }}
                    <button type="button" wire:click="clearCatalogFilter" aria-label="Quitar filtro de notificación">
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                    </button>
                </span>
            @endif
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-path" wire:click="refreshLog" wire:loading.attr="disabled" wire:target="refreshLog">
                Actualizar
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
                class="ncl-card tone-{{ $card['tone'] }} {{ $quick === $card['id'] ? 'is-on' : '' }}"
                aria-pressed="{{ $quick === $card['id'] ? 'true' : 'false' }}"
            >
                <span class="ncl-card-top">
                    <x-filament::icon :icon="$card['icon']" class="h-4 w-4" />
                    {{ $card['label'] }}
                </span>
                <span class="ncl-card-value">{{ number_format($card['value'], 0, ',', '.') }}</span>
            </button>
        @endforeach
    </div>

    {{ $this->table }}

    <x-filament-actions::modals />
</div>
